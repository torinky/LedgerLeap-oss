# 秘密区分表示機能 仕様書

**作成日:** 2026-04-29
**最終更新:** 2026-04-29 (Rev.2)
**対象機能:** フォルダ・台帳ごとの「秘密区分」表示機能
**方針:** MVPとして「表示・注意喚起」に留め、アクセス権限制御とは切り離す

---

## 0. 前提：用語の整理（⚠️ 重要）

LedgerLeap のコードベースでは「台帳」という言葉が2層に分かれる。仕様書全体でこの区別を厳守する。

| 用語 | コード上のクラス | テーブル | 説明 |
|------|----------------|---------|------|
| **台帳定義** | `LedgerDefine` | `ledger_defines` | 台帳のテンプレート（カラム定義・ワークフロー設定を含む）。フォルダに属する。 |
| **台帳レコード** | `Ledger` | `ledgers`（Mroonga） | 台帳定義に基づいて登録された個別データ行。ワークフローステータスを持つ。 |
| **フォルダ** | `Folder` | `folders` | 台帳定義を格納する階層構造のコンテナ。 |

### 🔑 設計判断：秘密区分を持つ層

> **MVPでは「台帳定義（LedgerDefine）」と「フォルダ（Folder）」に秘密区分を持たせる。**
> 台帳レコード（Ledger）単位への設定はPhase 2以降とする。

**判断根拠:**

| 視点 | 台帳定義（推奨） | 台帳レコード |
|------|---------------|------------|
| 業務実態 | 「採用評価台帳」全体が極秘→台帳定義単位が自然 | レコードごとに機密性が異なるケースは稀 |
| UX | 台帳定義設定時に1回設定 | レコード登録ごとに設定が必要（操作が多い） |
| 一覧表示 | 台帳定義ヘッダーカードで表示可能 | 全レコード行にバッジが並ぶ（視認性が低下） |
| ワークフロー | `isLocked()` 制約なし（台帳定義はロック概念がない） | 承認済みレコード（`APPROVED`）は `isLocked()=true` で変更不可 |
| Mroongaリスク | InnoDB テーブルへの通常カラム追加 | Mroonga テーブルへのカラム追加（要注意事項あり） |

> **⚠️ 確認事項（実装前に合意が必要）:** 個別レコードごとに機密性が異なるユースケース（例：特定の応募者データのみ極秘）が想定される場合は、Ledger レコード層への追加も検討する。その場合はPhase 2として仕様を別途策定する。

---

## 1. 機能階層

```
大機能：秘密区分表示機能
│
├─ 小機能1：マスタデータ管理
│   ├─ 1-1. 秘密区分の定義管理（Enumによるコード固定）
│   └─ 1-2. 区分ごとの視覚的スタイル定義
│
├─ 小機能2：フォルダへの秘密区分設定
│   ├─ 2-1. フォルダ作成時の秘密区分選択
│   ├─ 2-2. フォルダ編集時の秘密区分変更
│   └─ 2-3. フォルダツリーでの秘密区分バッジ表示
│
├─ 小機能3：台帳定義への秘密区分設定  ← Rev.2: 台帳レコードではなく台帳定義に変更
│   ├─ 3-1. 台帳定義作成時の秘密区分選択
│   ├─ 3-2. 台帳定義編集時の秘密区分変更
│   └─ 3-3. 台帳定義保存時の秘密区分永続化
│
├─ 小機能4：一覧・詳細画面での秘密区分表示
│   ├─ 4-1. 台帳一覧（台帳定義ヘッダーカード）でのバッジ表示
│   ├─ 4-2. 台帳レコード行（table-row）でのバッジ表示
│   └─ 4-3. 台帳レコード詳細画面（Show）でのバッジ表示
│
└─ 小機能5：運用補助・拡張（Phase 2以降検討）
    ├─ 5-1. 親フォルダとの秘密区分矛盾警告
    ├─ 5-2. 台帳レコード単位の秘密区分設定
    ├─ 5-3. 組織識別子との複合表示
    └─ 5-4. 転送・共有時の注意喚起ダイアログ
```

---

## 2. 各小機能の概要

### 小機能1：マスタデータ管理

システム固定のマスタとして、4段階の秘密区分を定義します。

| Enum値 | 表示ラベル | daisyUI クラス | Heroicon |
|--------|-----------|--------------|---------|
| `public` | 公開 | `badge-success` | `o-globe-alt` |
| `internal` | 社内制限 | `badge-info` | `o-building-office` |
| `confidential` | 社外秘 | `badge-warning` | `o-exclamation-triangle` |
| `strictly_confidential` | 極秘 | `badge-error` | `o-shield-exclamation` |

