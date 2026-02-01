# ローディング表現の全域統一化計画 (Issue #53)

## 1. 目的
LedgerLeap の Livewire ビュー全域において、現在混在しているローディング表現（スピナー、スケルトン、ドット、ボール、透過表示）を標準化し、システム全体で最高品質のデザイン一貫性と操作フィードバックを実現する。

## 2. デザイン標準（3ティア + 1方式）

### Tier 0: グローバル・ナビゲーション (Page Transition)
- **用途**: `wire:navigate` によるページ遷移、グローバルな検索実行時。
- **デザイン**: 
  - **トップ・プログレスバー**: 画面最上部に Primary カラーの細いプログラバーを表示（MaryUI/Livewire互換）。
  - **目的**: ページ全体が入れ替わる際の「止まっていない」安心感を提供。
- **実装場所**: `app.blade.php`, `appWithDrawer.blade.php` 等のレイアウト。

### Tier 1: フルコンテント・ロード (Main Content)
- **用途**: 画面初期ロード（Lazy loading）、メインエリアの完全なリフレッシュ、重い一括処理。
- **デザイン**: 
  - **構造的スケルトン**: `animate-pulse` によるコンテンツの「型」を表示。
  - **透過ブラインド**: `bg-base-100/40` または `bg-base-300/40` + `backdrop-blur-sm` によるオーバーレイ。
  - **中央スピナー**: 大型スピナー (`loading-lg text-primary`) + 「読み込み中...」ラベル。
- **撤廃項目**: `loading-dots`, `loading-ball`, `loading-bars` 等の特殊アニメーション。

### Tier 2: セクション・ロード (Component Level)
- **用途**: テーブル内フィルタリング、サブパネルの追加読込、モーダル内遷移。
- **デザイン**: 
  - 該当するカードやエリアのみを透過オーバーレイ。
  - 中型スピナー (`loading-md text-primary/60`)。
  - `wire:target` を厳密に指定し、関係のないボタンの非活性化やチラツキを防止。

### Tier 3: マイクロ・インタラクション (Interaction Level)
- **用途**: ボタン操作、インライン検索、トグル/チェックの切り替え。
- **デザイン**: 
  - **ボタン**: MaryUI `spinner` 属性を活用。
  - **インライン**: 入力欄右端等に配置する小型スピナー (`loading-xs`)。

## 3. 調査結果 (フェーズ0)
現時点で特定された主な修正対象箇所：

| 画面/機能 | 現状の表現 | 推奨 Tier | 備考 |
| :--- | :--- | :--- | :--- |
| **全体ナビゲーション** | **なし (ブラウザ標準)** | Tier 0 | `wire:navigate` 用のトップバー導入 |
| **全体リスト (RecordsTable)** | `loading-dots` (固定オーバーレイ) | Tier 1 | 最優先。背景にスケルトン導入 |
| **詳細プレビュー (DiffViewer)** | `loading-dots` / スケルトン | Tier 1 | 実装済みだが他と合わせる |
| **ファイルインスペクター** | 独自スケルトン / `loading-spinner` | Tier 1 | 共通コンポーネントへ置換 |
| **マイポータル (MyPortal)** | **不明 (なし)** | Tier 2 | 各種統計・権限情報の読込時にスケルトンが必要 |
| **フォルダツリー (FolderTree)** | インライン遷移のみ | Tier 2 | フォルダ切替時の待ち時間に操作ミス防止の遮断が必要 |
| **台帳定義 (ModifyColumn)** | `loading-dots` | Tier 1 | 重い処理が多く、Tier 1 オーバーレイが必要 |
| **タグ設定 (Tag)** | `loading-ball` | Tier 3 | 特殊表現を廃止。インラインスピナーへ |
| **各種ボタン (Workflow等)** | MaryUI `spinner` | Tier 3 | 現状維持かつ適用漏れを補填 |
| **検索/フィルタ (Activity等)** | `x-mary-loading` | Tier 2 | 表示位置とサイズを Tier 2 基準へ |
| **添付ファイルカード** | `loading-dots` (サムネイル部) | Tier 2 | カード単位のスケルトンへ |

