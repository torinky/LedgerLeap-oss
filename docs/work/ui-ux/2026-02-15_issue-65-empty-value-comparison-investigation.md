# Issue #65: 空値比較問題の包括的調査と修正方針

## 概要
`LedgerDiffProcessor::prepareContentDiff` において、`json_encode()` による比較が `null` と空文字列 `''`、空配列 `[]` を区別してしまい、実質的に同じ「空」状態でも「変更あり」と誤判定される問題。

## 関連Issue
- GitHub Issue: https://github.com/torinky/LedgerLeap/issues/65
- 発見元: Issue #64 の手動動作確認時

---

## 現状分析

### 問題のあるコード (`LedgerDiffProcessor.php:150`)
```php
elseif (json_encode($currentValue) !== json_encode($oldValue)) {
    $status = 'modified';
}
```

### JSON エンコード動作の違い
| 値 | `json_encode()` の結果 | 備考 |
|---|---|---|
| `null` | `"null"` | PHP の null |
| `''` (空文字列) | `""` | 空の文字列 |
| `[]` (空配列) | `"[]"` | 空の配列 |
| `0` | `"0"` | 数値のゼロ |
| `false` | `"false"` | 真偽値 |

**問題**: `null` と `''` と `[]` が全て異なる文字列になるため、実質的に「空」でも「変更あり」と判定される。

---

## カラムタイプ別の空値パターン調査

### カラムタイプ一覧
`InputTypeFactory` で定義されているカラムタイプ:
1. `text` - TextType
2. `textarea` - TextareaType
3. `number` - NumberType
4. `auto_number` - AutoNumberType
5. `chk` - CheckboxType
6. `select` - SelectType
7. `YMD` / `YMDHM` - DateType
8. `files` - FilesType
9. `phone` - PhoneNumberType
10. `user_name` - UserNameType

### 空値の発生パターン

#### グループA: スカラー値型 (String)
**カラムタイプ**: `text`, `textarea`, `number`, `phone`, `user_name`, `YMD`, `YMDHM`

**空値のパターン**:
- `null` - データベースに保存されていない、または明示的に null
- `''` (空文字列) - Livewire で未入力のフィールドがバインドされた場合
- _(理論上)_ `0` - number型で0が入力された場合（これは空ではない）

**初期化**:
```php
// HandlesFormInitialization.php:26
default => '',
```
→ 新規作成時は `''` で初期化される

**想定される問題シナリオ**:
1. **Version 1**: 台帳作成、textarea未入力 → DB: `null` または `''`
2. **Version 2**: 他のカラムを編集、textareaは触らない → Livewire再バインド時に `''` になる可能性
3. **比較**: `json_encode(null)` ≠ `json_encode('')` → 誤判定

#### グループB: 配列値型 (Array)
**カラムタイプ**: `chk`, `select`, `files`

**空値のパターン**:
- `null` - データベースに保存されていない
- `[]` (空配列) - Livewire で未選択のフィールドがバインドされた場合
- `['']` - 部分的に入力されたが削除された場合

**初期化**:
```php
// HandlesFormInitialization.php:19
'files', 'chk' => [],
```
→ 新規作成時は `[]` で初期化される

**想定される問題シナリオ**:
1. **Version 1**: chk カラム未選択 → DB: `[]` または `null`
2. **Version 2**: 他のカラムを編集、chk は触らない → 再保存時に `[]` で統一される可能性
3. **比較**: `json_encode(null)` ≠ `json_encode([])` → 誤判定

#### グループC: 自動生成型
**カラムタイプ**: `auto_number`, `user_name`

**空値のパターン**:
- これらは通常、自動生成されるため空にはならない
- ただし、バリデーションエラーなどで保存失敗時は `''` になる可能性

---

## 実際のDB保存値の調査

### AsColumnArrayJson キャストの挙動
```php
// app/Casts/AsColumnArrayJson.php
public function set($model, $key, $value, $attributes)
{
    if ($value === null || $value === '') {
        return null; // null または空文字列は null として保存
    }
    
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}
```

