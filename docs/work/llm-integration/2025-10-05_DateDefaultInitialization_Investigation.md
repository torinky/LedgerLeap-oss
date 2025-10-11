# DateDefaultInitializationTest 対応の調査と課題

**日付:** 2025-10-05  
**状態:** 調査完了・ロールバック実施  
**関連テスト:** `tests/Feature/Livewire/Ledger/DateDefaultInitializationTest.php`

## 問題の概要

`DateDefaultInitializationTest` テストで、`ModifyColumn` コンポーネントが日付カラムのデフォルト値を初期化する際に、`overwrite_existing=false` の設定が正しく動作せず、既存値が上書きされる不具合が発生していた。

## 根本原因の分析

### データ構造の設計

LedgerLeapシステムでは、`content` 配列に関して以下の設計がある：

1. **アプリケーション層でのアクセス**: `$content[$column->id]` のようにカラムIDをキーとして使用
2. **DB保存形式**: Mroongaの全文検索要件により、`array_values()` で0から始まるJSON配列として保存
3. **変換処理**: `AsColumnArrayJson::set()` メソッドが保存時に自動的に `array_values()` を適用

### 発見した問題点

#### 問題1: `normalizeByColumnDefine()` の挙動

`LedgerDefine::normalizeByColumnDefine()` メソッドは以下の処理を行う：

```php
public function normalizeByColumnDefine($content)
{
    $maxId = $this->getMaxColumnIdAttribute();
    $columnDefineKeyById = $this->getColumnDefineKeyByIdAttribute();
    
    $contentCollection = collect($content);
    
    // 欠番を埋める
    for ($i = 0; $i <= $maxId; $i++) {
        if (! $contentCollection->has($i)) {
            if ($columnDefineKeyById->has($i)) {
                $contentCollection[$i] = $columnDefineKeyById[$i]->type === 'chk' ? [] : '';
            }
        }
    }
    
    // キーで並び替え
    $sortedContentArray = $contentCollection->sortKeys();
    
    // 数字添字配列に作り直し
    return $sortedContentArray->values()->toArray();
}
```

**問題点**: 最後の `.values()` により、カラムIDをキーとする配列が0から始まるインデックス配列に変換される。

#### 問題2: データの流れの不整合

```
DB保存時:
  $content[1 => 'value'] 
  → AsColumnArrayJson::set() → array_values() 
  → [0 => 'value'] (JSON配列として保存)

DB読み込み時:
  JSON: ["value"]
  → AsColumnArrayJson::get()
  → [0 => 'value'] (0から始まる配列)

ModifyColumn::mount():
  $this->content = $this->ledgerRecord->content; // [0 => 'value']
  → initColumns() → normalizeByColumnDefine()
  → [0 => 'value'] (変わらず)
  
  しかし、コンポーネント内では:
  $this->content[$column->id] でアクセス
  → $column->id = 1 の場合、アクセス不可
```

### 試行した解決策と結果

#### 試行1: `normalizeByColumnDefine()` の `.values()` を削除

- **結果**: Mroongaの要件により、JSON配列（オブジェクトではなく）として保存する必要があるため不可
- **影響**: 他のテストが失敗

#### 試行2: `ModifyColumn::initColumns()` で変換処理を追加

```php
protected function initColumns(): void
{
    $normalized = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
    
    // カラムIDをキーとする配列に変換
    $columns = collect($this->ledgerDefineRecord->column_define)->values()->all();
    $this->content = [];
    foreach ($columns as $index => $column) {
        $this->content[$column->id] = $normalized[$index] ?? '';
    }
    // ...
}
```

- **結果**: `DateDefaultInitializationTest` は通過したが、他のテストが失敗
- **失敗したテスト**:
  - `ModifyColumnTenancyTest::it_updates_ledger_content_properly`
  - `ModifyColumnTest::it_handles_sparse_file_columns`

#### 試行3: `Ledger` モデルにアクセサを追加

```php
public function getContentAttribute($value)
{
    $content = $this->castAttribute('content', $value);
    
    if (!is_array($content) || !$this->relationLoaded('define')) {
        return $content;
    }
    
    $columns = collect($this->define->column_define)->values()->all();
    $result = [];
    foreach ($columns as $index => $column) {
        $result[$column->id] = $content[$index] ?? '';
    }
    
    return $result;
}
```

- **問題**: アクセサはDBから読み込む時にしか動作しない
- **課題**: `normalizeByColumnDefine()` の結果には適用されない

## 矛盾点の整理

### ユーザーからの情報

> 全ての処理でcontentには必ずcolumnIdでアクセスするようにしています。UI上では台帳編集、台帳定義ともに変更前の状態で破綻はしていませんでした。

### しかし実際には

1. DBに保存されている `content` は0から始まるインデックス配列
2. `Ledger::fresh()->content` で取得すると0から始まる配列が返る
3. テストでは `$ledger->content[1]` でアクセスしていた（失敗）

### 仮説

システムが正常に動作していたということは：

1. **Livewireコンポーネント内では正しく動作**:
   - 何らかの方法でカラムIDベースのアクセスが機能していた
   - または、Livewireのライフサイクル内で変換が行われていた

2. **テストが実際のシステムの挙動を反映していなかった可能性**:
   - テストで `$ledger->content[1]` でアクセスするのは誤り？
   - または、モデルレベルでの変換処理が存在するが見つけられていない？

## 今後の調査が必要な点

1. **`CreateColumn` での動作確認**:
   - `CreateColumn` では `initColumns()` メソッドがどう動作しているか
   - 親クラスでの `initColumns()` 実装を確認

2. **既存のテストがなぜ通っていたか**:
   - `ModifyColumnTest` や `ModifyColumnTenancyTest` が変更前に通っていたなら
   - どのように `content` 配列にアクセスしていたか再確認

3. **Livewireのデータバインディング**:
   - Livewireが `content` プロパティをどう処理しているか
   - `wire:model="content.1"` のようなバインディングがどう解決されるか

4. **`normalizeByColumnDefine()` の使用箇所**:
   - このメソッドがどこで呼ばれているか全体を調査
   - `.values()` を削除した場合の影響範囲を正確に把握

## 結論

現時点では、システムの設計に関する理解が不完全であり、安全に修正を適用できない状態。以下の理由により変更をロールバックする：

1. テストの失敗により、他の機能への影響が確認された
2. データ構造の変換タイミングと場所について、全体像を把握できていない
3. ユーザーの証言（UI上は問題なく動作していた）と、実装の理解にギャップがある

## 推奨される次のステップ

1. 既存のコードベースで、`content` 配列へのアクセスパターンを全て洗い出す
2. Livewireコンポーネントのライフサイクルにおける `content` の変換処理を追跡
3. 動作している実際のUIでデバッガーを使用して、`content` 配列の構造を確認
4. テストケースが実際のシステムの挙動を正しく反映しているか検証
5. 上記の調査結果を元に、改めて適切な修正方法を決定