- **1-1. 秘密区分の定義管理**: `App\Enums\ConfidentialityLevel` として定義。管理画面からの動的追加はMVPでは対応しない。
- **1-2. 区分ごとの視覚的スタイル定義**: `WorkflowStatus` と同様のパターンで実装（`label()`, `colorClass()`, `heroicon()`, `description()` メソッドを持つ）。

### 小機能2：フォルダへの秘密区分設定

- **2-1. フォルダ作成時**: `Livewire\Folder\FolderForm` コンポーネントの `create` モードに `x-mary-select` を追加。未選択（`null`）を許可。
- **2-2. フォルダ編集時**: 同コンポーネントの `edit` モードで変更可能。`Folder` モデルに `LogsActivity` が適用済みのため、`$fillable` に追加するだけで変更ログが自動記録される。
- **2-3. フォルダツリー表示**: `resources/views/components/folder/tree.blade.php` の `.tree-row-left` 内、既存の `ledgerDefines->count()` バッジの右隣にバッジを追加。`whitespace-nowrap` 行なので `badge-xs` サイズを使用する。

### 小機能3：台帳定義への秘密区分設定

- **3-1. 台帳定義作成時**: `Livewire\LedgerDefine\Create` コンポーネントのフォームに `x-mary-select` を追加。デフォルトは `null`（未設定）。
- **3-2. 台帳定義編集時**: `Livewire\LedgerDefine\Edit` コンポーネントで変更可能。`LedgerDefine` モデルに `LogsActivity` が適用済みのため自動ログ記録される。
- **3-3. 台帳定義保存時の永続化**: `ledger_defines` テーブルに `confidentiality_level` カラムを追加（`InnoDB` テーブルのため通常の `Schema::table` マイグレーションで対応可能）。既存の `column_define` や検索インデックスとは完全に独立する。

### 小機能4：一覧・詳細画面での秘密区分表示

- **4-1. 台帳一覧（台帳定義ヘッダーカード）**: 台帳定義一覧セクションのヘッダーカード（`IndexManager` コンポーネント）に秘密区分バッジを表示。`$ledgerDefine->confidentiality_level` から取得。
- **4-2. 台帳レコード行（table-row）**: `resources/views/components/ledger/table-row.blade.php` の右端 `<td>` にある既存のバッジ表示エリア（ワークフローバッジと同じ位置）に追加。`$ledgerRecord->define->confidentiality_level` で台帳定義の秘密区分を参照する。
- **4-3. 台帳レコード詳細画面（Show）**: `Livewire\Ledger\Show` コンポーネントが参照する `ledgerRecord->define->confidentiality_level` をビュー（`livewire/ledger/show.blade.php`）のヘッダー部分に表示。

### 小機能5：運用補助・拡張（Phase 2以降検討）

MVPでは実装せず、運用実績を見ながら将来追加を検討する機能群です（詳細は初度検討ドキュメント参照）。

---

## 3. MVPスコープと非スコープ

### MVPで実装するもの（Phase 1）

- [x] 小機能1：`App\Enums\ConfidentialityLevel` の定義
- [x] 小機能2：フォルダへの秘密区分設定（FolderForm + tree.blade）
- [x] 小機能3：台帳定義への秘密区分設定（LedgerDefine Create/Edit）
- [x] 小機能4：一覧・詳細画面でのバッジ表示

### MVPで実装しないもの（Phase 2以降）

- [ ] 台帳レコード（Ledger）単位の秘密区分設定
- [ ] フォルダ→台帳定義の秘密区分自動継承
- [ ] 親子間の秘密区分矛盾バリデーション
- [ ] 組織識別子との複合バッジ
- [ ] 転送・共有時の注意喚起ダイアログ
- [ ] 秘密区分に基づくアクセス権限制御
- [ ] 秘密区分変更の承認ワークフロー
- [ ] MCP ツールへの秘密区分情報の公開
- [ ] CSVインポート時の秘密区分一括設定

---

## 4. 非機能要件・制約

### 4.1 基本方針

- **表示（注意喚起）機能として実装**: 既存の権限管理（Spatie Permission + FolderPermission）とは独立。
- **既存機能への影響最小化**: 全文検索（Mroonga）、ソート、フィルタ、ワークフロー、活動ログなどに影響を与えない設計。
- **既存データの互換性**: 既存のフォルダ・台帳定義には秘密区分が `null` のまま。未設定時は既存と同じ見た目を維持する。

### 4.2 デザイン・UI制約

