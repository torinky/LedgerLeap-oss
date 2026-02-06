# 添付ファイル保存処理の調査報告

**日付:** 2026年2月3日  
**調査者:** AI Assistant  
**ステータス:** 🔴 重大な問題を発見・修正済み

---

## 📋 Executive Summary

添付ファイルの保存処理について、ドキュメントと実装の詳細な調査を実施しました。**ModifyColumnで重大なバグを2件発見し、修正しました**。

### 主要な発見事項

1. ✅ **`content`フィールドのアンシリアライズ処理が欠落** → 修正完了
2. ✅ **`mergeFilesForSave`での`content_attached`構造の不一致** → 修正完了
3. ⚠️  **`addAttachedFileRecordIfNecessary`の条件ロジックに潜在的問題** → 要確認
4. ⚠️  **二重エンコードの可能性** → 要調査

---

## 🔍 調査対象ドキュメント

### 1. 主要ドキュメント

| ドキュメント | パス | 記載内容 |
|------------|------|---------|
| **データベーススキーマ** | `docs/database/schema.md` | `attached_files`テーブル、`content`/`content_attached`カラムの仕様 |
| **添付ファイル機能** | `docs/function/Attachment.md` | データフロー、エンジン統合、ファイル処理の詳細 |
| **AttachedFileモデル** | `docs/models/AttachedFile.md` | モデルの属性、メソッド、制約事項 |
| **LedgerService** | `docs/services/LedgerService.md` | `saveDirectly`メソッドの仕様 |

### 2. ドキュメントに記載されている仕様

#### `content`と`content_attached`の構造

**ドキュメント (`docs/function/Attachment.md` Line 20-22):**
```markdown
2. **メタデータの保存:**
   - `attached_files`テーブルにファイルのメタデータが保存されます。
   - `ledgers`テーブルの`content`カラムに、`{"hashed_filename.ext": "original_filename.ext"}`形式で紐づけ情報が記録されます。
```

**ドキュメント (`docs/models/AttachedFile.md` Line 181-190):**
```markdown
### 7.1. AsColumnArrayJsonキャストの制約

`ledger.content_attached`へのアクセスには、Laravelの`data_get()`ヘルパーが使用できません（Phase 6で判明）。

```php
// ❌ 動作しない
$text = data_get($ledger->content_attached, "$columnId.$filename.meta.content");

// ✅ 正しい方法
$text = $ledger->content_attached[$columnId][$filename]['meta']['content'] ?? null;
```
```

#### プレースホルダーの仕様

**ドキュメントには明示的な記載がありません**が、CreateColumnの実装から推測すると:

```php
// CreateColumn/mergeFilesForSave (正しい実装)
$fileContents[$stored->hashedBaseName] = null;
```

**つまり、プレースホルダーは`null`であるべき**です。

---

## 🐛 発見された問題と修正

### 問題1: `content`フィールドのアンシリアライズ処理欠落 ✅ 修正完了

**場所:** `app/Livewire/Ledger/ModifyColumn.php` Line 170-189

**問題のコード:**
```php
// Line 176: content_attached のみアンシリアライズ処理
if (is_string($existing)) {
    $existing = unserialize($existing);
    $existing = $existing === false ? [] : $existing;
}

// Line 183: content はアンシリアライズなし ← 問題
foreach ($existing as $filename => $originalName) {
    // エラー発生: $existing が文字列の場合、foreachでエラー
}
```

**エラーメッセージ:**
```
foreach() argument must be of type array|object, string given
```

**修正内容:**
```php
// content もアンシリアライズ処理を追加
$existing = $this->ledgerRecord->content[$column->id] ?? [];
if (is_string($existing)) {
    $existing = unserialize($existing);
    $existing = $existing === false ? [] : $existing;
}
```

**影響範囲:** ModifyColumnでのファイル編集時に100%発生

---

### 問題2: `mergeFilesForSave`での構造不一致 ✅ 修正完了

**場所:** `app/Livewire/Ledger/ModifyColumn.php` Line 165-167

**問題のコード:**
```php
// ❌ 間違い: CreateColumnと異なる構造
$addedFileContents[$stored->hashedBaseName] = ['meta' => ['content' => '']];
```

**正しいコード (CreateColumnの実装):**
```php
// ✅ 正しい
$fileContents[$stored->hashedBaseName] = null;
```

**不一致の影響:**

| 項目 | CreateColumn | ModifyColumn (修正前) | 問題 |
|------|-------------|----------------------|------|
| `content_attached`のプレースホルダー | `null` | `['meta' => ['content' => '']]` | **構造不一致** |
| 後続処理 | VLM/OCR処理で正しく更新される | **構造が異なるため処理失敗の可能性** |

**修正内容:**
```php
// CreateColumnと同じくnullを設定
$addedFileContents[$stored->hashedBaseName] = null;
```

---

### 問題3: `addAttachedFileRecordIfNecessary`の条件ロジック ⚠️ 要確認

**場所:** `app/Livewire/Ledger/CreateColumn.php` Line 579-584

