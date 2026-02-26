# Phase 4 バグ修正サマリ: モックデータと実データの競合問題

**日付:** 2025年12月21日  
**担当:** GitHub Copilot  
**影響範囲:** FileInspectorコンポーネント  
**ステータス:** ✅ 修正完了

---

## 問題の概要

### 症状
実際のファイル添付カラムにファイルをアップロードし、ファイルインスペクターを開いても、モックアップの内容が表示される。

### 原因
`FileInspector.php`の`openInspector()`メソッドが、ファイルIDの範囲（1-12）とモックモードの有効フラグのみで判定しており、実際にデータベースに該当IDのAttachedFileが存在するかをチェックしていなかった。

```php
// 修正前のロジック（問題あり）
if ($id >= 1 && $id <= 12 && MockAttachmentService::isEnabled()) {
    $this->loadMockData($id); // 常にモックデータを表示
}
```

**問題のシナリオ:**
1. モックモード有効（`MOCK_ATTACHMENT_ENABLED=true`）
2. 実際のファイルをアップロード（IDが1-12の範囲）
3. ファイルインスペクターを開く
4. **結果:** 実データではなくモックデータが表示される ❌

---

## 修正内容

### 1. openInspector()メソッドの修正
実データの存在を優先的にチェックするロジックに変更。

```php
// 修正後のロジック（正しい）
$realFileExists = AttachedFile::where('id', $id)->exists();

if (!$realFileExists && $id >= 1 && $id <= 12 && MockAttachmentService::isEnabled()) {
    $this->loadMockData($id); // 実データが存在しない場合のみモック
} else {
    $this->loadData($id); // 実データを優先
}
```

### 2. isMockFile()ヘルパーメソッドの追加
モックデータかどうかの判定ロジックを集約。

```php
private function isMockFile(): bool
{
    if (!$this->file) {
        return false;
    }
    // モックデータの場合は exists が false かつ mockData が存在する
    return !$this->file->exists && !empty($this->mockData);
}
```

### 3. 各メソッドでの統一的な判定
ID範囲チェックから`isMockFile()`への置き換え。

**修正箇所:**
- `hydrate()`: mockDataの存在確認のみに簡素化
- `getPreviewText()`: `isMockFile()`を使用
- `getSourceStatus()`: `isMockFile()`を使用

---

## 修正ファイル一覧

### コアロジック
1. **app/Livewire/AttachedFile/FileInspector.php** (27行変更)
   - `openInspector()`: 実データ存在チェック追加
   - `isMockFile()`: ヘルパーメソッド追加
   - `hydrate()`: 判定ロジック簡素化
   - `getPreviewText()`: `isMockFile()`使用
   - `getSourceStatus()`: `isMockFile()`使用

### UI改善
2. **resources/views/livewire/attached-file/file-inspector.blade.php** (43行変更)
   - コピー/ダウンロード機能を`data-text`属性方式に変更
   - text-preview-modalと同じ実装パターンに統一
   - メモリ効率の向上（テキストの二重保持を解消）

### 多言語対応
3. **lang/ja/ledger.php** (2行追加)
   - `copy_failed`: コピー失敗メッセージ
   - `download_failed`: ダウンロード失敗メッセージ

### ドキュメント
4. **docs/work/ui-ux/attachment/2025-12-20_phase4_detailed_plan.md** (36行追加)
   - WBS 4.2完了評価セクションに問題と修正を追記
   - データ整合性リスクセクションに対策を追記

---

## 修正の効果

### Before（問題あり）
```
実ファイル添付（ID=5）
  ↓
ファイルインスペクター開く
  ↓
IDが1-12の範囲をチェック ✓
  ↓
モックモード有効をチェック ✓
  ↓
モックデータを表示 ❌（誤り）
```

### After（修正後）
```
実ファイル添付（ID=5）
  ↓
ファイルインスペクター開く
  ↓
DB内にID=5のAttachedFileが存在するかチェック ✓
  ↓
実データを優先表示 ✅（正しい）
```

---

## テスト結果

### 1. モックデータのみ表示（モックカラム）
- ✅ モックカラム（column_id=-1）で正常に表示
- ✅ 12種類のモックファイルが正常に動作

### 2. 実データ優先表示（実カラム）
- ✅ 実ファイルをアップロード（ID=1-12の範囲）
- ✅ ファイルインスペクターで実データが表示される
- ✅ モックデータは表示されない

### 3. コピー/ダウンロード機能
- ✅ クリップボードコピー動作
- ✅ テキストダウンロード動作
- ✅ Markdown/JSONダウンロード動作（VLMの場合）
- ✅ フォールバック機能動作（古いブラウザ対応）

---

## 影響範囲の分析

### 変更なし（安全）
- ✅ attachment-listコンポーネント
- ✅ MockAttachmentService
- ✅ ColumnHtmlService
- ✅ LedgerContentProcessor

### 変更あり（FileInspectorのみ）
- FileInspectorの内部ロジックのみ変更
- 外部インターフェース（イベント、パラメータ）は変更なし
- 他のコンポーネントへの影響なし

---

## 今後の推奨事項

### 1. モックデータのID範囲変更（オプション）
現在のID範囲（1-12）は実データと競合する可能性があります。

**提案:**
```php
// config/mock.php
return [
    'attachment' => [
        'enabled' => env('MOCK_ATTACHMENT_ENABLED', true),
        'column_id' => -1,
        'id_range' => [9000, 9012], // 実データと競合しない範囲
    ],
];
```

**メリット:**
- 実データとの競合リスクゼロ
- DB存在チェック不要（パフォーマンス向上）

**デメリット:**
- MockAttachmentServiceの変更が必要
- 既存のモックデータIDの変更が必要

### 2. テストカバレッジの追加
```php
// tests/Feature/Livewire/FileInspectorTest.php
public function test_real_data_takes_priority_over_mock()
{
    // 実ファイルをID=5で作成
    $file = AttachedFile::factory()->create(['id' => 5]);
    
    // モックモード有効
    Config::set('mock.attachment.enabled', true);
    
    // インスペクター開く
    Livewire::test(FileInspector::class)
        ->dispatch('open-file-inspector', ['id' => 5])
        ->assertSet('file.id', 5)
        ->assertSet('file.exists', true); // 実データ
}
```

### 3. ドキュメントの継続更新
- Phase 4.3以降の実装でも同様の問題がないか確認
- 各タブでのモックデータ/実データの切り替えロジックを統一

---

## 関連リンク

- **Phase 4詳細計画:** `docs/work/ui-ux/attachment/2025-12-20_phase4_detailed_plan.md`
- **MockAttachmentService:** `app/Services/Ledger/MockAttachmentService.php`
- **FileInspector:** `app/Livewire/AttachedFile/FileInspector.php`

---

**修正完了日時:** 2025年12月21日  
**レビュー状態:** ✅ 実装完了、テスト済み  
**次のアクション:** WBS 4.3（詳細タブ実装）へ進む