- **daisyUIセマンティックカラーのみ使用**: `badge-success`, `badge-info`, `badge-warning`, `badge-error`。
- **MaryUIコンポーネントの優先使用**: セレクトボックスは `<x-mary-select>`。
- **翻訳キーの使用**: UIテキストはすべて `__('ledger.xxx')` 形式。自然言語の直書きは禁止。
- **フォルダツリーのバッジサイズ**: フォルダツリー内では `badge-xs` を使用（深い階層でも横幅を圧迫しない）。

### 4.3 テスト・品質

- **テスト実行環境**: `./vendor/bin/sail test` でのみ実行（ホスト側 `php artisan test` 禁止）。
- **データベーストレイト**: FTSを含まない場合は `RefreshDatabase`、Mroonga関連テストは `DatabaseMigrationsOnce` を使用。本機能は `ledger_defines`/`folders` の InnoDB テーブルのみ操作するため `RefreshDatabase` で可。
- **必須テスト**:
  - `tests/Unit/Enums/ConfidentialityLevelTest.php` — `label()`, `colorClass()`, `heroicon()`, `cases()` のユニットテスト
  - `tests/Feature/Livewire/Folder/FolderFormTest.php` — 秘密区分セレクトの保存・バリデーションテスト
  - `tests/Feature/Livewire/LedgerDefine/LedgerDefineFormTest.php` — 台帳定義フォームの同上
  - `tests/Feature/Livewire/Ledger/IndexManagerTest.php` — バッジレンダリングテスト
- **テナント初期化**: Featureテストの `setUp()` で必ず `tenancy()->initialize($tenant)` を呼び出す。

### 4.4 データモデル設計指針

#### 4.4.1 マイグレーション（追加カラム）

```php
// フォルダテーブル
Schema::table('folders', function (Blueprint $table) {
    $table->string('confidentiality_level')->nullable()->after('title');
});

// 台帳定義テーブル
Schema::table('ledger_defines', function (Blueprint $table) {
    $table->string('confidentiality_level')->nullable()->after('title');
});
```

- `ledgers` テーブル（Mroonga）への変更は**不要**（秘密区分は台帳定義層で管理）。
- `nullable()` を必須とし、既存データへの互換性を保証する。

#### 4.4.2 モデル変更

**`Folder` モデル** – `$fillable` に追加:
```php
protected $fillable = [
    'title', 'modifier_id', 'creator_id', 'parent_id', 'tenant_id',
    'confidentiality_level',  // ← 追加
];
protected $casts = [
    'confidentiality_level' => \App\Enums\ConfidentialityLevel::class,
];
```

**`LedgerDefine` モデル** – `$fillable` に追加:
```php
protected $fillable = [
    // ...existing...
    'confidentiality_level',  // ← 追加
];
protected $casts = [
    // ...existing...
    'confidentiality_level' => \App\Enums\ConfidentialityLevel::class,
];
```

- `LogsActivity` の `logFillable()` が既に適用済み。`$fillable` に追加するだけで変更ログが自動記録される。
- `confidentiality_level` が `null` の既存レコードはキャスト後も `null` となるため、ビュー側で `@if($folder->confidentiality_level)` 等で null チェックする。

### 4.5 Enum 設計指針

`App\Enums\ConfidentialityLevel` を `WorkflowStatus` と同じパターンで実装する。

```php
namespace App\Enums;

enum ConfidentialityLevel: string
{
    case PUBLIC              = 'public';
    case INTERNAL            = 'internal';
    case CONFIDENTIAL        = 'confidential';
    case STRICTLY_CONFIDENTIAL = 'strictly_confidential';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC               => __('ledger.confidentiality.level.public'),
            self::INTERNAL             => __('ledger.confidentiality.level.internal'),
            self::CONFIDENTIAL         => __('ledger.confidentiality.level.confidential'),
            self::STRICTLY_CONFIDENTIAL => __('ledger.confidentiality.level.strictly_confidential'),
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::PUBLIC               => 'badge-success',
            self::INTERNAL             => 'badge-info',
            self::CONFIDENTIAL         => 'badge-warning',
            self::STRICTLY_CONFIDENTIAL => 'badge-error',
        };
    }

    public function heroicon(): string
    {
        return match ($this) {
            self::PUBLIC               => 'o-globe-alt',
            self::INTERNAL             => 'o-building-office',
            self::CONFIDENTIAL         => 'o-exclamation-triangle',
            self::STRICTLY_CONFIDENTIAL => 'o-shield-exclamation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PUBLIC               => __('ledger.confidentiality.description.public'),
            self::INTERNAL             => __('ledger.confidentiality.description.internal'),
            self::CONFIDENTIAL         => __('ledger.confidentiality.description.confidential'),
            self::STRICTLY_CONFIDENTIAL => __('ledger.confidentiality.description.strictly_confidential'),
        };
    }

    /** セレクトボックス用オプション配列 */
    public static function selectOptions(): array
    {
        return collect(self::cases())->map(fn ($case) => [
            'id'    => $case->value,
            'name'  => $case->label(),
        ])->all();
    }
}
```