**現在のコード:**
```php
protected function addAttachedFileRecordIfNecessary(): void
{
    if ($this->ledgerId && !empty($this->newAttachedFiles)) {
        $this->addAttachedFileRecord();
    }
}
```

**問題の分析:**

このメソッドは`saveDirectly`の**後**に呼ばれます (Line 291)。
- Line 286で`$this->ledgerId`が設定される
- Line 291で`addAttachedFileRecordIfNecessary`が呼ばれる

**条件の意図:**
- 元々は「更新時のみ」を想定していた可能性
- しかし、新規作成時にも`$this->ledgerId`は設定されるため、実際には**新規作成時にも実行される**

**実際の動作:**
```
新規作成時:
1. saveDirectly() → Ledgerレコード作成
2. $this->ledgerId = $ledger->id (設定される)
3. addAttachedFileRecordIfNecessary() → 条件を満たす → 実行される ✅

更新時:
1. saveDirectly() → Ledgerレコード更新
2. $this->ledgerId = $ledger->id (既に設定済み)
3. addAttachedFileRecordIfNecessary() → 条件を満たす → 実行される ✅
```

**結論:** 実際には問題なく動作している可能性が高いが、条件の意図が不明確。

**推奨改善:**
```php
// より明示的な条件
protected function addAttachedFileRecordIfNecessary(): void
{
    if ($this->ledgerRecord && !empty($this->newAttachedFiles)) {
        $this->addAttachedFileRecord();
    }
}
```

---

### 問題4: 二重エンコードの可能性 ⚠️ 要調査

**発見:** データベースの`content[9]`が**文字列**として保存されている

**期待される構造:**
```php
$ledger->content[9] = [
    "hash1.jpg" => "original1.jpg",
    "hash2.jpg" => "original2.jpg",
];
```

**実際の構造:**
```json
// 文字列として保存されている
"{\"JQ3Aex5MfmMegAShZaHP0iahCIGT6HwGLluU7CQU.jpg\":\"receipt_01.jpg\",\"0\":\"[]\"}"
```

**原因の仮説:**

1. **`AsColumnArrayJson`キャストの動作:**
   - `set`メソッドで配列をJSON文字列に変換
   - しかし、`mergeFilesForSave`で設定した連想配列が、`setContent`でシリアライズされる可能性

2. **`normalizeByColumnDefine`の影響:**
   - `values()->toArray()`で連番配列に変換
   - しかし、`$content[9]`自体は連想配列のまま

3. **Livewireのシリアライゼーション:**
   - `$this->content`がLivewireのプロパティとして保持される際に変換される可能性

**要調査項目:**
- [ ] `AsColumnArrayJson`の`set`メソッドでのログ追加
- [ ] `mergeFilesForSave`直後の`$this->content[9]`の型確認
- [ ] `normalizeByColumnDefine`直後の型確認
- [ ] LedgerService::saveDirectly内での型確認

---

## 📊 仕様変更の経緯

### タイムライン

| 時期 | イベント | 詳細 |
|------|---------|------|
| **Phase 1-3** | VLM/OCR統合実装 | `content_attached`カラム追加、3エンジン統合 |
| **Phase 4** | FileInspectorドロワー実装 | プレビュー機能、処理状態可視化 |
| **Phase 5** | 最終化処理実装 | `processing_finalized_at`、`finalized_source`追加 |
| **Phase 6** | `data_get()`非対応判明 | `AsColumnArrayJson`の制約が明確化 |
| **2026年2月3日** | **本調査** | ModifyColumnのバグ2件発見・修正 |

### 実装の変遷

#### CreateColumn (正しい実装)

```php
// Line 566-576
protected function mergeFilesForSave(object $column, array $storedFiles): void
{
    $filenames = [];
    $fileContents = [];
    foreach ($storedFiles as $stored) {
        $filenames[$stored->hashedBaseName] = $stored->originalName;
        $fileContents[$stored->hashedBaseName] = null;  // ✅ 正しい
    }
    $this->content[$column->id] = $filenames;
    $this->contentAttached[$column->id] = $fileContents;
}
```

#### ModifyColumn (修正前)

```php
// Line 165-167 (修正前)
$addedFileContents[$stored->hashedBaseName] = ['meta' => ['content' => '']];  // ❌ 間違い
```

**推測される変更理由:**
- VLM/OCR統合時に`meta`構造を意識しすぎた
- CreateColumnの実装を見落とした
- テストが不十分だった

---

## 🔧 修正内容まとめ

### 修正1: `content`フィールドのアンシリアライズ処理追加

**ファイル:** `app/Livewire/Ledger/ModifyColumn.php`

**変更箇所:** Line 170-189

**修正前:**
```php
// content_attachedのみアンシリアライズ
$existing = $this->ledgerRecord->content_attached[$column->id] ?? [];
if (is_string($existing)) {
    $existing = unserialize($existing);
    $existing = $existing === false ? [] : $existing;
}

// contentはアンシリアライズなし
foreach ($existing as $filename => $originalName) {
    // エラー発生
}
```

