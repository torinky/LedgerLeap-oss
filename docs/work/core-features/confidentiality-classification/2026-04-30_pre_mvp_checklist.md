# 秘密区分表示機能 MVP着手前 決定事項チェックリスト

**作成日:** 2026-04-30
**対象:** 秘密区分表示機能 MVP開発着手前に関係者合意が必要な事項

> 本チェックリストは、仕様書（基本仕様書・詳細仕様書）と現状システムのコードベースを照らし合わせて、検討が不足している、または実装前に決定しておくべき事項を洗い出したものです。

---

## 1. データモデル・スキーマ関連

### 1.1 略称カラム（`abbreviation`）の追加方針

**現状:**
- `organizations` テーブル: `name`（正式名）あり、`abbreviation` なし
- `roles` テーブル: `description`（日本語表示名）あり、`abbreviation` なし

**決定済み（2026-05-01）:**
- [x] **略称カラムの型と制約**: `string(50), nullable` で確定（マイグレーション `2026_05_01_000001` で実装済み）
- [x] **既存データの扱い**: 既存データの略称は `null` のままとし、運用で都度入力する方針。MVPでは一括設定しない
- [x] **Rolesテーブルの表示名**: `abbreviation ?? description` を使用する方針で確定。`roles.name` は Spatie Permission 内部識別子のため表示に使用しない

### 1.2 `confidentiality_scopes` の保存形式

**現状:**
- 仕様書では `{"org_ids":[1,2],"role_ids":[3,4]}` のJSONオブジェクト形式を想定

**決定済み（2026-05-01）:**
- **保存形式**: オブジェクト形式 `{"org_ids":[1,2],"role_ids":[3,4]}` で確定
- **名前スナップショット**: 組織名・ロール名の変更に対応するため、保存時点の名前も JSON 内に保持
  - 構造例: `{"org_ids":[{"id":1,"name":"人事部"}],"role_ids":[{"id":3,"name":"管理者"}]}`
  - 名前が変更された場合、管理者へ更新通知 UI を表示（Sprint 3 以降で実装）
- **バリデーション**: `exists:organizations,id` と `exists:roles,id` で個別チェック

### 1.3 マイグレーションの適用順序

**決定済み（2026-05-01）:**
- [x] **略称カラム追加と秘密区分カラム追加の順序**: 略称カラム追加（`2026_05_01_000001`）→ 秘密区分カラム追加（`2026_05_01_000002`）の順序で確定
- [x] **既存データの互換性**: `confidentiality_level` / `confidentiality_scopes` は `nullable` とし、既存レコードにはデフォルト値を設定しない。`null` の場合はスタンプ非表示とする

---

## 2. UI・フォーム関連

### 2.1 LedgerDefine/Create時の秘密区分設定

**現状:**
- `LedgerDefine/Create.php` は `title` と `folder_id` のみを保存し、**即座に Edit 画面へリダイレクト**する
- Create時に入力した内容は、Edit画面ですべて再設定可能

**決定済み（2026-05-01）:**
- **LedgerDefine**: Create でも Edit でも秘密区分・公開範囲を設定可能。ただし Folder 側の設定に従うことがほとんどになる想定
- **Folder**: 上位階層の Folder で秘密区分・公開範囲が設定されていれば「継承」がデフォルト選択肢となる
- **フォールバック**: どの階層にも設定がない場合は「公開（public）」がデフォルト値
- **継承の仕組み**: `inherited` フラグを持ち、継承時は親の値を参照。上書き時は自身の値を使用

### 2.2 公開範囲の選択UI

**現状:**
- FolderFormでは `x-mary-choices-offline` を使用して `selectedInspectorRoleIds` / `selectedApproverRoleIds` を選択
- 公開範囲も同様に複数選択が必要

**決定が必要な事項:**
- [ ] **使用コンポーネント**: `x-mary-choices`（オンライン・検索可能） vs `x-mary-choices-offline`（オフライン・全件表示）
  - 組織・ロールの件数が多い場合（100件以上）は `choices`（検索付き）が適切
  - 件数が少ない場合は `choices-offline` で十分
- [ ] **選択肢のグルーピング**: 「組織」「ロール」でグループ分けして表示するか、フラットリストにするか？
  - MaryUIの `choices` は `optgroup` 的なグルーピングに対応しているか確認が必要
- [ ] **表示項目**: 略称があれば略称を優先表示し、ツールチップで正式名を表示するか？

### 2.3 スタンプのレイアウト配置

**現状:**
- 仕様書では `layouts/app.blade.php` の右上に固定配置
- IndexManagerはsticky header、drawer、タブなど複雑なUI

**決定済み（2026-05-01）:**
- [x] **z-indexの調整**: `z-[55]` で確定（navbar dropdown `z-[30]` より上、sticky announcement `z-50` と垂直方向に重なるためクリック可能にするため `z-[55]` を設定）
- [x] **モバイル表示**: `fixed top-16 right-4` で確定。375px〜768px でコンテンツを過度に遮蔽しないことをブラウザで確認済み
- [x] **未設定時の表示**: スタンプ非表示時（`level` が `null`）は何も描画しないため、右上に空白は生じない

