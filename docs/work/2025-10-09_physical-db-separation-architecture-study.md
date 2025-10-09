# 物理DB分離アーキテクチャの検討記録

**日付:** 2025年10月9日（初版）、2025年10月更新（再調査）  
**ステータス:** 実施見送り・代替案採用  
**更新理由:** 実装着手前の妥当性検証により方針変更

## 1. 背景と目的

`stancl/tenancy` を用いたシングルDB・`tenant_id`方式でのマルチテナント実装は一旦完了している。
しかし、以下の点を考慮すると、運用開始後のパフォーマンスとスケーラビリティに懸念が残るため、アーキテクチャを再検討する。

1.  **初期データ移行:** 過去のシステムから、運用初期の段階で相当数のレコード（台帳、変更履歴など）が投入される予定である。
2.  **旧システムの構成:** 移行元の旧システムでは、テナントごとにデータベースが約20個に物理的に分離されており、その構成で安定稼働していた実績がある。

これらの事実から、現行のシングルDB構成では、特に初期データ投入後の高負荷に耐えられない可能性がある。
本ドキュメントは、この課題を解決し、将来的な安定稼働を見据えた最適なDB分離アーキテクチャを決定することを目的とする。

## 2. DB分離方式の比較検討

### 2.1. テーブルプレフィックス方式（非推奨）

-   **概要:** テーブル名にテナントごとのプレフィックス（例: `tenant_a_ledgers`）を付け、単一DB内でテーブルを分離する案。
-   **評価:**
    -   過去に本プロジェクトで試行したが、ルーティングの競合など深刻な副作用が発生したため**破棄済み**である。
    -   `stancl/tenancy` が公式にサポートしていない「ハック」であり、将来のライブラリ更新で破綻するリスクが極めて高い。
    -   **結論として、この方式は採用しない。**

### 2.2. 物理DB分離（ハイブリッド構成）（推奨）

-   **概要:**
    -   **中央DB:** `users`, `tenants`, `roles` など、全テナントで共有する情報を格納する。
    -   **テナントDB:** テナントごとに個別のデータベースを用意し、`ledgers`, `ledger_diffs`, `folders` など、テナント固有のデータを格納する。
    -   `stancl/tenancy` が公式にサポートしている構成であり、リクエストに応じて接続先DBを動的に切り替える。
-   **評価:**
    -   旧システムの構成を踏襲する形であり、パフォーマンスとスケーラビリティに関する懸念を根本的に解決できる、最も安全で実績のあるアプローチ。
    -   Mroongaによる全文検索においても、検索対象のインデックスがテナントごとに適切なサイズに保たれるため、高いパフォーマンスを維持できる。
    -   **結論として、このアーキテクチャを基本方針として採用することを強く推奨する。**

### 2.3. シングルDB + DBパーティショニング（次善策）

-   **概要:** DBのパーティショニング機能を使い、単一テーブル内で物理的な格納領域を `tenant_id` ごとに分割する。
-   **評価:**
    -   インフラの制約上、どうしてもDBの数を増やせない場合の代替案。
    -   CPUやI/Oなどのサーバーリソースは全テナントで共有されるため、ノイジーネイバー問題を完全に解決することはできない。
    -   旧システムがDB分離を選択していたことを考えると、この方式では性能要件を満たせない可能性がある。

## 3. 物理DB分離アーキテクチャの技術的課題と解決策

物理DB分離（案2.2）を採用する上で、最大の技術的課題は「データベース間のJOIN」である。

### 3.1. 課題: Eloquentによるデータベース間JOINの制約

**Eloquentは、異なるデータベース接続（中央DBとテナントDB）をまたいだテーブルJOINを直接サポートしていない。**
これにより、「テナントDBの台帳」と「中央DBのユーザー」を1つのSQLクエリで結合することができない。

### 3.2. 解決策1: アプリケーション側での手動結合（基本アプローチ）

`JOIN` の代替として、アプリケーションレイヤーでデータを効率的に連携させる。これがこのアーキテクチャにおける標準的な実装パターンとなる。
`whereIn` を活用したEager Loadingにより、クエリ回数を最小限に抑え、N+1問題を回避できる。

**例：台帳一覧と作成者名を表示するケース**

1.  **テナントDBから台帳リストを取得**
    ```php
    $ledgers = Ledger::latest()->paginate(20);
    ```