### 確認事項
1. **スカラー値 (`text`, `textarea` 等)**: 
   - Livewire: `''` でバインド
   - DB保存: `AsColumnArrayJson` で `null` に変換される可能性
   
2. **配列値 (`chk`, `files` 等)**:
   - Livewire: `[]` でバインド
   - DB保存: `json_encode([])` → `"[]"` として保存

---

## 既存テストの状況

### `LedgerDiffProcessorTest.php`
現在のテストでは以下のケースをカバー:
- ✅ `it_returns_unchanged_status_when_content_is_identical` - 完全一致
- ✅ `it_identifies_modified_columns` - 明確な変更
- ✅ `it_identifies_added_columns` - カラム追加
- ✅ `it_identifies_deleted_columns` - カラム削除
- ✅ `it_identifies_changes_in_file_attachments` - ファイル変更

**不足しているテストケース**:
- ❌ `null` vs `''` の比較（スカラー値型）
- ❌ `null` vs `[]` の比較（配列値型）
- ❌ `''` vs `[]` の混在比較（タイプ変更時）
- ❌ `0` vs `''` vs `null` の比較（number型）

---

## 修正方針の検討

### Option A: 正規化関数による統一 ⭐ **推奨**

#### 実装
```php
/**
 * 空値を正規化する
 * スカラー型、配列型に関わらず、「空」と見なせる値は全て null に統一
 */
private function normalizeEmptyValue($value): mixed
{
    // null, 空文字列, 空配列は全て null に統一
    if ($value === null || $value === '' || $value === []) {
        return null;
    }
    
    // 配列の場合、中身が全て空文字列なら null に統一
    if (is_array($value)) {
        $filtered = array_filter($value, fn($v) => $v !== null && $v !== '');
        if (empty($filtered)) {
            return null;
        }
    }
    
    return $value;
}

// 比較ロジック
$normalizedCurrent = $this->normalizeEmptyValue($currentValue);
$normalizedOld = $this->normalizeEmptyValue($oldValue);

elseif (json_encode($normalizedCurrent) !== json_encode($normalizedOld)) {
    $status = 'modified';
}
```

#### メリット
- ✅ 全カラムタイプに対応
- ✅ 既存のロジックへの影響が最小限
- ✅ 「空」の定義が明確
- ✅ テストが書きやすい

#### デメリット
- ⚠️ 配列値のフィルタリング処理が追加される（パフォーマンスへの影響は微小）

---

### Option B: カラムタイプ別の比較ロジック

#### 実装
```php
private function areValuesEqual($currentValue, $oldValue, string $columnType): bool
{
    // スカラー型
    if (in_array($columnType, ['text', 'textarea', 'number', 'phone', 'user_name', 'YMD', 'YMDHM'])) {
        $normalizedCurrent = ($currentValue === null || $currentValue === '') ? null : $currentValue;
        $normalizedOld = ($oldValue === null || $oldValue === '') ? null : $oldValue;
        return $normalizedCurrent === $normalizedOld;
    }
    
    // 配列型
    if (in_array($columnType, ['chk', 'select', 'files'])) {
        $normalizedCurrent = (empty($currentValue)) ? [] : $currentValue;
        $normalizedOld = (empty($oldValue)) ? [] : $oldValue;
        return json_encode($normalizedCurrent) === json_encode($normalizedOld);
    }
    
    // デフォルト
    return json_encode($currentValue) === json_encode($oldValue);
}
```

#### メリット
- ✅ カラムタイプごとに最適な比較が可能
- ✅ 将来的な拡張性が高い

#### デメリット
- ❌ カラムタイプ情報を取得する必要がある（現在は `prepareContentDiff` に渡されていない）
- ❌ 複雑性が増す
- ❌ メンテナンスコストが高い

---

### Option C: 空値判定の強化

#### 実装
```php
private function isEmpty($value): bool
{
    return $value === null 
        || $value === '' 
        || $value === []
        || (is_array($value) && empty(array_filter($value, fn($v) => $v !== null && $v !== '')));
}

// 比較ロジック
if ($this->isEmpty($currentValue) && $this->isEmpty($oldValue)) {
    $status = 'unchanged';
} elseif (json_encode($currentValue) !== json_encode($oldValue)) {
    $status = 'modified';
}
```