---

## 3. ビジネスロジック・実装関連

### 3.1 Folderの親子関係取得（継承解決）

**現状:**
- Folderは `NestedSet`（`kalnoy/nestedset`）を使用
- 仕様書では `$folder->parent` で再帰的に遡ることを想定

**決定済み（2026-05-01）:**
- **親取得方法**: `$folder->ancestors()->reverse()` を使用（NestedSet の正式な方法）
- **理由**: 権限の継承処理と同じパターンを使用することで、エッジケースの違いを減らせる
- **実装方針**: `WritableFolderRepository` と同様の継承パターンに合わせて実装

### 3.2 キャッシュ設計

**現状:**
- `WritableFolderRepository` のキャッシュキーに**テナントIDが含まれていない**（既知の問題。copilot-instructions.mdで警告されている）
- 仕様書では `Cache::remember("confidentiality_scopes:{$tenantId}", ...)` を想定

**決定済み（2026-05-01）:**
- [x] **キャッシュタグ**: `['confidentiality', 'tenant_access']` を使用。`tenant_access` は既存キャッシュと同じクリアタイミングで扱える
- [x] **キャッシュクリアタイミング**: MVP では手動キャッシュクリア（`php artisan cache:clear`）で運用。自動クリアは Sprint 4 または Phase 2 で検討
- [x] **テナントスコープの保証**: キャッシュキーに `tenant()?->id ?? 'global'` を含める。`null` の場合は `'global'` でフォールバック

> **⚠️ Sprint 1 発見**: `Organization` モデルに `BelongsToTenant` trait が**適用されていない**。`Role` も同様。したがって公開範囲選択肢のテナントスコープは自動的には行われない。Sprint 2-5 で手動スコープの必要性を再確認すること。

**エビデンス（Sprint 2-5）:**
- キャッシュキー形式: `"confidentiality:{$tenantId}:scopes"`（Service コードで確認済み）
- キャッシュドライバー: Redis（`CACHE_DRIVER=redis`）→ `Cache::tags()` 完全対応
- `Cache::tags(['confidentiality'])->flush()` でキャッシュクリアできることを tinker で検証済み
- ただし、Organization/Role 更新時の自動キャッシュクリアは未実装
  - Role の `booted()` は `WritableFolderRepository::clearAllCache()` を呼ぶが、`ConfidentialityLevelService` のキャッシュまではクリアしない
  - Organization の `booted()` は存在しない

### 3.3 設定ファイルの翻訳キー

**現状:**
- 仕様書の `config/confidentiality.php` では `__('ledger.confidentiality.level.public')` を使用

**決定済み（2026-05-01）:**
- [x] **`config:cache` 時の動作**: **対策A（翻訳キー文字列のみを設定ファイルに記載）** で確定
  - 設定ファイルでは翻訳キー文字列（`'ledger.confidentiality.level.public'`）のみを定義し、表示時に `__()` を適用する
  - これにより `config:cache` 実行時のロケール固定問題を回避
- [x] **構造例**:
  ```php
  'levels' => [
      'public'    => ['label_key' => 'ledger.confidentiality.level.public',    'color' => 'success'],
      'internal'  => ['label_key' => 'ledger.confidentiality.level.internal',  'color' => 'info'],
      'confidential' => ['label_key' => 'ledger.confidentiality.level.confidential', 'color' => 'warning'],
      'secret'    => ['label_key' => 'ledger.confidentiality.level.secret',    'color' => 'error'],
  ],
  ```

### 3.4 Serviceの責務分離

**現状:**
- 仕様書では `ConfidentialityLevelService` が設定ファイルアクセス、DBアクセス、解決ロジックをすべて担当

**決定済み（2026-05-01）:**
- [x] **Serviceの分割**: MVPでは **A（単一Service）** で確定
  - `ConfidentialityLevelService` に以下の責務を集約：
    - `getLevelDefinition(string $levelKey, ?int $tenantId = null): array` — 設定ファイルから定義を取得
    - `resolveLevel(Folder|LedgerDefine $model): array` — DB 値を解決（継承含む）
    - `resolveScopes(array $scopeData, ?int $tenantId = null): array` — 公開範囲を読みやすい配列に変換
    - `getEffectiveLevel(Folder|LedgerDefine $model): array` — 継承を解決した最終的なレベル定義
  - Phase 2 で肥大化が顕著になった場合に分割を検討

---

## 4. 権限・セキュリティ関連

### 4.1 スタンプツールチップの「設定を変更」リンク権限

**現状:**
- 仕様書では `Gate::allows('edit', ...)` を想定
- FolderFormでは実際に `auth()->user()->can('update', $this->folder)` を使用

