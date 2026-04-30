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

**決定が必要な事項:**
- [ ] **略称カラムの型と制約**: `string(50)` または `string(100)`？`nullable` で良いか？
- [ ] **既存データの扱い**: 既存組織・ロールの略称は初期値を `name` / `description` で一括設定するか、手動入力を促すか？
- [ ] **Rolesテーブルの表示名**: `roles.name` は Spatie Permission の内部識別子（`admin`, `user` など）で、`roles.description` が日本語表示名。スタンプには `abbreviation ?? description` を使用する方針で良いか？

### 1.2 `confidentiality_scopes` の保存形式

**現状:**
- 仕様書では `{"org_ids":[1,2],"role_ids":[3,4]}` のJSONオブジェクト形式を想定

**決定が必要な事項:**
- [ ] **JSON構造の確定**: 上記形式で確定するか、配列にフラット化（`["org:1","org:2","role:3"]`）するか？
  - オブジェクト形式のメリット: `org_ids` / `role_ids` の区別が明確
  - フラット形式のメリット: 単純な配列castで扱いやすい
- [ ] **バリデーション**: `exists:organizations,id` と `exists:roles,id` で個別にチェックする方針で良いか？

### 1.3 マイグレーションの適用順序

**決定が必要な事項:**
- [ ] **略称カラム追加と秘密区分カラム追加の順序**: 略称カラムを先に適用し、データ投入後に秘密区分機能をリリースするか、同時に行うか？
- [ ] **既存データの互換性**: `confidentiality_level` / `confidentiality_scopes` は `nullable` だが、既存のFolder・LedgerDefineレコードに対してデフォルト値を設定する必要はないか？

---

## 2. UI・フォーム関連

### 2.1 LedgerDefine/Create時の秘密区分設定

**現状:**
- `LedgerDefine/Create.php` は `title` と `folder_id` のみを保存し、**即座に Edit 画面へリダイレクト**する
- Create時に入力した内容は、Edit画面ですべて再設定可能

**決定が必要な事項:**
- [ ] **Create時に秘密区分・公開範囲を設定するか**: 
  - **A. Createでは設定せず、Editでのみ設定**（シンプル。Createの責務を最小限に保つ）
  - **B. Createでも設定可能にする**（ユーザーの操作ステップを減らせるが、Createのリダイレクト直後にEditで同じ項目が出るため冗長）
  - **C. Create時に必須項目として設定**（現状の「作成→即編集」フローと整合性が取れない）
- [ ] **推奨**: **A（Editでのみ設定）** が現状のシステムフローと最も整合する

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

**決定が必要な事項:**
- [ ] **z-indexの調整**: スタンプ（`z-index: 50`）が、IndexManagerのsticky header（おそらく `z-index: 40` 程度）やdrawer（`z-index: 30` 程度）と競合しないか？実際のCSS変数を確認する必要がある
- [ ] **モバイル表示**: 右上固定スタンプがモバイル画面でコンテンツを遮蔽しないか？`top: 1rem; right: 1rem` はモバイルでも適切か？
- [ ] **未設定時の表示**: スタンプ非表示時、右上に空白ができるか、他のUI要素がズレるか？

---

## 3. ビジネスロジック・実装関連

### 3.1 Folderの親子関係取得（継承解決）

**現状:**
- Folderは `NestedSet`（`kalnoy/nestedset`）を使用
- 仕様書では `$folder->parent` で再帰的に遡ることを想定

**決定が必要な事項:**
- [ ] **親取得方法**: 
  - **A. `$folder->parent` を再帰的に辿る**（シンプルだが、NestedSetの場合 `parent` リレーションが正しく動作するか確認が必要）
  - **B. `$folder->ancestors()->reverse()` を使用**（NestedSetの正式な方法。ルートからのパスを一括取得できる）
- [ ] **推奨**: **B（`ancestors`）** がNestedSetの設計意図に沿い、無限ループのリスクも低い

### 3.2 キャッシュ設計

**現状:**
- `WritableFolderRepository` のキャッシュキーに**テナントIDが含まれていない**（既知の問題。copilot-instructions.mdで警告されている）
- 仕様書では `Cache::remember("confidentiality_scopes:{$tenantId}", ...)` を想定

