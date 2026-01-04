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

## 2. サービスレイヤー改修 (`LedgerDiffProcessor`)

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

---

## 3. インターフェース詳細

### 3.1 `LedgerHistoryManager` (Parent)

#### Inputs (Mount)
- `ledgerId`: int

#### Events (Listeners)
- `displayLevelUpdated`: `Show` コンポーネントからのイベントを受信し、自身の `$displayLevel` を更新。`LedgerDiffViewer` へダウンプロパゲートする。

#### Outputs (View)
- `<livewire:ledger.ledger-diff-viewer ... />` を条件付きで呼び出し。
- 承認履歴テーブル (Table UI)。

### 3.2 `LedgerDiffViewer` (Child)

#### Render Logic
- `LedgerContentProcessor` を使用して、`baseData` と `targetData` の差異を計算済みHTMLとして `displayData` プロパティにセット。
- `showChanges` トグル: `targetData` が存在する場合のみ有効化。

---

## 4. 既存 `ShowDiff` (専用履歴画面) との整合性

- `ShowDiff` コンポーネントも、内部的にリファクタリング後の `LedgerDiffViewer` を使用するように修正することを推奨（今回は必須スコープ外だが、共通化のメリットあり）。
- 当面は `ShowDiff` は既存の `<x-ledger.detail.table>` (スナップショット表示) を維持してもよいが、ロジック一貫性の観点から `LedgerContentProcessor` の汎用化は必須。

## 5. 懸念点と対応策 (Updated)

| 懸念事項 | 詳細 | 対応策 |
|---|---|---|
| **1. 添付ファイル情報の肥大化** | 添付ファイルを表示するために `allAttachments` (Ledgerに紐づく全ての`AttachedFile`) をコンポーネントに渡す必要があるが、長期運用でファイル数が数千件になった場合、メモリと通信量を圧迫する恐れがある。 | **Phase 1での対応:** 現状の想定（最大100履歴×50ファイル=5000レコード）の範囲ならメモリ内処理で許容可能と判断。<br>**将来的な対応:** `allAttachments` として全件渡すのをやめ、表示対象の `attachmentIds` リストのみを受け取り、必要な分だけ取得する「遅延ロード型」または「バッチ取得型」に変更する。 |
| **2. カラム定義の不整合** | 比較する2つのバージョン間で `column_define`（カラム定義）が大きく変更されている場合（例: テキスト型から数値型へ変更、カラム削除など）、差分計算やHTML生成がエラーになる可能性がある。 | **対応策:** `LedgerDiffProcessor` 内で、**新しい方のバージョンのカラム定義**を「主（Master）」として採用し、古いデータをその定義に合わせて正規化（または表示可能な形式に変換）するロジックを実装する。削除されたカラムは「削除済み」として明示的に扱う。 |
| **3. 表示状態 (displayLevel) の同期ラグ** | `Show` (親) → `LedgerHistoryManager` (子) → `LedgerDiffViewer` (孫) とプロパティが伝播するため、ユーザー操作から反映までにタイムラグや同期ズレが発生し、UIのちらつき（FOUC）が起きる可能性がある。 | **対応策:** Livewire の `Reactive` プロパティや `#[Modelable]` を活用し、親子間のデータバインディングを強化する。また、Alpine.js の `Entangle` を併用し、クライアントサイドでの即時反映も検討する。 |
| **4. `LedgerHistoryManager` の責務過多** | 履歴管理、ページネーション、任意比較の選択、権限チェック、表示制御など、多くの機能が集約され、コンポーネントが肥大化しやすい。 | **対応策:** ビジネスロジック（差分計算、権限判定）は徹底してサービスクラス (`LedgerDiffProcessor`, Policy) に切り出す。コンポーネントは「入力を受け取り、サービスを呼び出し、結果を表示する」だけのコントローラ的な役割に徹する。 |