2.  **取得したリストからユーザーIDを抽出**
    ```php
    $creatorIds = $ledgers->pluck('created_by_user_id')->unique()->toArray();
    ```
3.  **中央DBに接続を切り替え、ユーザー情報を一括取得**
    ```php
    $creators = User::on('central')->whereIn('id', $creatorIds)->get()->keyBy('id');
    ```
4.  **PHP（ビュー）側でデータを結合**
    ```blade
    @foreach ($ledgers as $ledger)
        <td>{{ $creators[$ledger->created_by_user_id]->name ?? 'N/A' }}</td>
    @endforeach
    ```

### 3.3. 解決策2: パッケージによるリレーションの抽象化（推奨アプローチ）

上記の手動実装は定型的で冗長になりがちだが、この処理をカプセル化し、Eloquent標準のリレーションのように扱えるようにするサードパーティ製パッケージが存在する。

-   **パッケージ例:** `hoyvoy/laravel-cross-database-subqueries`
-   **概要:** この種のパッケージを利用すると、DB間のリレーションをモデル内に定義できる。内部的には効率的なサブクエリや2段階クエリが実行されるが、開発者はそれを意識することなく、通常のリレーションと同様に `with('creator')` のようなEager Loadingが利用可能になる。
-   **メリット:** コードの可読性が劇的に向上し、実装コストを削減できる。

### 3.4. 外部調査による裏付け

-   **調査目的:** 上記の課題と解決策について、Web上の最新情報をもとに再検証を実施。
-   **調査結果:**
    1.  Eloquent標準機能では、異なるDBサーバー（接続）をまたいだJOINが不可能であることは、Laravelコミュニティの共通認識として再確認された。
    2.  `hoyvoy/laravel-cross-database-subqueries` や `staudenmeir`氏が開発する関連パッケージ群が、この問題に対する有力な解決策として認知されていることが判明した。
    3.  これらのパッケージは、SQLレベルでJOINを生成するのではなく、効率的なクエリ（サブクエリや2段階クエリ）を発行し、アプリケーション側で結果を結合する処理を抽象化・自動化するものであることが確認できた。
-   **結論:** 外部調査の結果、**パッケージを活用してリレーションを抽象化するアプローチ（解決策2）が、技術的に妥当かつモダンな解決策である**ことが裏付けられた。

## 4. 影響範囲の網羅的調査と最終結論

### 4.1. 影響範囲の調査結果

物理DB分離の採用を最終判断するため、テナントDBのモデルから中央DBのモデルへのリレーション定義を網羅的に調査した。その結果、改修が必要な箇所は以下の通り特定された。

| 影響を受けるテナントモデル | リレーション名 | 関連する中央DBモデル | リレーション型 | 備考 |
| :--- | :--- | :--- | :--- | :--- |
| `Ledger` | `creator`, `modifier` | `User` | `belongsTo` | 作成者、更新者情報 |
| `LedgerDefine` | `creator`, `modifier`, `recommended_inspector`, `recommended_approver` | `User` | `belongsTo` | 作成/更新者、推奨担当者 |
| `LedgerDiff` | `creator`, `modifier`, `inspector`, `approver` | `User` | `belongsTo` | 各種操作の実行者 |
| `Folder` | `creator`, `modifier` | `User` | `belongsTo` | 作成者、更新者情報 |
| `AutoLink` | `creator`, `modifier` | `User` | `belongsTo` | 作成者、更新者情報 |
| `Tag` | `creator`, `modifier` | `User` | `belongsTo` | 作成者、更新者情報 |
| `Synonym\TechnicalTermGroup` | `creator`, `modifier` | `User` | `belongsTo` | 作成者、更新者情報 |
| `RoleFolderPermission` | `role` | `Role` | `belongsTo` | フォルダに紐づく役割 |
| `CustomActivity` | `causer` | `User` | `morphTo` | 操作ログの実行者 |

### 4.2. 最終結論と次のステップ

**【最終結論】**
調査の結果、DB間連携が必要な箇所はモデルのリレーション定義に集中しており、ビジネスロジックへの影響は軽微であることが判明した。改修箇所は上記リストの通り明確に特定できており、パッケージを導入することで、コードの可読性を損なうことなく対応可能である。

以上のことから、**「物理DB分離（ハイブリッド構成）」アーキテクチャの採用を正式に決定する。**