**決定済み（2026-05-01）:**
- [x] **使用する権限チェック**:
  - Folder: `auth()->user()->can('update', $folder)`
  - LedgerDefine: `auth()->user()->can('update', $ledgerDefine)`
- [x] **LedgerDefineのポリシー確認**: `App\Policies\LedgerDefinePolicy` に `update` メソッドが存在することを確認済み。追加の `Gate::define` は不要

### 4.2 公開範囲のデータアクセス制御

**現状:**
- 公開範囲は「ラベル表現」であり、権限管理とは独立

**決定済み（2026-05-01）:**
- [x] **公開範囲選択肢の表示範囲**: システム全体の組織・ロールを表示する方針で確定
  - `Organization`・`Role` モデルには `BelongsToTenant` trait が**適用されていない**（Sprint 1 コード調査で確認済み）
  - したがって自動的なテナントスコープは発生せず、全件が選択肢に表示される
  - MVP ではこれを受け入れ、Phase 2 でテナントスコープが必要な場合は別途検討
- [x] **削除済み組織・ロールの扱い**: SoftDeletes されている組織・ロールは選択肢に**表示しない**方針で確定

**エビデンス（Sprint 2-5）:**
```
Organization uses BelongsToTenant: no
Role uses BelongsToTenant: no
Folder uses BelongsToTenant: yes
```
- `organizations` テーブルに `tenant_id` カラムなし
- `roles` テーブルに `tenant_id` カラムなし

---

## 5. 活動ログ・監査関連

### 5.1 JSONカラムの変更ログ

**現状:**
- `LogsActivity` trait がFolder・LedgerDefineに適用済み
- `confidentiality_level`（string）は活動ログで読みやすいが、`confidentiality_scopes`（JSON）は差分表示が読みにくい

**決定が必要な事項:**
- [ ] **カスタムログメッセージ**: `LogsActivity` のデフォルト動作ではJSONの差分が生のまま記録される。人間が読める形式（「公開範囲：人事部、経営層を追加」など）でログを残すカスタム実装が必要か？
- [ ] **推奨**: MVPではデフォルトの `LogsActivity` 動作（JSON差分をそのまま記録）で開始し、運用フィードバックを受けてカスタムメッセージ化を検討

---

## 6. ルーティング・URL関連

### 6.1 ルート名の不一致

**現状:**
- 仕様書: `route('ledger-define.edit')`
- 実際: `route('ledgerDefine.edit')` （`LedgerDefine/Create.php` で確認済み）

**決定済み（2026-05-01）:**
- [x] **仕様書の修正**: 詳細仕様書 §6（スタンプコンポーネント）のルート名を `ledgerDefine.edit` に修正済み（Sprint 1 でスタンプコンポーネント実装時に反映）
- [x] **Folder編集URL**: `route('folder.edit', ['folder' => $id])` で確定（`routes/tenant.php` で確認済み。`{folder}` パラメータ）

---

## 7. テスト関連

### 7.1 必須テストの追加

**現状:**
- 仕様書では4つのテストファイルを想定

**決定が必要な事項:**
- [ ] **公開範囲DBベース化に伴うテスト追加**: 
  - `Organization::factory()->create(['abbreviation' => '人事'])` などのセットアップが必要
  - `Role::factory()->create(['description' => '管理者', 'abbreviation' => '管'])` などのセットアップが必要
- [ ] **テスト用キャッシュ**: `Cache::flush()` のタイミング。各テストでキャッシュが残らないように `setUp()` でクリアする必要がある

---

## 8. 優先度の高い決定事項（Top 5）

MVP着手をブロックする可能性が高い、最優先で決定すべき事項：

1. **【UI】LedgerDefine/Create時の秘密区分設定有無**（→ 決定済み：LedgerDefine は Create/Edit どちらでも設定可能。Folder は上位階層の継承がデフォルト、フォールバックは「公開」）
2. **【データモデル】`confidentiality_scopes` のJSON構造確定**（→ 決定済み：オブジェクト形式 + 保存時の名前スナップショットを含む）
3. **【実装】Folder親子関係取得方法**（→ 決定済み：`ancestors()->reverse()` を使用。権限継承と同じパターン）
4. **【セキュリティ】公開範囲選択肢のテナントスコープ**（→ **Sprint 1 発見**：`Organization`・`Role` に `BelongsToTenant` trait がないため、自動スコープは発生しない。MVPでは全件表示で確定）
5. **【UI】スタンプのz-indexとモバイル表示**（→ 決定済み：`z-[55]`、`top-16 right-4`、モバイル確認済み）

---

## 9. 次のアクション

1. 上記チェックリストを関係者（バックエンド、フロントエンド、PO）とレビュー
2. 各項目に対して「決定済み」「要検討」「MVP後回し」のラベルを付与
3. 決定事項を基本仕様書・詳細仕様書に反映（Rev.6として更新）
4. 実装タスクへの分解と工数見積もり

