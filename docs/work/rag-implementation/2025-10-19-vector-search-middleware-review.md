# Mroonga/Groonga ベクトル検索 - 最終調査報告と実装ガイド

**作成日:** 2025年10月19日  
**ステータス:** 実装方法確定  
**作成者:** Gemini CLI

> **📖 関連ドキュメント:**
> - [2025-10-17-phase1-hybrid-search-plan.md](./2025-10-17-phase1-hybrid-search-plan.md) - 当初の全体計画

---

## 1. 調査の目的と最終結論

### 1.1. 目的
当初計画の「アプローチ1: MySQL/Mroonga 活用案」に基づき、Mroonga/Groongaのネイティブ機能でベクトル類似度検索が実現可能かを調査・検証する。

### 1.2. 最終結論

**実行可能である。**

一連の調査と実践的なテストを経て、既存のMySQL/Mroonga環境でベクトル検索を実装するための、確実かつ実用的な方法を確立した。

Pgroongaへの移行や専門のベクトルDBの導入は不要と判断する。

## 2. 結論に至る調査経緯

当初、`distance_cosine` 関数の呼び出しに失敗し、一時は「実装不可能」と判断したが、ユーザーからの的確な情報提供と、段階的なテストにより、以下の事実が判明した。

1.  **`mroonga_command("load ...")` の問題点:**
    `load` コマンドは、MySQL側のエスケープ規則とGroonga側のJSONパースが二重に衝突するため、Laravel等のアプリケーション経由での利用は極めて脆弱で実用的でないことが判明。Groonga公式ドキュメントでも非推奨とされている。

2.  **`INSERT` 方式への転換:**
    `load` コマンドの代替として、通常の `INSERT` 文で `ENGINE=Mroonga` のテーブルに書き込む方式が有効であることを確認。これにより、エスケープの問題を回避できる。

3.  **スキーマ定義の要件:**
    `INSERT` 方式であっても、ベクトルを格納するカラムには `COMMENT 'flags "COLUMN_VECTOR", type "Float"'` という特別なコメントを付与し、Groongaにカラムの型を明示的に伝える必要がある。これが無い場合、`distance_cosine()` の実行時に `1st argument must be vector column` エラーが発生する。

4.  **クエリ構文の要件:**
    `mroonga_command` を介して、Groongaの `select` コマンドと動的カラム機能 (`--columns[<name>].value`) を利用して `distance_cosine` 関数を呼び出す必要がある。

5.  **PHPからの呼び出し方法:**
    `mroonga_command` に渡すGroongaのコマンド文字列は、改行を含まない1行の文字列としてPHP側で組み立て、プリペアドステートメントを使わずに直接SQLに埋め込む必要がある。

## 3. 最終的な実装アプローチと実践ガイド

以下に、LaravelアプリケーションでMroongaベクトル検索を実装するための具体的な手順を示す。

### Step 1: テーブルの作成 (マイグレーション)

ベクトルを格納するテーブルを定義する。最も重要なのは `embedding` カラムの `COMMENT` 句である。

**`database/migrations/..._create_ledger_chunks_table.php` の例:**

```php
// ...
Schema::create('ledger_chunks', function (Blueprint $table) {
    $table->id();
    // ... 他のカラム
    $table->text('embedding')->comment('flags "COLUMN_VECTOR", type "Float"');
    // ...
});
// ...
```

`DB::statement` を使って直接 `CREATE TABLE` を実行する場合は以下のようになる。

```sql
CREATE TABLE docs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  content TEXT,
  embedding TEXT COMMENT 'flags "COLUMN_VECTOR", type "Float"'
) ENGINE = Mroonga;
```

### Step 2: データ挿入

`mroonga_command("load ...")` は使わず、通常の `INSERT` 文でデータを挿入する。SQLインジェクションを防ぐため、プレースホルダを用いたプリペアドステートメントを推奨する。

**Laravelでの実装例:**

```php
use Illuminate\Support\Facades\DB;

$sql = 'INSERT INTO docs (content, embedding) VALUES (?, ?), (?, ?), (?, ?)';
$bindings = [
    'This is a sample document.', '[0.1,0.2,0.3,0.4]',
    'Another example text.', '[0.2,0.1,0.4,0.3]',
    'Different topic entirely.', '[0.9,0.8,0.1,0.0]'
];
DB::insert($sql, $bindings);
```
ベクトルデータは、JSON配列形式の**文字列**として渡す。

### Step 3: ベクトル検索の実行

`mroonga_command("select ...")` を使って検索を実行する。

**Laravel (`RagSearchService`等) での実装例:**