#### メリット
- ✅ シンプルで読みやすい
- ✅ 空値判定が明確

#### デメリット
- ⚠️ 実際の値が異なっても両方「空」なら `unchanged` になる（これは意図通り）

---

## 推奨方針: **Option A (正規化関数)**

### 理由
1. **包括性**: 全カラムタイプに対応し、将来的な拡張にも対応しやすい
2. **シンプルさ**: ロジックが明確で、テストが書きやすい
3. **一貫性**: 「空」の定義が統一される
4. **パフォーマンス**: 正規化のコストは微小（数値比較のみ）

### 実装箇所
- `app/Services/Ledger/LedgerDiffProcessor.php`
  - `normalizeEmptyValue()` メソッドを追加
  - `prepareContentDiff()` メソッドの比較ロジックを修正 (150行目付近)

---

## テスト戦略

### 新規追加が必要なテストケース

#### 1. スカラー値型の空値比較
```php
#[Test]
public function it_treats_null_and_empty_string_as_unchanged_for_text_columns(): void
{
    $oldDiff = LedgerDiff::factory()->create([
        'content' => [null, 'Value 2'],  // Column 1 が null
    ]);
    
    $this->ledger->content = ['', 'Value 2'];  // Column 1 が空文字列
    
    $result = $this->processor->prepareContentDiff($this->ledger, $oldDiff);
    
    $this->assertFalse($result['hasChangedColumns']);
    $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
}
```

#### 2. 配列値型の空値比較
```php
#[Test]
public function it_treats_null_and_empty_array_as_unchanged_for_array_columns(): void
{
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 0, 'name' => 'Checkbox', 'type' => 'chk', 'order' => 1],
        ],
    ]);
    
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => [[]],  // 空配列
    ]);
    
    $oldDiff = LedgerDiff::factory()->create([
        'ledger_id' => $ledger->id,
        'content' => [null],  // null
        'column_define' => $ledgerDefine->column_define,
    ]);
    
    $result = $this->processor->prepareContentDiff($ledger, $oldDiff);
    
    $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
}
```

#### 3. 配列内の空要素フィルタリング
```php
#[Test]
public function it_treats_array_with_empty_strings_as_empty(): void
{
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 0, 'name' => 'Checkbox', 'type' => 'chk', 'order' => 1],
        ],
    ]);
    
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => [['', null]],  // 空文字列とnullのみの配列
    ]);
    
    $oldDiff = LedgerDiff::factory()->create([
        'ledger_id' => $ledger->id,
        'content' => [[]],  // 空配列
        'column_define' => $ledgerDefine->column_define,
    ]);
    
    $result = $this->processor->prepareContentDiff($ledger, $oldDiff);
    
    $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
}
```

#### 4. 数値ゼロは空ではない
```php
#[Test]
public function it_does_not_treat_zero_as_empty_for_number_columns(): void
{
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 0, 'name' => 'Amount', 'type' => 'number', 'order' => 1],
        ],
    ]);
    
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => [0],  // 数値のゼロ
    ]);
    
    $oldDiff = LedgerDiff::factory()->create([
        'ledger_id' => $ledger->id,
        'content' => [''],  // 空文字列
        'column_define' => $ledgerDefine->column_define,
    ]);
    
    $result = $this->processor->prepareContentDiff($ledger, $oldDiff);
    
    // 0 と '' は異なる値なので modified
    $this->assertEquals('modified', $result['contentChanges'][0]['status']);
}
```

#### 5. 実際の内容変更は検出される
```php
#[Test]
public function it_detects_actual_content_change_in_textarea(): void
{
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 0, 'name' => 'Textarea', 'type' => 'textarea', 'order' => 1],
        ],
    ]);
    
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => ['Some content'],
    ]);
    
    $oldDiff = LedgerDiff::factory()->create([
        'ledger_id' => $ledger->id,
        'content' => [''],  // 空文字列
        'column_define' => $ledgerDefine->column_define,
    ]);
    
    $result = $this->processor->prepareContentDiff($ledger, $oldDiff);
    
    $this->assertEquals('modified', $result['contentChanges'][0]['status']);
}
```