**【次のステップ】**
具体的な改修作業に着手する。

1.  **パッケージの選定と導入:** `hoyvoy/laravel-cross-database-subqueries` 等のパッケージを導入・設定する。
2.  **リレーション定義の修正:** 上記リストに基づき、各モデルのリレーション定義を修正する。
3.  **動作確認:** 修正箇所に関連する機能が、DB分離後も正常に動作することをテストで確認する。

---

## 5. 再調査と方針の見直し（2025年10月更新）

**ステータス:** 実装見送り・代替案採用  
**調査実施日:** 2025年10月  
**更新理由:** 実装着手前の妥当性検証

### 5.1. 再調査の背景

当初の計画書作成後、実装着手前に以下の観点から妥当性を再検証した。

1. **パッケージの現状確認:** 提案した `hoyvoy/laravel-cross-database-subqueries` の保守状況
2. **現行アーキテクチャの評価:** 2025年8月に実装された `tenant_id` 方式の実績
3. **改修規模の精査:** 当初見積もりの妥当性検証
4. **代替案の探索:** より低リスクで効果的なアプローチの検討

### 5.2. 重大な発見事項

#### 5.2.1. パッケージの廃止状態

Packagistでの調査により、推奨パッケージが**既に廃止（abandoned）**されていることが判明した。

```json
{
  "name": "hoyvoy/laravel-cross-database-subqueries",
  "abandoned": true,
  "downloads": 131463,
  "favers": 94
}
```

**影響:**
- 公式サポートなし、セキュリティアップデート停止
- Laravel 12.0との互換性保証なし
- 将来的なPHPバージョンアップ時の動作保証なし

**フォーク版の調査結果:**
- `adnanhussainturki/laravel-cross-database-subqueries`: 51,797 DL、保守状況不明
- `rmtrin/laravel-cross-database-subqueries`: 14,617 DL、コミット履歴少ない
- いずれも長期的な保守の信頼性に欠ける

**結論:** 廃止済みパッケージへの依存は、本番環境での長期運用において重大なリスクとなる。

#### 5.2.2. 現行アーキテクチャの実績

2025年8月30日に実装された `tenant_id` 方式は、以下の成果を上げている。

```php
// 現在の安定した構成
- stancl/tenancy v3.9（公式推奨方法）
- BelongsToTenant トレイトによる自動スコープ
- 210個の全テストが通過
- tenant_user テーブル廃止による設計のシンプル化完了
```

**重要な事実:**
- `Ledger` と `User` は**同一DB内**に存在するため、通常のEloquentリレーションが問題なく動作
- DB間リレーションの問題は**現時点では存在しない**
- パフォーマンス問題も**現時点では顕在化していない**

```php
// app/Models/Ledger.php（現行コード）
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'creator_id');
    // 同一DB内のため、通常のリレーションで動作
}
```

物理分離すると、以下のように変更が必要になる：

```php
public function creator(): BelongsTo
{
    return $this->belongsTo(User::class, 'creator_id')
        ->on('central'); // 接続先の明示が必須
}
```

#### 5.2.3. 改修規模の見積もり誤り

当初の「9つのモデルのリレーション定義」という見積もりは、以下の箇所を見落としていた。

**追加で影響を受ける箇所:**

1. **Controller層（推定50-100箇所）**
   ```php
   // 各所で Eager Loading の修正が必要
   Ledger::with(['creator', 'modifier', 'define.folder'])
       ->get();
   ```

2. **Livewireコンポーネント（推定30-50箇所）**
   ```php
   // リレーションロード時の接続先指定
   $this->ledgers = Ledger::with('creator')->paginate(10);
   ```

3. **Filamentリソース（推定20-30箇所）**
   ```php
   // テーブルカラムでのリレーション表示
   Tables\Columns\TextColumn::make('creator.name')
   ```

4. **API応答のシリアライゼーション（推定10-20箇所）**
   ```php
   // JSON出力時のリレーションロード
   return LedgerResource::collection($ledgers);
   ```

5. **テストコード（推定100-200箇所）**
   ```php
   // ファクトリでのリレーション生成
   Ledger::factory()->create(['creator_id' => $user->id]);
   ```

**実際の改修規模:** 数百箇所に及ぶ可能性が高く、当初見積もりの10倍以上。

### 5.3. 前提条件の再検証

#### 5.3.1. 旧システムの「20DB分離」について