```php
use Illuminate\Support\Facades\DB;

/**
 * ベクトル検索を行う
 *
 * @param array<float> $queryEmbedding 検索ベクトル
 * @return array
 */
function searchByVector(array $queryEmbedding): array
{
    $vector = '[' . implode(',', $queryEmbedding) . ']';

    // Groongaコマンドを改行を含まない1行の文字列として構築
    $mroonga_command = "select docs " \
                       "--columns[score].stage filtered " \
                       "--columns[score].flags COLUMN_SCALAR " \
                       "--columns[score].types Float32 " \
                       "--columns[score].value 'distance_cosine(embedding, {$vector})' " \
                       "--output_columns content,score " \
                       "--sort_keys score " \
                       "--limit 10";

    // SQLクエリ全体を文字列として組み立て、直接実行
    $sql = "SELECT mroonga_command(\"" . $mroonga_command . "\") AS res";

    $result = DB::select($sql);

    // 結果は配列で返ってくるので、最初の要素のresプロパティをデコード
    $groonga_response = json_decode($result[0]->res);
    
    // 必要に応じて、アプリケーション側でパースとソートを行う
    return parseGroongaResponse($groonga_response);
}
```

### Step 4: 結果のパースと注意点

`mroonga_command` から返されるJSONは独特の配列構造を持つため、アプリケーションで扱いやすい形式に変換（パース）する必要がある。

**成功時のレスポンス例:**
```json
[
    [
        [ 3 ], // ヒット件数
        [ /* カラム定義 */ ],
        [ "Another example text.", 0.066... ],
        [ "Different topic entirely.", 0.576... ],
        [ "This is a sample document.", 2.22e-16 ]
    ]
]
```

**注意点:**
*   **ソート:** テストでは `--sort_keys score` が期待通りに機能しなかった。信頼性を高めるため、検索結果は取得後に**アプリケーション側でスコアに基づいてソートする**ことを強く推奨する。
*   **パース処理:** 上記のJSON構造を、`['content' => '...', 'score' => 0.0]` のような連想配列のリストに変換するヘルパー関数を用意すると良い。

# 補足事項

Mroonga側で **ソート（ORDER BY 相当）を実行する方法** を整理します。
（あなたの「ベクトル検索＋距離スコアでソート」目的にも関連しますが、純粋な Groonga／Mroonga の `select` コマンドとしてのソート機能を中心に説明します。）

---

## ✅ Groonga の `select` におけるソート機能

まず、Groonga（Mroonga が内部的に使っているエンジン）の `select` コマンドを使ったソート機能が以下のように定義されています。 ([Groonga][1])

* `--sort_keys` パラメータ：ソート対象のカラム名を指定。複数可。例：`--sort_keys 'n_likes, _id'`（昇順） ([Groonga][1])
* 降順はカラムの前に `-` を付ける。例：`--sort_keys '-n_likes, _id'` ([Groonga][1])
* デフォルトのソートキーは `_id` とのこと（明記あり） ([Groonga][1])
* 注意： `sortby` パラメータは古く、現在は `sort_keys` を使うべき。 ([Groonga][1])
* `--limit`／`--offset` と一緒に使え、例えば `--sort_keys _id --limit 3` なども可能。 ([Groonga][1])

この機能自体が Groonga の一般検索用であるため、ベクトル検索で「スコア（距離・類似度）に応じてソート」したい場合もこの仕組みを活用できます（ただし、実際には少し注意点があります）。

---

## ✅ Mroonga 経由でのソート（Groonga `select` を使う場合）

Mroonga を使って MySQL／MariaDB 上から Groonga の `select` コマンドを実行する場合、以下点がポイントです。 ([Mroonga][2])

### 実行手順

1. `mroonga_command()` を使って Groonga の `select` コマンドを文字列として MySQL／MariaDB に渡す。
2. `select` コマンド内に `--sort_keys <カラム名>` を指定すれば、Groonga がそのキーでソートして結果を返却。例：

   ```
   select your_table --sort_keys '-score, _id' --output_columns content,score --limit 10
   ```
3. MySQL 側では `SELECT mroonga_command("…") AS res` のように実行し、返ってきた JSON をパースして使用。

### 注意点・限界

* MySQL／MariaDB の SQL 層を通るため、文字列のエスケープや改行・特殊文字の処理に非常に気を使う（以前あなたが遭遇した「Syntax error: <||>」はこのあたりが原因）
* `--sort_keys` に指定できるのは「通常のカラム（または擬似カラム `_score`, `_id` 等）」。ベクトルスコア（例えば `distance_cosine(...)`）で動的に算出されたスコアを直接カラムとして指定できるかは状況による。Groonga の `select` では動的列（`columns[score].value` など）を使って、スコア列を生成し、それをソートキーに使う設計も可能。ただしその場合 `sort_keys` の指定が「その動的列名」に一致している必要。
* Mroonga のドキュメントに「ORDER BY LIMIT 最適化」について記載があり、Groonga がソート＋LIMITを効率的に処理できる条件がいくつか示されています。 ([Mroonga][3])
* ベクトルスコア検索（例： `distance_cosine(embedding, [ … ])`）を Groonga の `select` で行う場合、スコア列を `--columns[score].value` 等で明示し、その列を `--sort_keys score` でソート指定する、という流れが一般的です。