### 3.1 なぜ統一が必要か
- **デザインの一貫性**: ドット、ボール、スピナーの混在は「未完成」な印象を与える。Primaryカラーのスピナーに統一することでブランド信頼度を向上させる。
- **体感速度の向上 (Perceived Performance)**: 空白の画面を見せるのではなく、スケルトンを表示することで「システムが動いている」ことを即座に伝え、心理的待機時間を減らす。
- **連続操作の防止**: `wire:click` 発生中に透過オーバーレイで入力を遮断しないと、二重登録や予期せぬエラーの原因となる。特に Tier 1/2 の役割が重要。
- **視覚的安定性**: `wire:target` を適切に設定しないと、関係ない要素（サイドバーなど）までローディング対象になり、画面全体のチラツキを誘発する。

### 3.2 ドロワー連携における「視覚的空白」の課題 (追記: 2026-01-27)
調査により、サイドドロワー（`Folder/Tree`）からメインエリア（`RecordsTable`）への遷移時に以下の課題が特定された：
1. **通信の断絶**: `Tree` コンポーネントが処理を終えてイベントを dispatch し、ブラウザがそれを受けて `RecordsTable` のリクエストを開始するまでの間に「どちらのコンポーネントも通信していない」空白の時間が存在する。
2. **ターゲットの局所化**: 現在の Tier 1 ローディングが各コンポーネント内に閉じているため、ドロワー操作中にメインエリアが「静止」してしまい、ユーザーがフリーズしたと錯覚する。
3. **解決策**: 特定のターゲットに依存しない「真のグローバル・オーバーレイ」をレイアウトレベルで導入し、ドロワー操作の開始からメインエリアの更新完了までを一つの視覚的シーケンスとして繋ぐ必要がある。

## 4. WBS (Work Breakdown Structure)

### フェーズ1: 基盤整備 (Common Components & Layouts)
- [x] **CP-1.1**: `x-element.loading-overlay` の実装
- [x] **CP-1.2**: 汎用スケルトン部品の整備
  - [x] `skeleton-card`, `skeleton-row`
- [x] **CP-1.3**: レイアウトへのグローバル・プログレスバー導入
- [x] **CP-1.4**: UX 微調整用 CSS (チラツキ防止・アニメーション洗練)
  - [x] チラツキ防止フェードイン
  - [x] シマー（グラデーション）アニメーション導入

### フェーズ2: 主要画面のリフレッシュ (リファクタリング)
- [x] **RF-2.1: リスト系画面**
  - [x] `RecordsTable` のドット廃止と Tier 1 スケルトン導入。
  - [x] `records-table.blade.php` のローディング範囲細分化（検索・移動・抽出）。
  - [x] 検索バーの独立化と `wire:target` によるターゲット限定。
  - [x] フォルダパネル・リストエリアへの詳細スケルトン（グリッド・テーブル）の配置。
  - [x] 全スケルトンへのシマーアニメーション適用。
- [x] **RF-2.2: ポータル・ナビゲーション (注目ポイント)**
  - [x] `MyPortal` に Tier 2 スケルトンカード・統計を表示（統計・権限情報の遅延対策）。
  - [x] `FolderTree` 遷移時に Tier 2 オーバーレイとリストスケルトンを表示。
- [x] **RF-2.3: 詳細画面周辺**
  - [x] `show.blade.php` の初期読込・タブ遷移の洗練。
  - [x] `LedgerHistoryManager` のスケルトン共通化（リストスケルトン導入）。
  - [x] `LedgerDiffViewer` の lazy loading プレースホルダー刷新。
- [x] **RF-2.4: ファイルインスペクター**
  - [x] `skeleton.blade.php` の再設計（テーブル・統計スケルトンの活用）。
  - [x] プレビュー、コンテンツ、履歴の各タブ内読み込みを Tier 2 基準へ。

### フェーズ3: 管理・設定画面の統一
- [x] **RF-3.1: 台帳定義システム**
  - [x] `ModifyColumn` 等の重い処理のローディング表現更新（入力フォームスケルトン導入）。
  - [x] `records-table.blade.php` (定義側) に Tier 2 導入。
  - [x] 特殊な `loading-ball` 等の完全排除。
- [x] **RF-3.2: モーダル・ドロワー全般**
  - [x] `WorkflowAssigneeModal`, `RollbackConfirmModal` 等のボタン内スピナー整合。