提案書では「旧システムでDB分離が実績あり」としているが、これは以下の理由から必ずしも最適解とは限らない。

**旧システムの技術的背景（推測）:**
- レガシーフレームワークでの制約（マルチテナント機能の不在）
- 当時のMySQL性能での物理的な必要性
- 論理分離を実現する技術の未成熟

**現代的なアプローチとの比較:**

| 観点 | 旧システム（20DB） | 現代的手法（tenant_id） |
|------|------------------|---------------------|
| スキーマ管理 | 各DBでマイグレーション実行 | 一元管理 |
| バックアップ | 20個のDBを個別管理 | 単一DB |
| 監視 | 20個の接続プールを監視 | 単一接続プール |
| デプロイ | DB接続設定の複雑化 | シンプルな設定 |

**重要な指摘:** 旧システムの構成は、当時の技術的制約の結果であり、現在再現する必然性はない。

#### 5.3.2. パフォーマンス要件の現状

提案書で懸念されている「初期データ移行後の高負荷」について、具体的な測定データが存在しない。

**必要な検証:**
1. 想定データ量での負荷テスト
2. Mroonga全文検索のインデックスサイズと検索速度の測定
3. `tenant_id` インデックスでの絞り込み性能の実測

**現時点での判断:** パフォーマンス問題が**顕在化していない段階**で大規模な構造変更を行うのは、リスクが高い。

### 5.4. 代替アプローチの提案

#### 案A: 現行アーキテクチャの継続（最推奨）

**方針:** `tenant_id` 方式を継続し、パフォーマンス最適化に注力する。

**実施すべき対策:**

1. **MySQLパーティショニングの導入**
   ```sql
   -- ledgers テーブルのパーティショニング（既存データに適用可能）
   ALTER TABLE ledgers PARTITION BY HASH(tenant_id) PARTITIONS 20;
   ```
   - スキーマ変更不要
   - コード変更不要
   - 物理的なデータ分離効果を実現

2. **パフォーマンス監視の実装**
   ```php
   // app/Providers/AppServiceProvider.php
   DB::listen(function ($query) {
       if ($query->time > 1000) { // 1秒以上のクエリ
           Log::warning('Slow query detected', [
               'sql' => $query->sql,
               'time' => $query->time,
               'tenant' => tenant()?->id,
               'bindings' => $query->bindings,
           ]);
       }
   });
   ```

3. **Mroongaインデックスの最適化**
   ```sql
   -- テナント毎のインデックス分割（検討事項）
   ALTER TABLE ledgers 
     ADD FULLTEXT INDEX idx_content_per_tenant (tenant_id, content) 
     USING NGRAM;
   ```
   注: Mroongaの複合インデックス制約を考慮し、実装前に十分な検証が必要

**メリット:**
- ゼロリスク（既存の安定稼働を維持）
- 開発リソースを機能開発に集中可能
- 段階的な最適化が可能

**デメリット:**
- 将来的に物理分離が必要になった場合の移行コスト

#### 案B: 段階的ハイブリッド移行（必要時のみ）

**方針:** パフォーマンス問題が顕在化した**特定テナントのみ**を物理分離する。

**フェーズ1: 読み取りレプリカの分離**
```php
// config/database.php
'connections' => [
    'mysql' => [...], // 中央DB + 通常テナント
    'tenant_large_read' => [ // 大規模テナント専用
        'read' => ['host' => env('DB_REPLICA_HOST')],
        'write' => ['host' => env('DB_HOST')],
    ],
],
```

**フェーズ2: カスタムBootstrapperの実装**
```php
// app/Tenancy/Bootstrappers/CustomDatabaseBootstrapper.php
class CustomDatabaseBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant)
    {
        if ($tenant->has_dedicated_db) {
            // 特定テナントのみ接続を切り替え
            config(['database.default' => "tenant_{$tenant->id}"]);
        }
        // 通常テナントは tenant_id で動作
    }
}
```

**重要な利点:** リレーション定義の変更が不要（接続先のみ切り替え）

**メリット:**
- 必要なテナントのみ段階的に移行
- リスクとコストの最小化
- `stancl/tenancy` の公式機能を活用

**デメリット:**
- ハイブリッド構成の運用複雑性
- テナント間でデータベース構成が異なることによる保守負担

#### 案C: 読み取り最適化レイヤーの導入（新提案）