#### 6. ファイル型の空値
```php
#[Test]
public function it_treats_null_and_empty_array_as_unchanged_for_files(): void
{
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 0, 'name' => 'Files', 'type' => 'files', 'order' => 1],
        ],
    ]);
    
    $ledger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => [[]],  // 空配列
    ]);
    
    $oldDiff = LedgerDiff::factory()->create([
        'ledger_id' => $ledger->id,
        'content' => [null],  // null
        'column_define' => $ledgerDefine->column_define,
    ]);
    
    $result = $this->processor->prepareContentDiff($ledger, $oldDiff);
    
    $this->assertEquals('unchanged', $result['contentChanges'][0]['status']);
}
```

---

## 実装計画 (WBS)

### Phase 1: 調査・計画 ✅
- [x] Task 1.1: Issue #65 作成
- [x] Task 1.2: カラムタイプ別の空値パターン調査
- [x] Task 1.3: 修正方針の策定
- [x] Task 1.4: テスト戦略の策定
- [x] Task 1.5: このドキュメント作成

### Phase 2: 実装
#### Block 2.1: `LedgerDiffProcessor` 修正
- [ ] **Task 2.1.1**: `normalizeEmptyValue()` メソッド追加 (15分)
  - 依存: なし
  - 内容: 空値を null に統一する関数
  - 検証: 単体で動作確認

- [ ] **Task 2.1.2**: `prepareContentDiff()` の比較ロジック修正 (10分)
  - 依存: Task 2.1.1
  - 内容: 150行目の比較を正規化後の値で実施
  - 検証: 既存テストがパスすること

### Phase 3: テスト実装
#### Block 3.1: Unit テスト追加 (`LedgerDiffProcessorTest.php`)
- [ ] **Task 3.1.1**: スカラー値型空値比較テスト (20分)
  - `it_treats_null_and_empty_string_as_unchanged_for_text_columns`
  - `it_detects_actual_content_change_in_textarea`
  
- [ ] **Task 3.1.2**: 配列値型空値比較テスト (20分)
  - `it_treats_null_and_empty_array_as_unchanged_for_array_columns`
  - `it_treats_array_with_empty_strings_as_empty`
  
- [ ] **Task 3.1.3**: 数値ゼロのエッジケーステスト (15分)
  - `it_does_not_treat_zero_as_empty_for_number_columns`
  
- [ ] **Task 3.1.4**: ファイル型空値テスト (15分)
  - `it_treats_null_and_empty_array_as_unchanged_for_files`

#### Block 3.2: 既存テストの実行
- [ ] **Task 3.2.1**: 既存テスト全実行 (10分)
  - `LedgerDiffProcessorTest` (9テスト + 新規6テスト = 15テスト)
  - `LedgerDiffViewerTest` (10テスト)
  - リグレッションがないことを確認

### Phase 4: 統合テスト
#### Block 4.1: Feature テスト追加 (オプション)
- [ ] **Task 4.1.1**: 実際のLivewireフロー検証テスト (30分)
  - `CreateColumn` → 保存 → `ModifyColumn` → 未変更保存 → 履歴比較
  - 実際のユーザーフローで空値が正しく扱われることを確認

### Phase 5: コードレビュー・調整
- [ ] **Task 5.1**: Pint 実行 (5分)
- [ ] **Task 5.2**: エラーチェック (10分)
- [ ] **Task 5.3**: 手動動作確認 (20分)
  - textarea カラム空欄のケース
  - checkbox 未選択のケース
  - files 未添付のケース

### Phase 6: ドキュメント・報告
- [ ] **Task 6.1**: 実装完了報告を Issue #65 に追記 (15分)
- [ ] **Task 6.2**: このドキュメントの「実装結果」セクション更新 (10分)
- [ ] **Task 6.3**: コミット作成 (10分)
  - コミットメッセージ: `fix(ledger): 空値比較の正規化により誤判定を修正 #65`

