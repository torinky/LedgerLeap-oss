# Ledger content データ構造とテスト

**最終更新:** 2026-02-28
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## content の正規化とデータベース保存フロー

```php
// 1. Livewire コンポーネントでの入力
$content = [1 => 'テキスト', 3 => '値'];  // カラムIDをキーとした連想配列

// 2. Ledger::saving イベントで normalizeByColumnDefine() による正規化（保存前）
//    → Ledger::booted() で自動実行される
// - カラムIDの欠番を空文字で埋める
$normalized = [0 => '', 1 => 'テキスト', 2 => '', 3 => '値'];

// 3. AsColumnArrayJson::set() による変換（DB 保存）
// - array_values() で連番配列に変換
$stored = ['', 'テキスト', '', '値'];  // JSON: ["","テキスト","","値"]

// 4. DB から読み取り時（AsColumnArrayJson::get()）
$fromDb = [0 => '', 1 => 'テキスト', 2 => '', 3 => '値'];
```

> **変更履歴:** 2026-05-03 以降、`Ledger::booted()` の `saving` イベントで `normalizeByColumnDefine()` が自動実行されるようになった。これにより `render()` 側での正規化は不要となった。

---

## テストでの Ledger 作成時の注意点

`Ledger::factory()->create()` を呼ぶと、`Ledger::booted()` の `saving` イベントで `normalizeByColumnDefine()` が自動実行される。したがって、欠番を手動で埋める必要はなく、必要なカラムIDだけを指定すればよい。

```php
// ✅ Factory で直接作成（saving イベントで自動正規化される）
$ledger = Ledger::factory()->create([
    'content' => [
        0 => 'テストタイトル',
        7 => ['タグ1', 'タグ2'],  // キー7のみ指定（欠番は自動で埋まる）
    ],
]);
// → DB には正規化済みで保存される
// → $ledger->content[7] で正しくアクセスできる
```

ただし、`$ledger->content` にアクセスする前に `fresh()` を呼んで最新状態を取得するか、
正規化後の値を直接アサーションに使う場合は注意が必要。

### 重要な制約まとめ

- テストデータは**必ず 0 から始まる連番配列**として作成する
- カラムID に欠番がある場合は、空文字列 `''` または空配列 `[]` で埋める
- `content_attached` も同様（カラムID 0 に空要素が必須）

```php
// ✅ content_attached の正しい構造
'content_attached' => [
    0 => [],  // カラムID 0（空）※必須
    1 => [
        'test.pdf' => [
            'meta' => ['content' => 'OCR extracted text'],
        ],
    ],
],
```

---

## AsColumnArrayJson キャストの制約

### `data_get()` との非互換性

`AsColumnArrayJson` キャストは内部で `___serialized___` プレフィックスを使ったシリアライゼーションを行うため、
Laravel の `data_get()` ヘルパーが正しく動作しない。

```php
// ❌ 動作しない
$text = data_get($ledger->content_attached, '1.test.pdf.meta.content');
// => NULL

// ✅ 正しい方法：直接配列アクセス
$text = $ledger->content_attached[1]['test.pdf']['meta']['content'] ?? null;
// => 'OCR extracted text'
```

**テストでのアサーションも同様：**

```php
// ❌ 避けるべきパターン
$this->assertEquals('expected', data_get($ledger->content, '1'));

// ✅ 推奨パターン
$this->assertEquals('expected', $ledger->content[1] ?? null);
```

---

## カラムID と配列インデックスの対応関係

```php
$ledgerDefine->column_define = [
    new ColumnDefine(0, 'フィールド0', ...),
    new ColumnDefine(2, 'フィールド2', ...),  // ID=1 は欠番
];

// maxId = 2 なので、インデックス 0,1,2 が作成される
// normalizeByColumnDefine(['1 => '値']) の後:
// [0 => '', 1 => '値', 2 => '']

// DB 読み取り後も同じ構造:
// $ledger->content[0] → ''  （カラムID=0 の値）
// $ledger->content[1] → '値'（カラムID=1 の値）
// $ledger->content[2] → ''  （カラムID=2 の値）
```

---

## テストのベストプラクティス

```php
// ✅ パターン1: 実際のフローを使う（正規化が自動的に行われる）
Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
    ->set('content.1', 'テスト値')
    ->call('saveDraft');

// ✅ パターン2: 正規化された形式で直接作成
$ledger = Ledger::factory()->create([
    'content' => [
        0 => '',
        1 => 'テスト値',
        2 => '',
    ],
]);

// ✅ パターン3: ヘルパーメソッドを作成
protected function createLedgerWithContent(LedgerDefine $define, array $content): Ledger
{
    $normalized = $define->normalizeByColumnDefine($content);
    return Ledger::factory()->create([
        'ledger_define_id' => $define->id,
        'content' => $normalized,
    ]);
}
```

---

## トラブルシューティング

### `Undefined array key X` エラーが発生する

チェックポイント:
1. テストデータ作成時にカラムID の欠番を空文字で埋めているか？
2. `column_define` の maxId までの全インデックスを含めているか？
3. DB に保存された実際の JSON 構造を確認したか？

```php
// デバッグ方法
$ledger = Ledger::find($ledgerId);
dd([
    'content'      => $ledger->content,
    'content_keys' => array_keys($ledger->content),
    'db_raw'       => $ledger->getAttributes()['content'],
]);
```

---

## Ledger ワークフローテストのデータ準備

### `latest_diff_id` の正しい設定方法

`Ledger.latestDiff()` は `belongsTo(LedgerDiff::class, 'latest_diff_id')`。
`LedgerDiff::factory()` で差分を作成しただけでは `Ledger.latest_diff_id` は更新されない。

```php
// ❌ latest_diff_id が null のまま → latestDiff() が null を返す
$ledger = Ledger::factory()->create(...);
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

// ✅ 明示的に設定する
$ledger = Ledger::factory()->create(...);
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
$ledger->update(['latest_diff_id' => $diff->id]);
$ledger = $ledger->fresh();  // latest_diff_id を反映したインスタンスを取得
```

### `PendingList::openApproverSelectModal()` のテスト必須手順

内部で `$ledger->latestDiff()->first()` の `inspector_id` と `Auth::id()` を比較するため、
`latest_diff_id` が設定されていないとモーダルが開かない。

```php
private function createPendingLedgerWithDiff(): array
{
    $ledger = Ledger::factory()->create([
        'status' => WorkflowStatus::PENDING_INSPECTION,
    ]);
    $diff = LedgerDiff::factory()->create([
        'ledger_id'    => $ledger->id,
        'inspector_id' => $this->inspector->id,  // Auth::id() と一致させる
        'status'       => WorkflowStatus::PENDING_INSPECTION,
    ]);
    $ledger->update(['latest_diff_id' => $diff->id]);  // 必須

    return [$ledger->fresh(), $diff];
}
```