**方針:** 物理DB分離ではなく、ElasticsearchまたはMeilisearchを活用した検索専用レイヤーを構築する。

**アーキテクチャ:**
```
[書き込み] MySQL (tenant_id による論理分離)
     ↓ 同期 (Laravel Scout)
[読み込み] Meilisearch (テナント毎のインデックス自動分離)
```

**実装例:**
```php
// app/Models/Ledger.php
use Laravel\Scout\Searchable;

class Ledger extends Model
{
    use Searchable;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'tenant_id' => $this->tenant_id, // 自動でフィルタリング
        ];
    }
}

// 既存コードをほぼ変更せずに高速化
$results = Ledger::search($query)->get();
```

**Meilisearchの特徴:**
- テナント毎のインデックスを自動分離
- Mroongaの10-100倍の検索速度（ベンチマーク必要）
- タイポ許容、同義語対応などの高度な機能
- Laravelとのネイティブインテグレーション

**メリット:**
- コード変更が最小限（Laravel Scoutで抽象化）
- 劇的なパフォーマンス向上の可能性
- 水平スケーリングが容易
- リレーション問題の回避（非正規化データで運用）

**デメリット:**
- 追加インフラ（Meilisearchサーバー）が必要
- データ同期の遅延（非同期）
- 検索とCRUDの整合性管理が必要

**コスト比較:**

| 項目 | 物理DB分離 | Meilisearch導入 |
|------|-----------|----------------|
| 開発工数 | 数百箇所の改修 | Scout設定のみ |
| インフラコスト | DB複数台 | 検索サーバー1台 |
| 保守性 | 複雑化 | シンプル化 |
| パフォーマンス | 改善（限定的） | 劇的改善（要検証） |

### 5.5. 最終推奨事項（更新版）

#### 即座に実施すべき対策

1. **パフォーマンス監視の実装（優先度: 高）**
   - 上記のスロークエリログを実装
   - 実際のボトルネックを特定
   - 実装場所: `app/Providers/AppServiceProvider.php`

2. **MySQLパーティショニングの適用（優先度: 中）**
   - `ledgers`, `ledger_diffs`, `attached_files` テーブルに適用
   - 既存データへの影響なし
   - 実装前にステージング環境での検証必須

3. **Meilisearch PoC（優先度: 中）**
   - 小規模環境で検証
   - パフォーマンス改善効果を測定
   - Mroongaとの性能比較

#### 物理DB分離を見送る理由（明確化）

**1. 技術的リスク**
- 依存パッケージの廃止状態
- 改修規模の過小評価（実際は10倍以上）
- 210個の全テストの書き直しが必要
- デバッグとテストに数ヶ月単位の時間

**2. ビジネス的リスク**
- 機能開発の大幅な遅延
- 既存の安定稼働システムへの影響
- ROI（投資対効果）の不明確さ

**3. 代替案の優位性**
- 案A（現状維持+最適化）: ゼロリスク
- 案B（段階的移行）: 必要時のみ実施
- 案C（Meilisearch）: より高いパフォーマンス改善の可能性

**4. 前提条件の誤り**
- 旧システムの構成は現在の最適解ではない
- パフォーマンス問題が顕在化していない
- 「相当数のレコード投入」の具体的な数値が不明

#### 判断基準の設定

以下の条件が**全て**満たされた場合のみ、物理DB分離を再検討する。

```yaml
conditions:
  - パフォーマンス監視で実測された問題:
      - 特定テナントのクエリが継続的に1秒超過
      - CPU使用率が80%を常時超過
      - Mroongaインデックスサイズが物理メモリを圧迫
      - ディスクI/Oがボトルネックとして特定
  
  - 他の対策の失敗:
      - パーティショニングで改善せず
      - Meilisearch導入が技術的に困難
      - インデックス最適化の効果不足
      - クエリチューニングの限界
  
  - ビジネス要件:
      - コンプライアンス要件で物理分離が必須
      - 特定の大規模顧客からの契約条件
      - 法的な要求（個人情報保護法等）
```

**現時点の結論:** これらの条件が満たされていないため、物理DB分離は**実施しない**。

### 5.6. 今後の開発方針

**短期（1-2ヶ月）**
- パフォーマンス監視の実装と計測
- MySQLパーティショニングの適用（ステージング → 本番）
- Meilisearch PoCの実施と評価