---

## 総予想工数
- Phase 1: ✅ 完了
- Phase 2: 25分
- Phase 3: 80分
- Phase 4: 30分 (オプション)
- Phase 5: 35分
- Phase 6: 35分
- **合計: 約3時間25分** (Phase 4含む) / **約2時間55分** (Phase 4除く)

---

## リスク管理

### 高リスク
なし - 正規化は既存の比較ロジックに追加されるのみ

### 中リスク
- **リスク**: 数値の `0` や `false` が意図せず空と判定される
  - **対策**: `normalizeEmptyValue` で明示的に `0` と `false` は除外
  - **検証**: 専用テストケース追加

### 低リスク
- **リスク**: パフォーマンスへの影響
  - **対策**: 正規化はシンプルな条件分岐のみ
  - **検証**: 既存のパフォーマンステストで確認

---

## エッジケース検討

### 1. 数値の `0` (ゼロ)
```php
// ✅ 空ではない（金額0円など）
normalizeEmptyValue(0) → 0
normalizeEmptyValue('0') → '0'
```

### 2. 真偽値の `false`
```php
// ✅ 空ではない
normalizeEmptyValue(false) → false
```

### 3. 文字列の `"0"`
```php
// ✅ 空ではない
normalizeEmptyValue('0') → '0'
```

### 4. 配列の `[null, '']`
```php
// ✅ 空として扱う
normalizeEmptyValue([null, '']) → null
```

### 5. 配列の `[0]`
```php
// ✅ 空ではない
normalizeEmptyValue([0]) → [0]
```

---

## 実装後の期待される動作

### Before (修正前)
```
Version 1: textarea = null
Version 2: textarea = '' (未入力)
→ 比較: json_encode(null) ≠ json_encode('') → ❌ 変更あり
```

### After (修正後)
```
Version 1: textarea = null → 正規化後 null
Version 2: textarea = '' → 正規化後 null
→ 比較: json_encode(null) === json_encode(null) → ✅ 変更なし
```

### 実際の変更は検出される
```
Version 1: textarea = ''
Version 2: textarea = 'Some text'
→ 比較: json_encode(null) ≠ json_encode('Some text') → ✅ 変更あり
```

---

## 実装結果 (Phase 6で記入)

### 実装日時
2026-02-15

### 実装内容

#### 変更ファイル
1. **`app/Services/Ledger/LedgerDiffProcessor.php`**
   - `normalizeEmptyValue()` private メソッドを追加
   - `prepareContentDiff()` の比較ロジックを正規化後の値で実施するように修正

2. **`tests/Unit/Services/Ledger/LedgerDiffProcessorTest.php`**
   - 新規テスト6つを追加

#### 実装した正規化ロジック
```php
/**
 * 空値を正規化する
 * null, 空文字列, 空配列を全て null に統一することで、実質的に「空」である値を同一視する
 */
private function normalizeEmptyValue(mixed $value): mixed
{
    // null, 空文字列, 空配列は全て null に統一
    if ($value === null || $value === '' || $value === []) {
        return null;
    }

    // 配列の場合、中身が全て空（null または ''）なら null に統一
    if (is_array($value)) {
        $filtered = array_filter($value, fn ($v) => $v !== null && $v !== '');
        if (empty($filtered)) {
            return null;
        }
    }

    return $value;
}

// 比較ロジック
$normalizedCurrent = $this->normalizeEmptyValue($currentValue);
$normalizedOld = $this->normalizeEmptyValue($oldValue);

elseif (json_encode($normalizedCurrent) !== json_encode($normalizedOld)) {
    $status = 'modified';
}
```

#### empty ステータスの調整
比較対象がある場合は `empty` ステータスを設定せず、`unchanged` を維持するようにロジックを調整:
```php
if (! $oldColDef) {
    // 追加されたカラムで値が空の場合のみ empty として明示
    $status = ($normalizedCurrent === null) ? 'unchanged' : 'added';
    
    if ($status === 'unchanged' && $normalizedCurrent === null) {
        $status = 'empty';
    }
}
```

