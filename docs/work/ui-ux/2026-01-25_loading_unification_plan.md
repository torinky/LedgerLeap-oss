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
  - [x] `Import`, `RollbackConfirmModal` への Tier 2 オーバーレイ導入。
  - [x] `PermissionDisplay`, `ActivityHistoryDisplay` のフィルタリング操作に Tier 2 導入。

### フェーズ4: 検証とクリーンアップ
- [x] **QA-4.1**: 全コードベースから `loading-dots`, `loading-ball` の検索・絶滅確認。
- [x] **QA-4.2**: ネットワーク遅延下での視覚的・操作的フィール確認。

### フェーズ5: コンポーネント間連携の強化 (完了: 2026-01-28)
- [x] **CP-5.1**: レイアウトへの「手動制御可能」なグローバル・オーバーレイの設置
- [x] **CP-5.2**: ドロワー（Tree）操作開始時のブラウザイベント発火実装
- [x] **CP-5.3**: RecordsTable 更新完了時のオーバーレイ解除ロジックの実装
- [x] **CP-5.4**: 二重表示防止のための既存ローカルオーバーレイの整理

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
- [x] **タブ切り替えローディングの適切な実装**
  - 問題: `<x-element.loading-overlay tier="2" target="selectedTab,switchSource,searchKeyword" />` の範囲が広すぎる
  - 対策: タブ切り替え専用とコンテンツ操作用に分離し、適切な位置に配置
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
  - 問題: `activity`, `permissions` タブにスケルトンがない
  - 対策: `ledger-history-manager.blade.php` パターン（`wire:loading` + `<x-element.skeleton-list>`）を適用
  - ファイル: 
    - `resources/views/livewire/ledger/show.blade.php`
    - `resources/views/livewire/common/activity-history-display.blade.php`
    - `resources/views/livewire/common/permission-display.blade.php`

#### RF-6.3: スケルトンの要素サイズ引き継ぎ改善
- [x] **コンテンツエリアへの min-h 追加**
  - 問題: スケルトン表示時にレイアウトシフトが発生
  - 対策: 各画面のメインコンテンツエリアに適切な `min-h-[既存の高さ]` クラスを追加
  - 対象ファイル:
    - `resources/views/livewire/ledger/show.blade.php`（各タブコンテンツ: `min-h-[400px]`）
    - `resources/views/livewire/ledger/ledger-history-manager.blade.php`
    - `resources/views/livewire/ledger/ledger-diff-viewer.blade.php`
    - `resources/views/livewire/attached-file/file-inspector.blade.php`
    - `resources/views/livewire/common/activity-history-display.blade.php`
    - `resources/views/livewire/common/permission-display.blade.php`

#### RF-6.4: Tier 2 適用の最終確認
- [x] **台帳リスト画面の再確認**
  - 検証: `records-table.blade.php` の各 `<x-element.loading-overlay>` が適切な `tier="2"` と `target` を持つか確認
  - 結果: 既に適切に設定されていることを確認
  - 修正: Tier 1 使用箇所（`appWithDrawer.blade.php`, `pending-list.blade.php`, `create-column.blade.php`, `modify-column.blade.php`）は計画通り

### 期待される効果
1. **視覚的な一貫性**: すべてのローディング状態が予測可能で、適切な範囲に表示される
2. **レイアウト安定性**: スケルトン表示時のレイアウトシフトを最小化
3. **ユーザー体験の向上**: 「何が起きているか」が常に明確で、不自然な動作がない
4. **コードの保守性**: ローディング表示のパターンが統一され、今後の追加・修正が容易

## 5. 関連リソース
- **Issue #53**: [UI/UX: 全システムにおけるローディング表現の統一・洗練](https://github.com/torinky/LedgerLeap/issues/53)