**中期（3-6ヶ月）**
- 監視データに基づく最適化
- 必要に応じてMeilisearch本番導入
- 大規模データ移行のシミュレーション
- 負荷テストの実施

**長期（6ヶ月以降）**
- 実測データに基づく再評価
- 必要性が証明された場合のみ物理分離を検討
- 5.5の判断基準に基づく意思決定

---

## 6. 技術的補足資料

### 6.1. 廃止パッケージの詳細調査

**調査方法:**
```bash
# Packagist API での確認結果（2025年10月時点）
curl -s "https://packagist.org/search.json?q=laravel%20cross%20database" \
  | python3 -m json.tool
```

**調査結果:**
```json
{
  "name": "hoyvoy/laravel-cross-database-subqueries",
  "description": "Eloquent cross database compatibility in subqueries",
  "downloads": 131463,
  "favers": 94,
  "abandoned": true
}
```

**リスク分析:**
- 最終更新: 2020年頃（推定）
- Laravel 8-9 世代のパッケージ
- Laravel 12.0 での動作保証なし
- PHP 8.4 での動作保証なし

### 6.2. 現行システムの安定性指標

**2025年9月完了の実装実績:**
```
✅ tenant_id 方式の実装完了（2025年8月30日）
✅ BelongsToTenant トレイト適用（全テナントモデル）
✅ TenantAccessService による動的権限管理
✅ tenant_user テーブル廃止完了（2025年9月）
✅ 210個の全テストが通過（継続的に維持）
✅ Filament 管理画面でのマルチテナント対応完了
✅ Livewire コンポーネントのテナント対応完了
```

**採用技術:**
- `stancl/tenancy` v3.9（公式推奨の tenant_id 方式）
- Laravel 12.0
- PHP 8.4
- MySQL 8.0 + Mroonga

### 6.3. パフォーマンス監視実装例

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    if (app()->environment('production')) {
        DB::listen(function ($query) {
            if ($query->time > 1000) {
                Log::channel('performance')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'tenant_id' => tenant()?->id,
                    'url' => request()->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }
}
```

**監視すべき指標:**
- クエリ実行時間（目標: 95パーセンタイルで500ms以下）
- テナント毎のクエリ数
- Mroonga全文検索の応答時間
- データベース接続プールの使用率

### 6.4. 参考資料

**公式ドキュメント:**
- stancl/tenancy: https://tenancyforlaravel.com/docs/v3/
- Laravel Scout: https://laravel.com/docs/11.x/scout
- MySQL パーティショニング: https://dev.mysql.com/doc/refman/8.0/en/partitioning.html
- Meilisearch: https://www.meilisearch.com/docs

**関連ドキュメント:**
- `docs/work/2025-08-27_multi-tenant-architecture-re-evaluation.md` - マルチテナント再評価
- `docs/work/2025-08-30_new-multi-tenant-implementation-plan-final.md` - 実装計画書
- `docs/work/2025-09-07_issue-resolution-strategy.md` - 課題解決戦略

---

## 7. 結論

**【本ドキュメントの最終結論】**

2025年10月の再調査により、当初提案した「物理DB分離（ハイブリッド構成）」は、以下の理由から**実施を見送る**ことを決定した。

**見送り理由:**
1. 依存パッケージの廃止状態による長期リスク
2. 改修規模の大幅な過小評価（数百箇所→数十箇所の誤り）
3. 現行アーキテクチャの高い安定性と実績（210テスト全通過）
4. より効果的な代替案（Meilisearch等）の存在
5. パフォーマンス問題の未顕在化（測定データなし）
6. 旧システムの構成が現在の最適解とは限らない

**採用方針:**
- **基本方針:** 案A（現行アーキテクチャの継続+最適化）
- **実施事項:** パフォーマンス監視、MySQLパーティショニング、Meilisearch PoC
- **判断基準:** 5.5節の条件が全て満たされた場合のみ再検討

**教訓:**
- 実装着手前の妥当性検証の重要性
- 外部パッケージの保守状況確認の必須性
- 実測データに基づく意思決定の原則
- 既存システムの成功事例が現在の最適解とは限らない

**次のアクション:**
1. パフォーマンス監視の実装（1週間以内）
2. 監視データの収集と分析（1-2ヶ月）
3. 必要に応じた段階的な最適化
4. 定期的な再評価（四半期毎）