---

## ✅ 具体例：ベクトルスコアでソートする `select` コマンド例

あなたが「embedding ベクトルを持つテーブルで、クエリベクトルとの距離を算出し、その距離が小さい順（類似度が高い順）でソートしたい」というケースに対応する例を下記に示します。

```
select Docs \
  --columns[score].stage filtered \
  --columns[score].flags COLUMN_SCALAR \
  --columns[score].types Float32 \
  --columns[score].value 'distance_cosine(embedding, [0.1,0.2,0.3,0.4])' \
  --output_columns _key,content,score \
  --sort_keys score \
  --limit 10
```

この `select` の意味：

* `--columns[score].value 'distance_cosine(...)'` により、Groonga が各レコードに対して距離（cosine距離）を計算し、 `score` という動的列に格納。
* `--sort_keys score` によって、 `score` の昇順（距離が小さい方が先）でソートされる。
* `--limit 10` にて上位10件を取得。
* `--output_columns _key,content,score` によって、レコードのキー、本文、スコアを出力。

※ 注意：降順にしたい場合（例えば類似度スコアが大きい方が好き）なら `--sort_keys -score` のようにマイナスを付けて下さい。

---

## ✅ Mroongaでソートを「確実に有効にする」ためのチェックポイント

実運用で「思った通りソートされない／順位が乱れる」といったトラブルを防ぐため、次の点を確認して下さい。

1. **ソートキーが有効な列か**

    * 動的列（`columns[name].value`）を使う場合、名前が正しいか。
    * 既存カラムを使うなら（例： `created_at`, `score` 等）そのカラムが SELECT に出ていて索引可能か。

2. **`--sort_keys` の並び・符号（+/-）**

    * 昇順ならカラム名だけ。
    * 降順なら `-カラム名`。例： `--sort_keys -score, _id`

3. **LIMIT を併用する**

    * 大量データから上位を取るなら `--limit` を必ず指定。
    * `--offset` で開始位置をずらすことも可能。 ([Groonga][1])

4. **MySQL 側でも ORDER BY を書かない／もしくは適切に**

    * Mroonga の `mroonga_command()` は Groonga のソートを使うため、MySQL 側で更に `ORDER BY` を書くとロジックが二重になる。また MySQL 側でソート・再取得すると性能低下。
    * もし MySQL 側でソートが必要な場合は、Groonga 側の `--sort_keys` を「最終ソート用」として設計すると良い。

5. **最適化可能性の確認**

    * Mroonga ドキュメントに「ORDER BY LIMIT 最適化」について記載があります：MySQL の `ORDER BY … LIMIT` を Groonga が効率的に処理できる条件があるとのこと。 ([Mroonga][3])
    * その条件を満たせば、ソート＋LIMIT を高性能に実行できる。

6. **ソート付き＋フィルタ付き検索**

    * 通常の `--query`, `--filter` と組み合わせて使うことも可能です。例えば `--filter 'content @ "keyword"' --sort_keys score` 等。
    * ただしベクトル検索など「distance_cosine」のような動的スコア列では、フィルタとソートキーを明確に分けて設計するほうが理解しやすい。

---

## ⚠️ Mroongaでソートを使う上での“落とし穴”

* MySQL／Laravel 経由で `mroonga_command()` を使うと **クォート・エスケープ／文字列改行の制御が非常に厳しい**ため、コマンド文字列内で `--sort_keys score` 等を記述してもうまく処理されないケースあり。
* `sort_keys` を指定しても、「スコア列が負の値だった」「カラム名が存在しなかった」「動的列名が適切に生成されていなかった」などの理由で、期待通り順序が出ないことがあります。
* ベクトルスコア用途では「スコアが小さいほど意味が近い」という逆の評価指標を使うことがあるため、ソートキーの符号（昇順／降順）を誤ると「似てないものから出る」ような誤動作になります。
* Mroonga の内部仕様／バージョンによっては、ソートキーによる最適化（インデックスを使ってソート）に限界があり、全件スキャン＋ソートになるため性能が出ないことがあります（特に大規模データ＋高次元ベクトルの場合）。


[1]: https://groonga.org/docs/reference/commands/select.html?utm_source=chatgpt.com "7.3.58. select"
[2]: https://mroonga.org/docs/tutorial/storage.html?utm_source=chatgpt.com "4.3. Storage mode — Mroonga v15.17 documentation"
[3]: https://mroonga.org/docs/reference/optimizations.html?utm_source=chatgpt.com "5.1. Optimizations — Mroonga v15.16 documentation"


## 4. 今後のステップ

この実装ガイドに基づき、`RagSearchService` のリファクタリングを再開する。具体的には、`searchByVector` のようなメソッドを実装し、既存のロジックを置き換える。

```