### 4.6 翻訳キー体系

`lang/ja/ledger/` に新ファイル `confidentiality.php` を追加する（既存ファイルと分割管理）。

```php
// lang/ja/ledger/confidentiality.php
return [
    'confidentiality' => [
        'level' => [
            'public'               => '公開',
            'internal'             => '社内制限',
            'confidential'         => '社外秘',
            'strictly_confidential' => '極秘',
        ],
        'description' => [
            'public'               => '制限なく公開可能な情報',
            'internal'             => '社内に限り共有可能な情報',
            'confidential'         => '社外への共有禁止の情報',
            'strictly_confidential' => '厳重に管理が必要な極秘情報',
        ],
        'form' => [
            'label'       => '秘密区分',
            'placeholder' => '秘密区分を選択（任意）',
            'helper'      => '未選択の場合はバッジが表示されません',
        ],
        'tooltip' => [
            'folder'  => 'このフォルダの秘密区分',
            'ledger'  => 'この台帳の秘密区分',
        ],
    ],
];
```

翻訳キー追加後は `artisan translations:compare --force` で同期する。

### 4.7 インポート機能との整合性

- 秘密区分は台帳定義（LedgerDefine）に保存するため、CSVインポート（`LedgerImport`）で作成される台帳レコード（Ledger）には直接影響しない。
- フォルダ/台帳定義のCSVインポート機能が将来追加される場合は、`confidentiality_level` カラムの対応を別途検討する。

### 4.8 MCP ツールへの影響範囲

- **MVPでは MCP ツールへの変更は行わない**。
- `GetLedgerDetailTool`, `SearchLedgersTool` 等が返すレスポンスへの秘密区分情報の追加は Phase 2 で検討。
- 理由: 秘密区分情報の MCP 経由公開はクライアント向け契約の変更を伴い、`client-facing-contract-maintenance` エージェントによる別途レビューが必要。

---

## 5. 実装コンポーネントマッピング

| 修正対象ファイル | 変更内容 |
|----------------|---------|
| `database/migrations/xxxx_add_confidentiality_level.php` | `folders`, `ledger_defines` へのカラム追加 |
| `app/Enums/ConfidentialityLevel.php` | 新規作成 |
| `app/Models/Folder.php` | `$fillable`, `$casts` に追加 |
| `app/Models/LedgerDefine.php` | `$fillable`, `$casts` に追加 |
| `app/Livewire/Folder/FolderForm.php` | Enum選択肢のロードと `confidentiality_level` の保存 |
| `app/Livewire/LedgerDefine/Create.php` | 同上 |
| `app/Livewire/LedgerDefine/Edit.php` | 同上 |
| `resources/views/livewire/folder/folder-form.blade.php` | `x-mary-select` の追加 |
| `resources/views/livewire/ledger-define/create.blade.php` | 同上 |
| `resources/views/livewire/ledger-define/edit.blade.php` | 同上 |
| `resources/views/components/folder/tree.blade.php` | バッジ追加（`badge-xs`） |
| `resources/views/components/ledger/table-row.blade.php` | 右端バッジ領域に追加（`$ledgerRecord->define->confidentiality_level`） |
| `resources/views/livewire/ledger/show.blade.php` | ヘッダー部分へのバッジ追加 |
| `lang/ja/ledger/confidentiality.php` | 新規作成 |

---

## 6. 関連ドキュメント

- [初度検討ドキュメント](./2026-04-29_initial_consideration.md) - ペルソナ・シナリオ・背景
- [設計ガイドライン](../../../.github/instructions/design.instructions.md) - UI/UX設計ルール
- [翻訳管理スキル](../../../.github/skills/translation/SKILL.md) - 翻訳キー管理方法
- [フォームレイアウトスキル](../../../.github/skills/form-layout/SKILL.md) - フォーム設計パターン
- [ワークフローステータス実装例](../../../app/Enums/WorkflowStatus.php) - Enumパターンの参考実装

---

## 7. 改訂履歴

| 日付 | 版 | 変更内容 |
|------|-----|---------|
| 2026-04-29 | 1.0 | 初版作成。MVPスコープを「表示・注意喚起機能」に絞り込み。 |
| 2026-04-29 | 2.0 | コードベース調査に基づきブラッシュアップ。台帳定義と台帳レコードの用語整理、設計判断（LedgerDefine層への秘密区分付与）の明記、データモデル設計指針、Enum設計指針、翻訳キー体系、インポート/MCP影響範囲、テスト要件の具体化、実装コンポーネントマッピング表を追加。 |