### フェーズ4: リアクティブ・コンポーネント統合 (2026-01-31 追加)
- [x] **RF-4.1: Reactive Prop 同期**
  - [x] `LedgerDiffViewer` 等への `#[Reactive]` 適用による状態不整合の解消。
  - [x] 子コンポーネントからの不適切な Prop 書き換えの排除。
- [x] **RF-4.2: 通信の集約**
  - [x] 親子間のリクエストを1回に統合し、スケルトンのチラつきを物理的に排除。
- [x] **RF-4.3: 階層構造におけるターゲット同期**
  - [x] 独立したコンポーネント（Drawer等）からの操作を親の `wire:loading` ターゲットに確実にヒットさせる手法の確立。
  - [x] `$parent` 呼び出しへの移行による `wire:loading.target` 自律追跡の実現。

### フェーズ5: ナビゲーション統合とUI安定化 (2026-02-01 追加)
- [x] **RF-5.1: フォルダ・台帳ナビゲーションの親集約**
  - [x] `RecordsTable` 内のパンくず・フォルダパネル等を `IndexManager` へ移動。
  - [x] 一貫したスケルトン表示（`heavyTargets`）と透過フィードバック（`lightTargets`）の出し分け。
- [x] **RF-5.2: 直接通信への完全移行**
  - [x] `Livewire.dispatch` による非同期イベント連鎖を、`$parent` による直接メソッド呼び出しに置換。
  - [x] ソート・フィルタ等の操作レスポンスを劇的に向上。
- [x] **RF-5.3: `wire:key` と CSS の安定化**
  - [x] 動的な `Hash::make()` キーを固定キーに置換し、コンポーネント再生成を防止。
  - [x] アイコン文字化け（？）を防ぐ CSS 優先順位の調整。

## 5. 知見と制約
- **Livewire 3 の Reactive Prop**: 子での書き換えは厳禁。Alpine.js の `$watch` を使い親のメソッドを叩くのが安全。
- **再帰的コンポーネントの $parent**: `@include` よりも `<x-component>` の方がコンテキスト維持に有利だが、深い階層では依然として不安定な場合がある。
- **delay の解釈**: `.delay.long` は体感より早く、`.delay.longest` (1s) が重い処理のスピナー表示には適している。

## 4.1 フェーズ6: 是正作業 (Phase 6: 2026-01-29)

### 背景
フェーズ1-5の実装完了後、実際の動作確認において以下の問題が発見された：
1. **Tier 2 ローディングの適用漏れ**: ファイルインスペクターのタブ切り替え時にローディングが表示されない
2. **不自然な動作**: ファイルインスペクターで内容が準備できているのにローディングが動作し続ける
3. **プレビューローディングの欠如**: ファイルインスペクターのプレビュー領域でローディング表示がない
4. **スケルトンの要素サイズ問題**: スケルトン表示時に元のエリアの大きさを引き継がず、レイアウトシフトが発生
5. **詳細画面タブ内の不統一**: 一部のタブにスケルトンがあり、他のタブにはない状態

### 是正計画

#### RF-6.1: ファイルインスペクターの修正
- [x] **タブ切り替えローディングの廃止**
  - 問題: タブ切り替えごとにローディングが表示されるが、データは初期ロード済みのため不要で過剰
  - 対策: `target="selectedTab"` の Tier 2 ローディングオーバーレイを削除
  - ファイル: `resources/views/livewire/attached-file/file-inspector.blade.php`

- [x] **プレビュー領域のローディング追加**
  - 問題: 画像読み込み中のローディングはあるが、Livewire側の状態変更時のローディングがない
  - 対策: プレビューコンテナに Tier 2 オーバーレイを追加
  - ファイル: `resources/views/livewire/attached-file/file-inspector/preview.blade.php`

- [x] **スケルトンとオーバーレイの重複表示を防止**
  - 問題: 初期ローディング（`isLoading = true`）時のスケルトンと、タブ切り替え時のオーバーレイが同時表示される可能性
  - 対策: Alpine.js の `isLoading` 状態管理を最適化し、スケルトンはドロワー初回オープン時のみ表示
  - ファイル: `resources/views/livewire/attached-file/file-inspector/skeleton.blade.php`

