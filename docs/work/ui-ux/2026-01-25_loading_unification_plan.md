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

## 4. WBS (Work Breakdown Structure)

### フェーズ1: 基盤整備 (Common Components & Layouts)
- [x] **CP-1.1**: `x-element.loading-overlay` の実装
- [x] **CP-1.2**: 汎用スケルトン部品の整備
  - [x] `skeleton-card`, `skeleton-row`
- [x] **CP-1.3**: レイアウトへのグローバル・プログレスバー導入
- [x] **CP-1.4**: UX 微調整用 CSS (チラツキ防止・アニメーション洗練)

### フェーズ2: 主要画面のリフレッシュ (リファクタリング)
- [x] **RF-2.1: リスト系画面**
  - [x] `RecordsTable` のドット廃止と Tier 1 スケルトン導入。
  - [x] `records-table.blade.php` のローディング範囲細分化（検索・移動・抽出）。
  - [x] 検索バーの独立化と `wire:target` によるターゲット限定。
  - [x] フォルダパネル・リストエリアへの詳細スケルトン（グリッド・テーブル）の配置。
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
- [ ] **QA-4.2**: ネットワーク遅延下での視覚的・操作的フィール確認。

## 5. 関連リソース
- **Issue #53**: [UI/UX: 全システムにおけるローディング表現の統一・洗練](https://github.com/torinky/LedgerLeap/issues/53)
