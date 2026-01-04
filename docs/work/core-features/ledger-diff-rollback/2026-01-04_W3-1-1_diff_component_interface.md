# W3-1.1 共通差分コンポーネントインターフェース設計

**最終更新:** 2026-01-04
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`
**ステータス:** Draft

## 1. コンポーネント構成案

現在の `LedgerDiffViewer` と `ShowDiff` の非一貫性を解消し、更新履歴タブでの「任意比較」「履歴一覧」を実現するために、以下の構成へ再編する。

### 1.1 新規コンポーネント: `LedgerHistoryManager`
**責務:** 履歴タブのメインコントローラ。履歴一覧の管理と、選択されたバージョンの差分表示をオーケストレーションする。

| プロパティ/メソッド | 説明 |
|--- |--- |
| `public $ledgerId` | 対象台帳ID |
| `public $history` | 承認履歴テーブル用の `LedgerDiff` コレクション（ページネーション/無限スクロール対応） |
| `public $selectedDiffId` | メイン表示対象（左側、またはスナップショット対象） |
| `public $comparisonDiffId` | 比較対象（右側）。`null` の場合はスナップショット表示。 |
| `public $displayLevel` | 親 `Show` コンポーネントから同期される表示レベル (1-3) |
| `loadMoreHistory()` | 無限スクロール用。履歴を追加ロードする。 |
| `selectVersion($diffId, $asComparison = false)` | 比較対象を選択するアクション。 |

### 1.2 リファクタリング: `LedgerDiffViewer`
**責務:** 純粋なプレゼンテーションコンポーネントに変更。DBアクセスを行わず、渡されたデータのみを描画する「Dumb Component」化を目指す。

| プロパティ | 説明 | 必須 |
|--- |--- |--- |
| `baseData` | 比較基準データ（現在のバージョン または 選択された `LedgerDiff`）。配列形式。 | Yes |
| `targetData` | 比較対象データ（過去の `LedgerDiff`）。`null` ならスナップショットモード。配列形式。 | No |
| `baseMeta` | 基準データのメタ情報（更新者、更新日時、バージョン等）。`Ledger` または `LedgerDiff` モデル、あるいは配列。 | Yes |
| `targetMeta` | 対象データのメタ情報。`LedgerDiff` モデル、あるいは配列。 | No |
| `displayLevel` | 表示レベル (1-3)。表示カラムのフィルタリングに使用。 | Yes |
| `collapsedStates` | グループ開閉状態（Phase 1では初期値として利用、管理はAlpine）。 | Yes |
| `columnDefines` | カラム定義情報（`Ledger` または `LedgerDiff` から取得）。 | Yes |
| `allAttachments` | 添付ファイル参照用コレクション。 | Yes |
| `highlight` | 検索キーワード等、ハイライト表示する文字列。 | No |

---

## 2. サービスレイヤー改修方針（LedgerDiffProcessor）

現状の `prepareContentDiff` メソッドは `Ledger` モデルに依存しており、`LedgerDiff` 同士の比較に対応できない。これを汎用化する。

### 変更前
```php
public function prepareContentDiff(Ledger $ledgerRecord, ?LedgerDiff $comparisonTargetDiff): array
```

### 変更後 (Interface Definition)
```php
/**
 * 任意の2つのコンテンツ配列を比較し、差分構造を生成する
 */