**決定が必要な事項:**
- [ ] **キャッシュタグ**: 新規に `confidentiality` タグを作るか、既存の `tenant_access` タグを流用するか？
- [ ] **キャッシュクリアタイミング**: 
  - 組織・ロールが更新された際にキャッシュをクリアする必要がある
  - 既存の `Organization` / `Role` モデルのObserverにキャッシュクリア処理を追加するか？
- [ ] **テナントスコープの保証**: `tenant()?->id` がnullの場合のフォールバック（`'global'` など）をどうするか？

### 3.3 設定ファイルの翻訳キー

**現状:**
- 仕様書の `config/confidentiality.php` では `__('ledger.confidentiality.level.public')` を使用

**決定が必要な事項:**
- [ ] **`config:cache` 時の動作**: 設定ファイルで `__()` を呼ぶと、`config:cache` 実行時のロケールで翻訳が固定される可能性がある
  - **対策A**: 設定ファイルでは翻訳キー文字列（`'ledger.confidentiality.level.public'`）のみを定義し、表示時に `__()` を適用する
  - **対策B**: 設定ファイルでは日本語ラベルを直接記載し、多言語対応はPhase 2で検討する
- [ ] **推奨**: **A（翻訳キー文字列のみを設定ファイルに記載）**

### 3.4 Serviceの責務分離

**現状:**
- 仕様書では `ConfidentialityLevelService` が設定ファイルアクセス、DBアクセス、解決ロジックをすべて担当

**決定が必要な事項:**
- [ ] **Serviceの分割**: 
  - **A. 単一Service**（シンプルだが、肥大化のリスク）
  - **B. `ConfidentialityLevelService`（設定ファイル・解決）+ `ConfidentialityScopeService`（DB・公開範囲）** に分割
- [ ] **推奨**: MVPでは **A（単一Service）** で開始し、Phase 2で分割を検討

---

## 4. 権限・セキュリティ関連

### 4.1 スタンプツールチップの「設定を変更」リンク権限

**現状:**
- 仕様書では `Gate::allows('edit', ...)` を想定
- FolderFormでは実際に `auth()->user()->can('update', $this->folder)` を使用

**決定が必要な事項:**
- [ ] **使用する権限チェック**: 
  - Folder: `auth()->user()->can('update', $folder)` 
  - LedgerDefine: `auth()->user()->can('update', $ledgerDefine)` 
- [ ] **LedgerDefineのポリシー確認**: `LedgerDefine` に対する `update` ポリシーが存在するか、存在しない場合は `Gate::define` で追加が必要

### 4.2 公開範囲のデータアクセス制御

**現状:**
- 公開範囲は「ラベル表現」であり、権限管理とは独立

**決定が必要な事項:**
- [ ] **公開範囲選択肢の表示範囲**: テナント配下の組織・ロールのみ表示するか、それともシステム全体の組織・ロールを表示するか？
  - `BelongsToTenant` が適用されていれば自動的にテナントスコープされるはずだが、確認が必要
- [ ] **削除済み組織・ロールの扱い**: SoftDeletesされている組織・ロールは選択肢に表示しない方針で良いか？

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

**決定が必要な事項:**
- [ ] **仕様書の修正**: 詳細仕様書 §6（スタンプコンポーネント）のルート名を `ledgerDefine.edit` に修正する
- [ ] **Folder編集URL**: `route('folder.edit', ['folder' => $id])` で正しいか確認（`tenant.php` で確認済み。`{folder}` パラメータ）

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

1. **【UI】LedgerDefine/Create時の秘密区分設定有無**（→ Createでは設定せず、Editのみにする方向で整合）
2. **【データモデル】`confidentiality_scopes` のJSON構造確定**（→ オブジェクト形式 `{"org_ids":[],"role_ids":[]}` で確定）
3. **【実装】Folder親子関係取得方法**（→ `ancestors()` を使用する方針で確定）
4. **【セキュリティ】公開範囲選択肢のテナントスコープ**（→ `BelongsToTenant` の自動スコープに依存する方針で確認）
5. **【UI】スタンプのz-indexとモバイル表示**（→ 実際のCSS変数を確認後に微調整）

---

## 9. 次のアクション

1. 上記チェックリストを関係者（バックエンド、フロントエンド、PO）とレビュー
2. 各項目に対して「決定済み」「要検討」「MVP後回し」のラベルを付与
3. 決定事項を基本仕様書・詳細仕様書に反映（Rev.6として更新）
4. 実装タスクへの分解と工数見積もり