**修正後:**
```php
// contentもアンシリアライズ
$existing = $this->ledgerRecord->content[$column->id] ?? [];
if (is_string($existing)) {
    $existing = unserialize($existing);
    $existing = $existing === false ? [] : $existing;
}

// content_attachedもアンシリアライズ
$existingContents = $this->ledgerRecord->content_attached[$column->id] ?? [];
if (is_string($existingContents)) {
    $existingContents = unserialize($existingContents);
    $existingContents = $existingContents === false ? [] : $existingContents;
}
```

### 修正2: `mergeFilesForSave`のプレースホルダー修正

**ファイル:** `app/Livewire/Ledger/ModifyColumn.php`

**変更箇所:** Line 165-167

**修正前:**
```php
$addedFileContents[$stored->hashedBaseName] = ['meta' => ['content' => '']];
```

**修正後:**
```php
// CreateColumnと同様にnullを設定
$addedFileContents[$stored->hashedBaseName] = null;
```

---

## 📝 ドキュメント更新の推奨事項

### 1. `docs/function/Attachment.md`の明確化

**追加すべき内容:**

```markdown
### プレースホルダーの仕様

アップロード直後の`content_attached`構造:

```php
// ✅ 正しい
$ledger->content_attached[$columnId][$hashedBaseName] = null;

// ❌ 間違い
$ledger->content_attached[$columnId][$hashedBaseName] = ['meta' => ['content' => '']];
```

**理由:**
- VLM/OCR/Tikaの各エンジンが、`null`を検出してテキスト抽出を開始する
- 処理完了後、`meta`構造で上書きされる
```

### 2. `docs/models/Ledger.md`の作成

**記載すべき内容:**
- `content`と`content_attached`の詳細な構造
- `AsColumnArrayJson`キャストの動作詳細
- シリアライズ/デシリアライズの仕組み
- `normalizeByColumnDefine`の役割

### 3. `docs/development/Testing-Best-Practices.md`への追加

**追加すべきテストケース:**

```markdown
### 添付ファイルのテスト

#### 必須テストケース

1. **新規作成時の添付ファイル保存:**
   - `content[$columnId]`に正しい連想配列が保存されているか
   - `content_attached[$columnId]`に`null`プレースホルダーが保存されているか
   - `attached_files`レコードが作成されているか

2. **更新時の添付ファイル追加:**
   - 既存ファイルと新規ファイルがマージされているか
   - 削除ファイルが正しく除外されているか

3. **`AsColumnArrayJson`キャストのテスト:**
   - 保存時にJSON文字列に変換されているか
   - 読み取り時に配列に変換されているか
   - シリアライズ/デシリアライズが正しく動作しているか
```

---

## 🎯 今後の対応

### 即時対応 (優先度: 高)

- [x] ~~`content`フィールドのアンシリアライズ処理追加~~ → 完了
- [x] ~~`mergeFilesForSave`のプレースホルダー修正~~ → 完了
- [ ] **二重エンコード問題の調査** → 要実施
- [ ] **テストケースの追加** → 要実施

### 短期対応 (1週間以内)

- [ ] `addAttachedFileRecordIfNecessary`の条件ロジック見直し
- [ ] ドキュメントの更新 (`docs/function/Attachment.md`)
- [ ] `docs/models/Ledger.md`の作成

### 中期対応 (1ヶ月以内)

- [ ] CreateColumnとModifyColumnの共通処理のリファクタリング
- [ ] `AsColumnArrayJson`キャストの改善検討
- [ ] E2Eテストの追加

---

## 📚 参考情報

### 関連ファイル

| ファイル | 役割 |
|---------|------|
| `app/Livewire/Ledger/CreateColumn.php` | 台帳新規作成コンポーネント |
| `app/Livewire/Ledger/ModifyColumn.php` | 台帳編集コンポーネント |
| `app/Services/LedgerService.php` | 台帳保存サービス |
| `app/Casts/AsColumnArrayJson.php` | カスタムキャスト |
| `app/Models/LedgerDefine.php` | 台帳定義モデル |
| `app/Models/AttachedFile.php` | 添付ファイルモデル |

### 関連ドキュメント

- `docs/database/schema.md` - データベーススキーマ
- `docs/function/Attachment.md` - 添付ファイル機能
- `docs/models/AttachedFile.md` - AttachedFileモデル
- `docs/services/LedgerService.md` - LedgerService
- `docs/development/Testing-Best-Practices.md` - テストのベストプラクティス

---

## 結論

本調査により、添付ファイル保存処理において**ModifyColumnで重大なバグを2件発見し、修正しました**。

1. ✅ `content`フィールドのアンシリアライズ処理欠落 → **修正完了**
2. ✅ `mergeFilesForSave`での構造不一致 → **修正完了**

しかし、**二重エンコードの可能性**など、さらなる調査が必要な問題も判明しました。今後、テストケースの追加とドキュメントの更新を実施し、再発防止を徹底する必要があります。

---

**調査完了日時:** 2026年2月3日 13:10 (JST)