public function prepareGenericDiff(
    array $baseContent,           // 基準データ (Left/Current)
    array|null $targetContent,    // 比較対象 (Right/Previous)
    EloquentCollection $columnDefines // カラム定義
): array
```
※ 既存の `prepareContentDiff` は、この `prepareGenericDiff` をラップする形に変更し、後方互換を維持しつつリファクタリングする。

### 2.3 Diff DTO 構造定義（Phase 1 / Cycle 1）

> [!NOTE]
> Phase 1 / Cycle 1 では「最新状態（または現在詳細ビューで表示中の状態）」と「1つの履歴スナップショット」の 1 対 1 比較のみを扱う。任意 2 バージョン比較は Cycle 2 以降のスコープとし、ここでは「将来拡張を阻害しない DTO 形」を定義する。

1. DTO の目的と前提
   - 目的
     - ビュー（`LedgerDiffViewer`）から差分計算ロジックを切り離し、`LedgerDiffProcessor` 側に責務を集約する。
     - 基本情報タブと更新履歴タブで同一の差分DTOを利用し、表示レベル・グループ開閉などの UI ロジックを共有しやすくする。
   - 前提
     - 比較対象は「Base（基準）」と「Target（比較対象）」の 2 つのみ。
     - Phase 1 / Cycle 1 では Base = 現在、Target = 1つの履歴 `ledger_diff` を想定する。

2. フィールド単位 DTO（FieldDiffDTO）
   - 構造（連想配列として表現）
     - `field_key` (string)
       - カラム ID または論理キー。例: `column_1`, `status`, `tags`, `attachments.1.abc123.pdf` など。
     - `field_type` (string)
       - 表示ロジックで利用する型。例: `text` / `number` / `date` / `select` / `status` / `attachment` など。
     - `label` (string)
       - 画面表示用ラベル。`column_define` / 内部定義から解決する。
     - `base_value` (mixed|null)
       - 基準側（Base）の値。AsColumnArrayJson の制約上、呼び出し元で配列アクセス済みの値を渡す前提。
     - `target_value` (mixed|null)
       - 比較対象側（Target）の値。存在しない場合は `null`。
     - `change_type` (string)
       - `added` / `removed` / `updated` / `unchanged` のいずれか。
     - `highlight` (bool)
       - Phase 1 では `change_type !== 'unchanged'` を基本ルールとし、将来の検索ハイライト拡張に備えて boolean として保持する。
     - `meta` (array)
       - 型別の追加情報を格納するための拡張用フィールド。例: 数値フォーマット、日付タイムゾーン、ステータス色名、添付ファイルの MIME、アイコン種別など。

3. レコード全体 DTO（DiffResultDTO）
   - 構造
     - `fields` (array<FieldDiffDTO>)
       - 各フィールドの差分リスト。
     - `summary` (array)
       - `changed_count` (int)
       - `added_count` (int)
       - `removed_count` (int)
       - `unchanged_count` (int)
     - `groups` (array)
       - キー: グループ ID（セクション識別子など）
       - 値: `['label' => string, 'field_keys' => string[]]`
       - 表示レベル・グループ開閉（`collapsedStates`）と連携するため、フィールドをグループ単位に束ねるためのメタ情報とする。

4. 型別ルール（Phase 1 の扱い）
   - テキスト型 (`text`)
     - 差分表現は「変更有無のフラグ + 全文表示」とし、単語単位の差分ハイライトは行わない（Cycle 2 の候補）。
   - 数値型 (`number`)
     - `base_value` / `target_value` に数値（または数値文字列）を保持し、ビュー側でフォーマットする。
     - `change_type` は `null → 非 null` なら `added`、逆なら `removed`、両方非 null かつ値変更なら `updated`。
   - 日付型 (`date`)
     - DTO 上は ISO8601 ストリング（`YYYY-MM-DD` / `YYYY-MM-DDTHH:MM:SS`）として扱い、タイムゾーン情報は `meta['timezone']` などに格納する。
   - ステータス (`status`)
     - 現行の `status` 表現に合わせ、`meta['status_color']`, `meta['status_label_key']` などを付加する。
   - 添付ファイル (`attachment`)
     - キー解決ルール:
       - 画像: OCR 後の `.pdf` キー（例: `original.jpg` → `original.pdf`）を優先し、Phase 1-5 の添付仕様に従う。
       - PDF: 元のキーを使用（`document.pdf` → `document.pdf`）。
     - DTO 単位:
       - Phase 1 ではファイル単位の追加/削除/更新のみを扱う。
       - プレビュー内容や抽出テキストの差分は扱わない（将来拡張に備えて `meta['content_digest']` などを持たせる余地を残す）。

---

## 3. `prepareGenericDiff` インターフェース詳細（Phase 1 / Cycle 1）

### 3.1 入力パラメータ

- `array $baseContent`
  - 構造: `['field_key' => mixed]` 形式の連想配列。
  - AsColumnArrayJson キャストにより `data_get()` が利用できないため、呼び出し側で `$ledger->content[$id]` / `$ledger->content_attached[$id][$file]` のように配列アクセス済みのデータを組み立てて渡す前提とする。
- `?array $targetContent`
  - 構造は `$baseContent` と同様。
  - `null` の場合は「スナップショット表示専用モード」として扱い、`change_type` はすべて `unchanged` とする（Phase 1 では主に比較モードで利用）。
- `Collection $columnDefines`
  - 各 `column_define` を含む Eloquent コレクション。
  - `field_key` 解決、`field_type` / `label` / グループ情報などのメタ取得に利用する。

### 3.2 戻り値

- 返り値: `array` 型の DiffResultDTO
  - 上記「2.3 Diff DTO 構造定義」で定義した構造を満たす連想配列。
- エラー処理方針:
  - DTO レベルでは原則として例外をスローせず、`meta['error']` にエラー種別を格納してビュー側に伝える。
  - 型不整合や未知の `field_key` は `change_type = 'unchanged'` とし、`meta['error'] = 'incompatible_column_define'` などで原因を識別可能にする。

### 3.3 Phase 1 のスコープ明示

- サポートするもの（Phase 1 / Cycle 1）
  - 現在状態 vs 1 履歴スナップショットの差分生成。
  - 変更有無と種別（追加/削除/更新/変更なし）の判定。
  - グループ単位のフィールド束ねと `displayLevel` / `collapsedStates` との連携。
- サポートしないが将来拡張を想定するもの
  - 任意 2 履歴同士の比較（Cycle 2）。
  - 検索キーワードに連動したハイライト（`highlight` の細分化）。
  - テキスト差分の部分強調（Diff ビュー的な表現）。

---

## 5. PM 判断が必要な事項（Diff DTO レベル）

> [!NOTE]
> 本時点の計画では、5.1 は **案A**、5.2 は **案B** を採用する方針で進める。

1. 変更なしフィールドの DTO への含め方
   - 採用方針: **案A: 変更なしフィールドもすべて DTO に含める（Phase 1 の標準仕様）**
     - メリット:
       - `displayLevel` 変更時にサーバー再計算なしで表示レベルを切り替えられる。
       - 「すべてのフィールドを 1 画面で俯瞰する」ユースケースに対応しやすい。
     - デメリット:
       - DTO サイズが増え、通信量・レンダリングコストが増大する可能性がある（ただし履歴件数 100 件程度を前提に許容範囲と見込む）。
   - （参考案）案B: 変更ありフィールドのみを DTO に含める
     - メリット: データ量が減り、ネットワーク/描画の負荷が軽い。
     - デメリット: 「詳細表示に切り替えたが何も増えない」といった UX 上の違和感が出る可能性があるため、現時点では採用しない。

2. テキスト型の差分粒度
   - 採用方針: **案B: 将来の語単位ハイライトに備え、`meta['diff_chunks']` などのフィールドだけ先に定義しておく**
     - メリット:
       - Cycle 2 以降に UI を強化しやすくなる（部分ハイライトや差分ビュー表現への橋頭堡となる）。
       - Phase 1 時点では `meta['diff_chunks']` を `null` または空配列としておき、後方互換を維持しながら拡張できる。
     - デメリット:
       - Phase 1 の時点では未使用フィールドが増え、仕様としては若干複雑に見える。ただし開発者向けドキュメントで明示しておくことで吸収可能と判断する。
   - （参考案）案A: 値全体の変更有無のみを扱う
     - メリット: 実装がシンプルで、パフォーマンスへの影響も小さい。
     - デメリット: 将来の UI 強化時に DTO 仕様を追加変更する必要が出てくるため、今回の計画では採用しない。
