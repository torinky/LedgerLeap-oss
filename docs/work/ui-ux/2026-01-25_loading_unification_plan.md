# ローディング表現の全域統一化計画 (Issue #53)

## 1. 目的
LedgerLeap の Livewire ビュー全域において、現在混在しているローディング表現（スピナー、スケルトン、ドット、ボール、透過表示）を標準化し、システム全体で最高品質のデザイン一貫性と操作フィードバックを実現する。

## 2. デザイン標準（3ティア方式）

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
| **全体リスト (RecordsTable)** | `loading-dots` (固定オーバーレイ) | Tier 1 | 最優先。背景にスケルトン導入 |
| **詳細プレビュー (DiffViewer)** | `loading-dots` / スケルトン | Tier 1 | 実装済みだが他と合わせる |
| **ファイルインスペクター** | 独自スケルトン / `loading-spinner` | Tier 1 | 共通コンポーネントへ置換 |
| **台帳定義 (ModifyColumn)** | `loading-dots` | Tier 1 | 一覧/詳細と同じ背景処理へ |
| **タグ設定 (LedgerDefine)** | `loading-ball` | Tier 3 | 特殊表現の廃止。スピナーへ |
| **各種ボタン (Workflow等)** | MaryUI `spinner` | Tier 3 | 現状維持かつ適用漏れを補填 |
| **検索/フィルタ (Activity等)** | `x-mary-loading` | Tier 2 | 表示位置とサイズを Tier 2 基準へ |

## 4. WBS (Work Breakdown Structure)

### フェーズ1: 基盤整備 (Common Components)
- [ ] **CP-1.1**: `x-element.loading-overlay` の実装
  - `target` (wire:target), `tier` (1 or 2), `message` を引数に取る。
- [ ] **CP-1.2**: 汎用スケルトン部品の整備
  - `x-element.skeleton-row`, `x-element.skeleton-card` 等。
- [ ] **CP-1.3**: UX 微調整用 CSS
  - 短時間通信時の「チラツキ」防止用のトランジション。

### フェーズ2: 主要画面のリフレッシュ (リファクタリング)
- [ ] **RF-2.1: リスト系画面**
  - [ ] `RecordsTable` のドット廃止と Tier 1 スケルトン導入。
  - [ ] `records-table.blade.php` の `wire:loading` オーバーレイ刷新。
- [ ] **RF-2.2: 詳細画面周辺**
  - [ ] `show.blade.php` の初期読込・タブ遷移の洗練。
  - [ ] `LedgerHistoryManager` のスケルトン共通化。
- [ ] **RF-2.3: ファイルインスペクター**
  - [ ] `skeleton.blade.php` の再設計。
  - [ ] プレビュー、コンテンツ、履歴の各タブ内読み込みを Tier 2 基準へ。

### フェーズ3: 管理・設定画面の統一
- [ ] **RF-3.1: 台帳定義システム**
  - [ ] `ModifyColumn` 等の重い処理のローディング表現更新。
  - [ ] 特殊な `loading-ball` 等の完全排除。
- [ ] **RF-3.2: モーダル・ドロワー全般**
  - [ ] `WorkflowAssigneeModal`, `RollbackConfirmModal` 等のボタン内スピナー整合。

### フェーズ4: 検証とクリーンアップ
- [ ] **QA-4.1**: 全コードベースから `loading-dots`, `loading-ball` の検索・絶滅確認。
- [ ] **QA-4.2**: ネットワーク遅延下での視覚的・操作的フィール確認。

## 5. 関連リソース
- **Issue #53**: [UI/UX: 全システムにおけるローディング表現の統一・洗練](https://github.com/torinky/LedgerLeap/issues/53)