### テスト結果

#### 新規テスト (6つ追加、全てパス ✅)
1. ✅ `it_treats_null_and_empty_string_as_unchanged_for_text_columns` (1.62s)
   - text/textarea で `null` vs `''` → `unchanged`

2. ✅ `it_treats_null_and_empty_array_as_unchanged_for_array_columns` (2.11s)
   - chk/files で `null` vs `[]` → `unchanged`

3. ✅ `it_treats_array_with_empty_strings_as_empty` (1.68s)
   - `['', null]` vs `[]` → `unchanged`

4. ✅ `it_does_not_treat_zero_as_empty_for_number_columns` (1.69s)
   - number で `0` vs `''` → `modified` ⚠️ **重要なエッジケース**

5. ✅ `it_detects_actual_content_change_in_textarea` (1.70s)
   - `''` vs `'Some text'` → `modified` (正常動作確認)

6. ✅ `it_treats_null_and_empty_array_as_unchanged_for_files` (1.70s)
   - files で `null` vs `[]` → `unchanged`

#### 既存テスト (全てパス、リグレッションなし ✅)
- **LedgerDiffProcessorTest**: 15テスト, 53 assertions ✅
- **LedgerDiffViewerTest**: 10テスト, 44 assertions ✅
- **全Unit/Services/Ledger**: 27テスト, 86 assertions ✅
- **RollbackSchemaTest**: 1テスト, 1 assertion ✅ (identical content detection)

#### コード品質
- ✅ Pint実行: 自動フォーマット完了
- ✅ エラーチェック: 重大なエラーなし (既存の警告のみ)
- ✅ 後方互換性: 全既存テストがパス

### 実装時間
- Phase 2 (実装): 約20分
- Phase 3 (テスト): 約40分 (デバッグ含む)
- Phase 5 (レビュー): 約10分
- Phase 6 (報告): 約10分
- **合計: 約1時間20分** (予想2時間55分～3時間25分に対して大幅短縮)

### 動作検証

#### スカラー値型 (text, textarea, number 等)
- ✅ `null` と `''` は同じ「空」として扱われる
- ✅ `0` は空ではない有効な値として扱われる
- ✅ 実際の内容変更は正しく検出される

#### 配列値型 (chk, files 等)
- ✅ `null` と `[]` は同じ「空」として扱われる
- ✅ `['', null]` も空として扱われる
- ✅ `[0]` は空ではない有効な値として扱われる

#### エッジケース
- ✅ 数値 `0` vs 空文字列 `''` → modified (正しく区別)
- ✅ 配列 `[0]` は空ではない
- ✅ 真偽値 `false` は空ではない（テスト未実装だが、ロジック上対応済み）

### 技術的な特徴
1. **包括性**: 全10種類のカラムタイプに対応
2. **明確性**: `normalizeEmptyValue()` で「空」の定義を一箇所に集約
3. **後方互換性**: 既存の全テストがパス
4. **パフォーマンス**: 正規化は軽量な条件分岐のみ
5. **保守性**: private メソッドとして独立、テストも容易

### 解決された問題
- ✅ textarea 型カラムの空欄が「変更あり」と誤判定される問題
- ✅ checkbox 型カラムの未選択が「変更あり」と誤判定される可能性
- ✅ files 型カラムの未添付が「変更あり」と誤判定される可能性
- ✅ 配列内の空要素のみの場合の誤判定

### 備考
- 実装は予想よりスムーズに完了
- エッジケース（数値0、false等）も適切に処理されることを確認
- Issue #64 の実装と組み合わせることで、より直感的な差分表示が実現

---

## 参考リンク
- [GitHub Issue #65](https://github.com/torinky/LedgerLeap/issues/65)
- [GitHub Issue #64](https://github.com/torinky/LedgerLeap/issues/64)
- [Copilot Instructions](/.github/copilot-instructions.md)