#### RF-6.2: 詳細画面タブのスケルトン統一
- [x] **履歴タブ以外のスケルトン追加**
  - 問題: `activity`, `permissions` タブにスケルトンがない。基本情報タブの lazy loading 中にスケルトンではなくスピナーが表示される。
  - 対策:
    - `LedgerDiffViewer::placeholder()` に専用のスケルトンビューを導入。
    - `show.blade.php` の基本情報タブの `wire:target` に内部フィルタを追加。
    - `permission-display.blade.php` に `skeleton-table` を適用。
  - ファイル:
    - `resources/views/livewire/ledger/show.blade.php`
    - `app/Livewire/Ledger/LedgerDiffViewer.php`
    - `resources/views/livewire/ledger/ledger-diff-viewer-placeholder.blade.php` (新規)
    - `resources/views/livewire/common/activity-history-display.blade.php` (既存)
    - `resources/views/livewire/common/permission-display.blade.php`

#### RF-6.3: スケルトンの要素サイズ引き継ぎ改善
- [x] **コンテンツエリアへの min-h 追加**
  - 問題: スケルトン表示時にレイアウトシフトが発生
  - 対策: 各画面のメインコンテンツエリアに適切な `min-h-[既存の高さ]` クラスを追加（diff viewer 系は実装済み 300px を採用し、他タブは 400px を維持）
  - 対象ファイル:
    - `resources/views/livewire/ledger/show.blade.php`（各タブコンテンツ: `min-h-[400px]`、diff viewer 埋め込み部のみ `min-h-[300px]` 実装）
    - `resources/views/livewire/ledger/ledger-history-manager.blade.php`（`min-h-[400px]`）
    - `resources/views/livewire/ledger/ledger-diff-viewer.blade.php`（`min-h-[300px]`）
    - `resources/views/livewire/ledger/ledger-diff-viewer-placeholder.blade.php`（`min-h-[300px]`）
    - `resources/views/livewire/attached-file/file-inspector.blade.php`（`min-h-[400px]`）
    - `resources/views/livewire/common/activity-history-display.blade.php`（`min-h-[400px]`）
    - `resources/views/livewire/common/permission-display.blade.php`（`min-h-[400px]`）

#### RF-6.4: Tier 2 適用の最終確認
- [x] **台帳リスト画面の再確認**
  - 検証: `records-table.blade.php` の各 `<x-element.loading-overlay>` が適切な `tier="2"` と `target` を持つか確認
  - 結果: 既に適切に設定されていることを確認
  - 修正: Tier 1 使用箇所（`appWithDrawer.blade.php`, `pending-list.blade.php`, `create-column.blade.php`, `modify-column.blade.php`）は計画通り

#### RF-6.5: 親子コンポーネント通信の最適化 (2026-01-31 追加)
- [x] **問題**: 親コンポーネントの操作時に、親のリクエスト完了後に子のリクエストが別途走るため、親側のスケルトンが一瞬で消えて古い子のコンテンツが残る（チラつき）。
- [x] **対策**: Livewire 3 の `#[Reactive]` 属性を導入し、プロパティ変更を単一のHTTPリクエストに集約。
- [x] **安全性**: `CannotMutateReactivePropException` 対策として、複雑なコレクションの防御的クローン作成や、内部状態変更の多いコンポーネントにおけるイベント同期の併用を実施。

### 期待される効果
1. **視覚的な一貫性**: すべてのローディング状態が予測可能で、適切な範囲に表示される
2. **レイアウト安定性**: スケルトン表示時のレイアウトシフトを最小化
3. **ユーザー体験の向上**: 「何が起きているか」が常に明確で、不自然な動作がない
4. **コードの保守性**: ローディング表示のパターンが統一され、今後の追加・修正が容易

## 5. 関連リソース
- **Issue #53**: [UI/UX: 全システムにおけるローディング表現の統一・洗練](https://github.com/torinky/LedgerLeap/issues/53)
- **UI調整・実施記録**: [Issue #53: 台帳リスト画面の UI 調整とローディング改善](2026-01-25_issue-53-loading-ui-adjustments.md)
- **是正作業レポート**: [Phase 6: ローディングUI是正作業レポート (不具合修正・通信最適化の詳細)](2026-01-29_phase6-remediation-report.md)
