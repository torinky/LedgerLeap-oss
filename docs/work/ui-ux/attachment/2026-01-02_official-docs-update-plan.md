# 添付ファイルUI改善 - 公式ドキュメント更新計画

**作成日:** 2026年1月2日  
**作成者:** AI開発アシスタント  
**対象:** docs/work/ui-ux/attachment実装完了後の公式ドキュメント反映  
**ステータス:** 📋 計画策定完了

---

## 1. 背景と目的

### 1.1 背景

Phase 1〜5（2025年12月13日〜2026年1月2日）にわたり実装された「添付ファイルUI改善: FileInspectorドロワー導入」が完了しました。この実装により、以下の機能が追加・改善されました：

- **FileInspectorドロワー**: 添付ファイルの詳細情報を統合表示する新UI
- **4タブ構成**: Content/Details/History/Permissions
- **VLM/OCR/Tika統合**: 複数エンジンの解析結果表示とソース切り替え
- **高度な処理状態管理**: 24パターンの処理状態に対応したUI分岐
- **権限管理とアクション**: 再処理、VLM再処理、ダウンロード制御
- **モデル拡張**: `attached_files`テーブルへの新カラム追加
- **非同期処理フロー**: VLM→OCR→Tikaの順次処理と状態管理

### 1.2 目的

作業ドキュメント（docs/work）に蓄積された実装知見を、docs以下の公式ドキュメントに適切に反映し、開発者が最新の実装状況を理解できるようにします。

---

## 2. 公式ドキュメント更新一覧

### 2.1 更新対象ドキュメント概要

**重要原則: Single Source of Truth（情報の一元化）**

公式ドキュメントでは、同じ情報を複数箇所に記載することを避け、各ドキュメントに明確な役割を持たせます。詳細は他ドキュメントへの相互参照で誘導します。

| # | ドキュメントパス | 更新種別 | 優先度 | 推定工数 | 主な役割 | 状態 |
|---|-----------------|---------|--------|---------|---------|------|
| 1 | `docs/function/Attachment.md` | 大規模更新 | 🔴 最高 | 2-3h | 機能概要（ユーザー視点） | ✅ 完了 |
| 2 | `docs/architecture/vlm-ocr-technology-selection.md` | 部分更新 | 🔴 高 | 1.5-2h | 技術選定理由とアーキテクチャ | ✅ 完了 |
| 3 | `docs/architecture/QueueProcessing.md` | 部分更新 | 🔴 高 | 1-1.5h | 非同期処理の設計思想 | 🔄 作業中 |
| 4 | `docs/services/Ledger/ColumnHtmlService.md` | 部分更新 | 🟡 中 | 0.5-1h | サービスクラス仕様 | 📋 計画中 |
| 5 | `docs/README.md` | 小規模更新 | 🟡 中 | 0.5h | プロジェクト概要 | 📋 計画中 |
| 6 | `docs/development/vlm-ocr.md` | 部分更新 | 🟡 中 | 1h | 開発者向けガイド | 📋 計画中 |
| 7 | `docs/database/schema.md` | 新規作成 | 🟢 低 | 2-3h | データベーススキーマ | 📋 計画中 |
| 8 | `docs/models/AttachedFile.md` | 部分更新 | 🟢 低 | 0.5-1h | モデル仕様（既存あり） | 📋 計画中 |
| 9 | `docs/architecture/file-processing-flow.md` | 新規作成 | 🟡 中 | 2-3h | ファイル処理フロー詳細 | 📋 計画中 |
| 10 | `.github/copilot-instructions.md` | 小規模更新 | 🟡 中 | 0.5h | GitHub Copilot設定 | 📋 計画中 |
| 11 | `docs/development/performance-optimization.md` | 新規作成 | 🟡 中 | 2-3h | パフォーマンス最適化（開発者向け） | 📋 計画中 |
| 12 | `docs/operations/fileinspector-performance-monitoring.md` | 部分更新 | 🟡 中 | 1h | パフォーマンス監視（運用者向け） | 📋 計画中 |

**合計推定工数:** 15-22時間（重複削減により3-4時間短縮）  
**実績工数:** 約3時間（#1: 1.5h, #2: 1.5h）

**重複回避による変更:**
- ドキュメント#1（function/Attachment.md）から技術詳細を大幅削減（architecture/へ移管）
- ドキュメント#11と#12を役割分担（開発者向け/運用者向け）

---

## 3. 詳細更新計画

### 重要原則：ドキュメント間の重複回避

公式ドキュメントは **Single Source of Truth** 原則に基づき、以下のように役割を明確に分離します：

| ドキュメント種別 | 役割 | 記載すべき内容 | 記載してはいけない内容 |
|---------------|------|--------------|-------------------|
| **function/** | 機能の概要とユーザー視点の使い方 | UI操作、機能一覧、データフロー概要 | 実装詳細、技術選定理由、コード例 |
| **architecture/** | アーキテクチャと技術選定 | 技術選定理由、処理フロー詳細、システム設計 | UI操作方法、運用手順 |
| **models/** | モデル仕様とAPI | カラム定義、メソッド一覧、リレーション | ビジネスロジック、UI連携 |
| **operations/** | 運用・設定ガイド | 環境設定、監視方法、トラブルシューティング | 実装方法、開発手順 |
| **development/** | 開発者向けガイド | 実装手順、コード例、ベストプラクティス | 運用設定、ユーザー操作 |

**相互参照ルール:**
- 詳細は他ドキュメントへのリンクで誘導
- 各ドキュメントは独立して理解可能な範囲でまとめる
- 同じ図表を複数箇所に配置しない（1箇所のみ配置し、他は参照）

---

### 3.1 最優先（🔴 Priority: Critical）

#### ドキュメント#1: `docs/function/Attachment.md`

**ドキュメントの役割:**
- **主な読者:** LedgerLeapの機能を理解したいユーザー、新規開発者、PM
- **記載範囲:** 添付ファイル機能の概要、UI操作方法、データフロー概要
- **記載しない内容:** 技術選定理由（architecture/）、実装詳細（development/）、モデル仕様（models/）

**現状の問題点:**
- FileInspectorドロワーの記載が一切ない
- 処理フローが古い（VLM統合前）
- 技術詳細とユーザー向け説明が混在している

**更新方針:**
1. **技術詳細を削除し、architecture/へ移管**
   - VLM/OCR/Tikaの技術選定理由 → `architecture/vlm-ocr-technology-selection.md`
   - 非同期ジョブの実装詳細 → `architecture/QueueProcessing.md`
   - エンジン統合アーキテクチャ → `architecture/file-processing-flow.md`（新規作成）

2. **モデル詳細を削除し、models/へ移管**
   - `attached_files`テーブルの全カラム定義 → `models/AttachedFile.md`（既存）
   - メソッド一覧、リレーション → `models/AttachedFile.md`

3. **運用情報を削除し、operations/へ移管**
   - パフォーマンス測定設定 → `operations/fileinspector-performance-monitoring.md`（既存）
   - メトリクス追加方法 → `development/performance-optimization.md`（新規作成）

**更新内容（簡潔化）:**

| セクション | 更新内容 | 記載内容（簡潔に） | 詳細リンク先 |
|-----------|---------|------------------|-------------|
| **1. 概要** | 既存維持 | 機能のハイライト5項目 | - |
| **2. データフロー** | 簡潔化 | アップロード→処理→表示の流れ（Mermaid図） | `architecture/file-processing-flow.md` |
| **3. 機能詳細** | 大幅更新 | - | - |
| 3.1 ファイル保存パス | 既存維持 | パス構造の概要のみ | `models/AttachedFile.md` |
| 3.2 FileInspectorドロワー | **新規追加** | 4タブの概要、開閉方法 | - |
| 3.3 セキュアなダウンロード | 既存維持（簡潔化） | 権限チェックの概要 | `architecture/security.md` |
| 3.4 テキスト抽出 | 簡潔化 | エンジン3種の役割と優先順位の概要 | `architecture/vlm-ocr-technology-selection.md` |
| 3.5 処理ステータス | 既存維持（簡潔化） | ステータス一覧、再処理機能の概要 | - |
| 3.6 ファイル削除 | 既存維持 | 論理削除の仕組み | - |
| **4. データモデル** | **削除** | - | `models/AttachedFile.md` |
| **5. 関連ドキュメント** | 更新 | リンク一覧の整理 | - |

**削除すべき詳細情報:**
- ❌ `attached_files`テーブルの全カラム定義（4.1節）→ `models/AttachedFile.md`へ
- ❌ `ledgers.content_attached`の詳細構造（4.2節）→ `models/Ledger.md`へ
- ❌ VLM/OCR/Tikaの技術詳細 → `architecture/vlm-ocr-technology-selection.md`
- ❌ 非同期ジョブの実装詳細 → `architecture/QueueProcessing.md`
- ❌ パフォーマンス測定の詳細 → `operations/fileinspector-performance-monitoring.md`

**追加すべき図表（最小限）:**
- ✅ データフロー概要図（簡潔版）
- ✅ FileInspectorドロワーのスクリーンショット（1枚、4タブ表示）
- ✅ 処理ステータス一覧表（簡易版）

**リンク先ドキュメント:**
- `architecture/vlm-ocr-technology-selection.md` - VLM/OCR技術選定
- `architecture/QueueProcessing.md` - 非同期ジョブフロー
- `architecture/file-processing-flow.md` - ファイル処理フロー詳細（新規）
- `models/AttachedFile.md` - AttachedFileモデル仕様
- `operations/fileinspector-performance-monitoring.md` - パフォーマンス監視
- `development/performance-optimization.md` - 開発者向けガイド（新規）

---

#### ドキュメント#2: `docs/architecture/vlm-ocr-technology-selection.md`

**ドキュメントの役割:**
- **主な読者:** 技術選定を理解したいアーキテクト、エンジニアリーダー
- **記載範囲:** VLM/OCR技術の選定理由、評価基準、採用技術の詳細
- **記載しない内容:** UI操作方法（function/）、運用設定（operations/）、実装手順（development/）

**現状の問題点:**
- 実装完了したPaddleOCR-VL統合の成果が未反映
- 計画段階の記述が多い

**更新内容:**

| セクション | 更新内容 | 参照元ドキュメント |
|-----------|---------|------------------|
| **2. 実装状況** | セクション新規追加 | - |
| 2.1 Phase 1-5完了サマリー | 実装完了内容の要約（2025年12月-2026年1月） | phase4-6_completion_summary.md |
| 2.2 PaddleOCR-VL採用実績 | 選定理由、実装アーキテクチャ | attachment-ui-improvement-plan.md |
| **3. 実測ベンチマーク** | セクション新規追加または更新 | - |
| 3.1 処理時間の実測値 | VLM/OCR/Tikaの処理時間比較 | phase4-6-5_performance_report.md |
| 3.2 精度評価 | VLM信頼度スコアの分布 | phase5_feature_comparison_report.md |
| **4. アーキテクチャ詳細** | 更新 | - |
| 4.1 エンジン統合フロー | VLM→OCR→Tika→Finalize処理フロー図 | phase3_detailed_plan.md |
| 4.2 エンジン選択ロジック | 優先順位とフォールバック戦略 | phase5_feature_comparison_report.md |
| **5. 今後の展望** | 既存維持 | - |

**削除すべき内容:**
- ❌ UI操作方法 → `function/Attachment.md`へ
- ❌ 運用設定手順 → `operations/`へ
- ❌ 具体的な実装コード → `development/`へ

**推定工数:** 1.5-2時間

---

#### ドキュメント#3: `docs/architecture/QueueProcessing.md`

**ドキュメントの役割:**
- **主な読者:** 非同期処理を理解したいエンジニア
- **記載範囲:** キューの設計思想、ジョブフロー、エラーハンドリング戦略
- **記載しない内容:** 具体的な実装コード（development/）、運用監視（operations/）

**現状の問題点:**
- VLM統合後のジョブフローが未反映
- 新ジョブクラスの説明がない

**更新内容:**

| セクション | 更新内容 | 参照元ドキュメント |
|-----------|---------|------------------|
| **2. 主要コンポーネント** | ジョブフロー図を更新 | - |
| **3. 添付ファイル処理ジョブ** | セクション新規追加または更新 | phase3_detailed_plan.md |
| 3.1 ProcessAttachedFile | 初期処理（Tika抽出） | - |
| 3.2 ProcessVlmExtraction | VLM処理（新規追加） | - |
| 3.3 OcrAndOptimizeFile | OCR処理 | - |
| 3.4 FinalizeAttachedFileProcessing | 最終化処理（新規追加） | phase2_model_extension_plan.md |
| **4. ジョブチェーン戦略** | セクション新規追加 | - |
| 4.1 並列処理とディレイ | VLM/OCRの並列ディスパッチ、2秒ディレイの理由 | phase3_detailed_plan.md |
| 4.2 エラーハンドリング | リトライ戦略、フォールバック | phase3_detailed_plan.md |
| **5. 関連ドキュメント** | リンク更新 | - |

**削除すべき内容:**
- ❌ 実装コード例 → `development/queue-jobs.md`（新規作成候補）
- ❌ 監視・トラブルシューティング → `operations/queue-monitoring.md`

**推定工数:** 1-1.5時間

---

### 3.2 高優先度（🟡 Priority: High）

#### ドキュメント#4: `docs/services/Ledger/ColumnHtmlService.md`

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **3.2 添付ファイル型** | FileInspectorボタン生成ロジック追加 |
| **4. 非推奨機能** | 旧VLMモーダル生成メソッド削除の記録 |

**推定工数:** 0.5-1時間

---

#### ドキュメント#5: `docs/README.md`

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **プロジェクト概要** | FileInspector機能追加の記載 |
| **LedgerLeapの特徴と機能** | VLM/OCR/Tika統合の説明強化 |
| **UIフレームワーク** | Livewireドロワーコンポーネントの記載 |

**推定工数:** 0.5時間

---

#### ドキュメント#6: `docs/development/vlm-ocr.md`

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **3. 開発者ガイド** | FileInspectorのデバッグ方法 |
| **4. トラブルシューティング** | VLM処理失敗時の対処法 |
| **5. テストデータ** | モックデータ12種類の説明 |

**推定工数:** 1時間

---

#### ドキュメント#10: `.github/copilot-instructions.md`

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **重要な実装教訓** | FileInspector開発での知見追加 |
| - Livewireドロワー | ドロワー状態管理パターン |
| - VLM/OCR統合 | 非同期処理とUI同期のベストプラクティス |
| - パフォーマンス | Eager Loading、キャッシング戦略 |
| - 開発環境 | npm run build必須（開発環境のオーバーヘッド） |

**推定工数:** 0.5時間

---

### 3.3 中優先度（🟢 Priority: Medium）

#### ドキュメント#11: `docs/development/performance-optimization.md`（新規作成）

**ドキュメントの役割:**
- **主な読者:** 新しいパフォーマンス測定を追加したい開発者
- **記載範囲:** 測定の基本原則、実装パターン、コード例、ベストプラクティス
- **記載しない内容:** 運用設定（operations/）、アーキテクチャ設計（architecture/）

**目的:** WBS 5.2で得られたパフォーマンス最適化の知見を開発者向けに体系化

**構成:**

```markdown
# パフォーマンス最適化ガイド（開発者向け）

## 1. 測定の基本原則

### 1.1 必ず本番モードで測定
**重要な教訓（WBS 5.2実証）:**
- `npm run build`で測定すること（`npm run dev`は非現実的に遅い）
- 理由: Viteの HMRオーバーヘッド、Alpine.jsの初期化遅延

### 1.2 問題の切り分け
- フロントエンド問題（JavaScript/Alpine.js） → npm run buildで改善
- サーバーサイド問題（Livewire/Laravel） → npm run buildでは改善しない

## 2. 新しいメトリクスの追加方法（7ステップ）

### Step 1: config/ledgerleap.php に追加
### Step 2: .env に環境変数を追加
### Step 3: フロントエンドに測定コードを追加
### Step 4: Livewireメソッドで受信（既存のlogPerformance利用）
### Step 5: 閾値アラートの追加（オプション）
### Step 6: メトリクスをドキュメント化
### Step 7: テストとバリデーション

## 3. Livewire最適化パターン

### 3.1 Eager Loading（N+1問題回避）
### 3.2 Computed Properties（キャッシング）
### 3.3 wire:ignore の適切な使用

**⚠️ 重要な教訓（WBS 5.2実証）:**
- MaryUIコンポーネントには`wire:ignore`を使用しない
- 理由: `wire:model`、clearable、money、エラー表示等が破壊される
- 成功例: サードパーティライブラリ（Chart.js等）のみ

### 3.4 Livewireレンダリングの最適化
- 測定結果: キーワード検索1500ms（サーバー処理0ms + レンダリング1500ms）
- 判断: デバウンス1000msで現状維持（wire:ignoreは使わない）

## 4. フロントエンド最適化

### 4.1 Alpine.jsのベストプラクティス
### 4.2 画像プレビューの最適化（実装例）

## 5. 実測ベンチマーク（WBS 5.2実績）

| 項目 | npm run dev | npm run build | 改善 |
|-----|------------|--------------|------|
| フォーカス応答 | 数秒 | 即座 | ✅ 劇的 |
| 画像プレビュー | 遅い | 143ms | ✅ 実用的 |
| UIブロック | 頻発 | なし | ✅ 解消 |
```

**推定工数:** 2-3時間

**参照元:**
- wbs5.2-performance-improvement/npm_build_improvement_analysis.md
- wbs5.2-performance-improvement/livewire3_optimization_investigation.md
- phase4-6-5_performance_report.md

---

#### ドキュメント#12: `docs/operations/fileinspector-performance-monitoring.md`（部分更新）

**ドキュメントの役割:**
- **主な読者:** システムの運用・監視を担当する運用エンジニア
- **記載範囲:** 環境設定、監視方法、ログ分析、トラブルシューティング
- **記載しない内容:** 実装コード（development/）、設計思想（architecture/）

**既存ドキュメント:** Phase 4.6.5で作成済み（運用ガイド）

**追加内容:**

| セクション | 更新内容 | 参照元ドキュメント |
|-----------|---------|------------------|
| **3. 測定結果の記録** | 新規追加 | - |
| 3.1 npm run build前後の比較 | WBS 5.2実測結果 | npm_build_improvement_analysis.md |
| 3.2 メトリクス実績値 | search_render、image_preview_load等 | phase4-6-5_performance_report.md |
| **4. トラブルシューティング** | 更新 | - |
| 4.1 遅延が見られる場合 | npm run buildの確認手順追加 | - |
| 4.2 wire:ignore失敗事例 | MaryUIとの相性問題の記録 | livewire3_optimization_investigation.md |
| **5. 将来の拡張** | 新規追加 | - |
| 5.1 他コンポーネントへの展開 | 測定インフラの再利用方法 | - |
| 5.2 閾値アラートの設定 | Slack通知等への拡張ガイド | - |

**削除すべき内容:**
- ❌ 実装コード例 → `development/performance-optimization.md`へ
- ❌ 7ステップガイド → `development/performance-optimization.md`へ

**推定工数:** 1時間

---

#### ドキュメント#4: `docs/services/Ledger/ColumnHtmlService.md`

**ドキュメントの役割:**
- **主な読者:** サービスクラスの実装を理解したい開発者
- **記載範囲:** サービスメソッドの役割、引数、戻り値
- **記載しない内容:** UI詳細（function/）、設計思想（architecture/）

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **3.2 添付ファイル型** | FileInspectorボタン生成ロジック追加 |
| **4. 非推奨機能** | 旧VLMモーダル生成メソッド削除の記録 |

**推定工数:** 0.5-1時間
# パフォーマンス最適化ガイド

## 1. 測定の基本原則

### 1.1 必ず本番モードで測定
**重要:** パフォーマンス測定は必ず`npm run build`で実施

**理由:**
- `npm run dev`（開発モード）にはViteのHMRオーバーヘッドが含まれる
- Alpine.jsの初期化、イベント処理が開発モードでは著しく遅い
- 開発環境の遅さを本質的な問題と誤認する危険性

**実測例（WBS 5.2）:**
| 項目 | npm run dev | npm run build | 差分 |
|-----|------------|--------------|------|
| フォーカス応答 | 数秒 | 即座 | 劇的改善 |
| 画像プレビュー | 遅い | 143ms | 実用的 |
| UIブロック | 頻発 | なし | 解消 |

### 1.2 問題の切り分け

**フロントエンド問題（JavaScript/Alpine.js）:**
- ユーザーインタラクションの応答性
- DOM操作の速度
- イベントハンドリング
→ **npm run buildで大幅改善**

**サーバーサイド問題（Livewire/Laravel）:**
- Livewireのレンダリング時間
- データベースクエリ
- キャッシュ戦略
→ **npm run buildでは改善しない**

## 2. Livewire最適化パターン

### 2.1 Eager Loading（N+1問題回避）

**悪い例:**
```php
// FileInspector.php
public function mount($fileId)
{
    $this->file = AttachedFile::find($fileId);
    // N+1発生: activities, ledger, ledger.ledgerDefine等
}
```

**良い例:**
```php
public function mount($fileId)
{
    $this->file = AttachedFile::with([
        'ledger.ledgerDefine',
        'activities.causer',
    ])->find($fileId);
}
```

**効果:** クエリ数が6-7回 → 2-3回に削減

### 2.2 Computed Properties（キャッシング）

**実装:**
```php
use Livewire\Attributes\Computed;

#[Computed]
public function previewText()
{
    return $this->getPreviewText();
}
```

**効果:** 同じリクエスト内で複数回呼び出しても1回のみ実行

### 2.3 wire:ignore の適切な使用

**推奨:** 慎重に使用する

**成功例:** サードパーティライブラリ（Chart.js等）
```blade
<div wire:ignore>
    <canvas id="chart"></canvas>
</div>
```

**失敗例:** MaryUIコンポーネント（WBS 5.2実証）
```blade
<div wire:ignore>
    <x-mary-input wire:model="search" /> <!-- 動作しなくなる -->
</div>
```

**理由:**
- MaryUIは`wire:model`に依存
- clearable、money、エラー表示等の機能が破壊される

### 2.4 Livewireレンダリングの最適化

**測定結果（WBS 5.2）:**
- キーワード検索: サーバー処理0ms + レンダリング1500ms
- **レンダリングがボトルネック**

**対策検討結果:**
- wire:ignore → MaryUIとの相性が悪い（実証済み）
- Alpine.js化 → 実装コストが高い
- コンポーネント分割 → 複雑すぎる
- **判断:** デバウンス1000msで現状維持（体感許容範囲）

## 3. フロントエンド最適化

### 3.1 Alpine.jsのベストプラクティス

**データの初期化:**
```blade
<div x-data="{
    isOpen: @entangle('drawerOpen'),
    activeTab: @entangle('activeTab')
}">
```

**避けるべきパターン:**
```blade
<!-- ❌ 重い処理を x-data内で実行 -->
<div x-data="{ results: {{ json_encode($this->heavyCalculation()) }} }">
```

### 3.2 画像プレビューの最適化

**実装（WBS 5.2）:**
```blade
<img 
    @load.once="
        endTime = performance.now();
        $wire.logPerformance('image_preview_load', {
            duration_ms: endTime - startTime
        });
    "
/>
```

**測定結果:** 143ms（許容範囲）

## 4. データベース最適化

### 4.1 全文検索（Mroonga）

**制約:** 複合インデックス不可
```sql
-- ❌ 動作しない
SELECT * FROM attached_files 
WHERE MATCH(content, vlm_source_text) AGAINST('keyword');

-- ✅ 正しい
SELECT * FROM attached_files 
WHERE MATCH(content) AGAINST('keyword')
   OR MATCH(vlm_source_text) AGAINST('keyword');
```

### 4.2 インデックス戦略

**attached_filesテーブル:**
- PRIMARY KEY: id
- INDEX: ledger_id（外部キー）
- FULLTEXT INDEX: content（Mroonga）
- UNIQUE INDEX: (ledger_id, column_id, hashedbasename)

## 5. 非同期処理の最適化

### 5.1 ジョブチェーン

**VLM→OCR→Tika→Finalize:**
```php
ProcessVlmForAttachedFile::dispatch($file)
    ->chain([
        new ProcessOcrForAttachedFile($file),
        new ProcessTikaForAttachedFile($file),
        new FinalizeAttachedFileProcessing($file),
    ]);
```

**タイムアウト設定:**
- VLM: 60秒
- OCR: 120秒（画像の場合）
- Tika: 30秒

## 6. 実測ベンチマーク（Phase 5実績）

### 6.1 FileInspectorのパフォーマンス

| 項目 | 目標 | 実績 | 達成率 |
|-----|------|------|--------|
| フォーカス応答 | 即座 | 即座 | ✅ 100% |
| 画像プレビュー | <200ms | 143ms | ✅ 100% |
| UIブロック | なし | なし | ✅ 100% |
| タブ切り替え | <100ms | 7-140ms | ✅ 90% |
| ドロワー開閉 | <300ms | 1600-2500ms | △ 20% |
| キーワード検索 | <500ms | 1500ms | ⚠️ 維持 |

### 6.2 教訓

**解決できた問題（4項目）:**
- フォーカス遅延 → npm run build
- 画像プレビュー → npm run build + ログ修正
- UIブロック → npm run build
- PHP 8.4警告 → vendorファイル修正

**現状維持とした問題（1項目）:**
- キーワード検索1500ms
  - 理由: wire:ignoreで表示が壊れた（実証済み）
  - 判断: デバウンス1000msで体感許容範囲

## 7. パフォーマンス測定ガイド

### 7.1 測定の前提条件

**⚠️ 重要な制約:**
- Livewireテストでは実際のJavaScriptが実行されない
- Alpine.jsコードが動作しないため、**実ブラウザでの測定が必須**
- 開発環境では必ず`npm run build`を実行してから測定

### 7.2 測定ツールとインフラ

#### フロントエンド測定（Performance API）

**基本パターン:**
```javascript
// Alpine.jsでの測定
<div x-data="{
    measurePerformance() {
        const startTime = performance.now();
        
        // 測定対象の処理
        
        const duration = performance.now() - startTime;
        
        // ログ送信
        $wire.logPerformance('metric_name', {
            duration_ms: duration,
            // 追加メタデータ
            file_id: this.fileId,
            context: 'additional_info'
        });
    }
}">
```

**イベントベース測定:**
```blade
<img 
    x-data="{ startTime: null }"
    x-init="startTime = performance.now()"
    @load.once="
        const duration = performance.now() - startTime;
        $wire.logPerformance('image_load', {
            duration_ms: duration,
            url: $el.src
        });
    "
/>
```

#### バックエンド受信（Livewire）

**FileInspector.php の実装:**
```php
use Illuminate\Support\Facades\Log;

public function logPerformance(string $metric, array $data): void
{
    $logData = array_merge([
        'metric' => $metric,
        'user_id' => auth()->id(),
        'timestamp' => now()->toIso8601String(),
    ], $data);
    
    // Laravel標準ログ
    Log::channel('performance')->info($metric, $logData);
    
    // JSON統計ファイル（ローカル環境のみ）
    if (app()->environment('local')) {
        $statsFile = storage_path('logs/performance_stats.json');
        $stats = file_exists($statsFile) 
            ? json_decode(file_get_contents($statsFile), true) 
            : [];
        
        $stats[] = $logData;
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    }
}
```

#### ログチャンネル設定

**config/logging.php:**
```php
'channels' => [
    'performance' => [
        'driver' => 'daily',
        'path' => storage_path('logs/performance.log'),
        'level' => 'info',
        'days' => 7,
    ],
],
```

### 7.3 測定対象メトリクス

#### 必須メトリクス一覧

| メトリクス名 | 測定タイミング | 目標値 | 実装場所 |
|------------|--------------|--------|---------|
| `drawer_open` | ドロワー開閉時 | <300ms | file-inspector.blade.php |
| `tab_switch` | タブ切り替え時 | <100ms | file-inspector.blade.php |
| `search_render` | 検索実行時 | <500ms | content.blade.php |
| `image_preview_load` | 画像読み込み時 | <200ms | preview.blade.php |
| `database_queries` | Livewireリクエスト時 | <5回 | Laravel Telescope |

#### オプションメトリクス

- `vlm_processing_time`: VLM処理時間（ジョブ内）
- `ocr_processing_time`: OCR処理時間（ジョブ内）
- `tika_processing_time`: Tika処理時間（ジョブ内）
- `cache_hit_rate`: キャッシュヒット率

### 7.4 新しいメトリクスの追加方法

**前提:** 既存の測定インフラを活用（`docs/operations/fileinspector-performance-monitoring.md`参照）

#### Step 1: config/ledgerleap.php に新メトリクスを追加

```php
'performance' => [
    'enabled' => env('PERFORMANCE_MONITORING_ENABLED', env('APP_ENV') === 'local'),
    'log_destination' => env('PERFORMANCE_LOG_DESTINATION', 'both'),
    'metrics' => [
        'drawer_open' => env('PERFORMANCE_METRIC_DRAWER_OPEN', true),
        'tab_switch' => env('PERFORMANCE_METRIC_TAB_SWITCH', true),
        // 新規追加
        'search_render' => env('PERFORMANCE_METRIC_SEARCH_RENDER', false),
        'vlm_preview' => env('PERFORMANCE_METRIC_VLM_PREVIEW', false),
    ],
],
```

#### Step 2: .envに環境変数を追加

```dotenv
# 既存のメトリクス
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true

# 新規メトリクス（デフォルトfalseで追加）
PERFORMANCE_METRIC_SEARCH_RENDER=false
PERFORMANCE_METRIC_VLM_PREVIEW=false
```

#### Step 3: フロントエンドに測定コードを追加

**Bladeテンプレートでの条件分岐:**
```blade
@php
    $performanceEnabled = config('ledgerleap.performance.enabled', false);
    $searchMetricEnabled = config('ledgerleap.performance.metrics.search_render', false);
@endphp

@if($performanceEnabled && $searchMetricEnabled)
    <div x-data="{
        searchStartTime: null,
        
        measureSearch() {
            this.searchStartTime = performance.now();
        },
        
        logSearchComplete() {
            if (this.searchStartTime) {
                const duration = performance.now() - this.searchStartTime;
                $wire.logPerformance('search_render', {
                    duration_ms: duration,
                    keyword: this.keyword
                });
                this.searchStartTime = null;
            }
        }
    }"
    @search-started="measureSearch()"
    @search-completed="logSearchComplete()">
@else
    <div x-data="{}">
@endif
    <!-- 検索UI -->
</div>
```

**例: ボタンクリック時の処理時間を測定**

```blade
<button 
    @click="
        @if($performanceEnabled && config('ledgerleap.performance.metrics.button_click', false))
            const startTime = performance.now();
        @endif
        
        // 既存の処理
        await processData();
        
        @if($performanceEnabled && config('ledgerleap.performance.metrics.button_click', false))
            // 測定記録
            const duration = performance.now() - startTime;
            $wire.logPerformance('button_click_process', {
                duration_ms: duration,
                button_id: 'my-button'
            });
        @endif
    "
>
    処理実行
</button>
```

#### Step 4: Livewireメソッドで受信（既存のlogPerformance利用）

**FileInspector.phpの既存メソッドが自動対応:**
```php
public function logPerformance(string $metric, array $data): void
{
    // 測定機能が無効な場合は何もしない
    if (!config('ledgerleap.performance.enabled', false)) {
        return;
    }
    
    // メトリクスが無効な場合は何もしない
    if (!config("ledgerleap.performance.metrics.{$metric}", true)) {
        return;
    }
    
    // 既存のログ記録処理が実行される
    $logData = array_merge([
        'metric' => $metric,
        'timestamp' => now()->toIso8601String(),
    ], $data);
    
    // ログ出力
    $destination = config('ledgerleap.performance.log_destination', 'both');
    
    if (in_array($destination, ['log', 'both'])) {
        Log::channel('performance')->info($metric, $logData);
    }
    
    if (in_array($destination, ['json', 'both']) && app()->environment('local')) {
        $this->appendToJsonStats($logData);
    }
}
```

#### Step 5: 閾値アラートの追加（オプション）

```php
// FileInspector.phpまたは専用のPerformanceMonitorServiceに追加
protected function checkThreshold(string $metric, float $duration): void
{
    $thresholds = [
        'drawer_open' => 300,
        'tab_switch' => 100,
        'search_render' => 500,
        'vlm_preview' => 200,  // 新規追加
    ];
    
    if (isset($thresholds[$metric]) && $duration > $thresholds[$metric]) {
        Log::channel('performance')->warning("Performance threshold exceeded", [
            'metric' => $metric,
            'threshold' => $thresholds[$metric] . 'ms',
            'actual' => $duration . 'ms',
            'exceeded_by' => ($duration - $thresholds[$metric]) . 'ms',
        ]);
        
        // 通知サービスへの連携（オプション）
        // NotificationService::sendSlackAlert(...);
    }
}

// logPerformanceメソッドから呼び出し
public function logPerformance(string $metric, array $data): void
{
    // ... 既存のチェック ...
    
    if (isset($data['duration_ms'])) {
        $this->checkThreshold($metric, $data['duration_ms']);
    }
    
    // ... 既存のログ記録 ...
}
```

#### Step 6: メトリクスをドキュメント化

**operations/fileinspector-performance-monitoring.mdに追加:**
```markdown
| メトリクス | 説明 | 測定範囲 |
|----------|------|---------|
| search_render | キーワード検索のレンダリング時間 | 検索開始 → 結果表示完了 |
| vlm_preview | VLMプレビュー読み込み時間 | プレビュー開始 → Markdown表示完了 |
```

**測定結果記録テンプレートに追加:**
```markdown
#### キーワード検索時間

| 測定回 | キーワード | 時間（ms） |
|-------|----------|----------|
| 1     | "テスト"  |          |
| 2     | "日報"    |          |
```

#### Step 7: テストとバリデーション

```bash
# 1. 設定の確認
./vendor/bin/sail artisan tinker
> config('ledgerleap.performance.metrics.search_render')
=> true

# 2. 実測定
# ブラウザでChrome DevTools (F12) → Console
# 操作実施 → ログ確認

# 3. ログファイル確認
./vendor/bin/sail logs | grep "search_render"

# 4. JSON統計確認
./vendor/bin/sail exec laravel cat storage/logs/performance_stats.json | \
  jq '.[] | select(.metric == "search_render")'
```

#### 実装チェックリスト

- [ ] config/ledgerleap.phpに新メトリクス追加
- [ ] .envに環境変数追加（デフォルトfalse）
- [ ] Bladeテンプレートに測定コード追加（条件分岐付き）
- [ ] 閾値アラート設定（オプション）
- [ ] operations/fileinspector-performance-monitoring.md更新
- [ ] 実測定とログ確認
- [ ] 測定結果をphase4-6-5_performance_report.mdに記録
```

### 7.5 実測手順

#### 準備

```bash
# 開発環境起動
./vendor/bin/sail up -d

# 本番モードでビルド（必須）
./vendor/bin/sail npm run build

# ログファイルクリア
./vendor/bin/sail exec laravel rm -f storage/logs/performance_stats.json

# テストデータ準備
./vendor/bin/sail artisan db:seed
```

#### 測定実施

1. **Chrome DevToolsを開く** (F12)
2. **Consoleタブを選択**
3. **測定対象の操作を実行**（例: ドロワー開閉）
4. **コンソールログを確認**:
   ```
   [FileInspector Performance] Drawer open duration: 287.45 ms
   ```

#### データ収集

**方法1: コンソールログ**
- ブラウザコンソールから手動コピー

**方法2: JSON統計ファイル**
```bash
./vendor/bin/sail exec laravel cat storage/logs/performance_stats.json | jq '.'
```

**方法3: Laravelログ**
```bash
./vendor/bin/sail logs | grep "FileInspector Performance"
```

### 7.6 測定結果の記録テンプレート

#### ドロワー開閉時間

| 測定回 | 初回（ms） | 2回目（ms） | 3回目（ms） | 平均（ms） |
|-------|----------|-----------|-----------|----------|
| 1     |          |           |           |          |
| 2     |          |           |           |          |
| 3     |          |           |           |          |
| **平均** |          |           |           |          |

#### タブ切り替え時間

| タブ切り替え | 測定1（ms） | 測定2（ms） | 測定3（ms） | 平均（ms） |
|------------|-----------|-----------|-----------|----------|
| Content → Details |  |  |  |  |
| Details → History |  |  |  |  |
| History → Permissions |  |  |  |  |

**詳細な測定ガイド:** [Phase 4.6測定ガイド](../work/ui-ux/attachment/2025-12-30_phase4-6-measurement-guide.md)

## 8. トラブルシューティング

### 8.1 開発環境が遅い

**症状:** フォーカス、画像、UIが遅い

**確認:**
```bash
# 現在のモード確認
ps aux | grep vite

# 本番モードに切り替え
sail npm run build
```

### 8.2 Livewireのレンダリングが遅い

**症状:** 検索、ドロワー開閉が1秒以上かかる

**確認:**
1. Eager Loadingの確認
2. Computed Propertiesの活用
3. クエリ数の確認（Debugbarで）

**対策:**
- N+1問題の解消
- 不要なリレーションの削除
- キャッシュの活用

### 8.3 wire:ignoreが動作しない

**症状:** MaryUIコンポーネントの機能が壊れる

**原因:** MaryUIは`wire:model`に依存

**解決:** wire:ignoreを使わない代替案を検討
- Entangle使用
- Computed Properties活用
- または現状維持

## 9. 参考資料

### 9.1 公式ドキュメント

- [Livewire 3 Performance](https://livewire.laravel.com/docs/performance)
- [Alpine.js Performance](https://alpinejs.dev/advanced/performance)
- [Laravel Query Optimization](https://laravel.com/docs/eloquent-relationships#eager-loading)

### 9.2 プロジェクト内ドキュメント

- [WBS 5.2 完了レポート](../work/ui-ux/attachment/wbs5.2-performance-improvement/2025-12-31_phase5_completion_report.md)
- [npm build改善分析](../work/ui-ux/attachment/wbs5.2-performance-improvement/2025-12-31_npm_build_improvement_analysis.md)
- [Livewire3最適化調査](../work/ui-ux/attachment/wbs5.2-performance-improvement/2025-12-31_livewire3_optimization_investigation.md)
```

**参照元ドキュメント:**
- wbs5.2-performance-improvement/README.md
- wbs5.2-performance-improvement/2025-12-31_phase5_completion_report.md
- wbs5.2-performance-improvement/2025-12-31_wbs5-2_summary.md
- wbs5.2-performance-improvement/2025-12-31_npm_build_improvement_analysis.md
- wbs5.2-performance-improvement/2025-12-31_livewire3_optimization_investigation.md

**推定工数:** 2-3時間

---

#### ドキュメント#12: `docs/operations/fileinspector-performance-monitoring.md`（部分更新）

**現状:** Phase 4.6.5で作成済み（FileInspector測定機能の運用ガイド）

**更新内容:**

| セクション | 更新内容 |
|-----------|---------|
| **測定可能なメトリクス** | WBS 5.2の追加メトリクス反映 |
| - search_render | キーワード検索レンダリング時間（1500ms） |
| - image_preview_load | 画像プレビュー読み込み時間（143ms） |
| **ベンチマーク** | Phase 5実測値に更新 |
| - npm run buildによる改善結果追加 | フォーカス応答（数秒 → 即座） |
| - 問題解決4項目の記録 | フォーカス、画像、UIブロック、PHP8.4警告 |
| **設定方法** | 新規メトリクスの追加手順追加 |
| **トラブルシューティング** | WBS 5.2で判明した知見追加 |
| - npm run devによる誤測定の警告 | 「必ず本番モードで測定」を強調 |
| - wire:ignore失敗事例 | MaryUIとの相性問題の記録 |
| **将来の拡張** | 新規セクション追加 |
| - 他コンポーネントへの測定機能展開 | LedgerEdit、Search等 |
| - メトリクス追加ガイドライン | development/performance-optimization.md参照 |

**追加すべき測定結果テーブル:**

```markdown
### WBS 5.2 実測結果（2025年12月31日）

**測定環境:**
- npm run build（本番モード）
- Chrome 131
- Laravel Sail（Docker）

| メトリクス | npm run dev | npm run build | 目標値 | 達成率 |
|----------|------------|--------------|--------|--------|
| フォーカス応答 | 数秒 | **即座** | 即座 | ✅ 100% |
| 画像プレビュー | 遅い | **143ms** | <200ms | ✅ 100% |
| UIブロック | あり | **なし** | なし | ✅ 100% |
| ドロワー開閉 | 2000ms | 1600-2500ms | <300ms | △ 20% |
| キーワード検索 | 1500ms | **1500ms** | <500ms | ⚠️ 維持 |
| タブ切り替え | 30ms | 7-140ms | <100ms | ✅ 90% |

**重要な発見:**
- ✅ **npm run buildで4項目が劇的改善**
- ⚠️ **キーワード検索はLivewireレンダリングが原因（フロントエンド起因ではない）**
- ⚠️ **wire:ignoreはMaryUIと相性が悪い（実証済み）**
```

**参照元ドキュメント:**
- wbs5.2-performance-improvement/2025-12-31_npm_build_improvement_analysis.md
- wbs5.2-performance-improvement/2025-12-31_phase5_completion_report.md
- wbs5.2-performance-improvement/2025-12-31_livewire3_optimization_investigation.md

**推定工数:** 1時間

---

#### ドキュメント#7: `docs/database/schema.md`（新規作成）

**目的:** attached_filesテーブルの最新スキーマを公式ドキュメント化

**構成:**

```markdown
# データベーススキーマ

## 1. attached_filesテーブル

### 1.1 基本情報
- テーブル名: attached_files
- 用途: 添付ファイルのメタデータと処理結果
- 全文検索: contentカラム（Mroongaインデックス）

### 1.2 カラム定義

| カラム名 | 型 | NULL | 説明 | Phase |
|---------|---|------|------|-------|
| id | bigint | NO | 主キー | - |
| ledger_id | bigint | NO | 台帳ID | - |
| column_id | int | NO | カラムID | - |
| hashedbasename | varchar | NO | ハッシュ化ファイル名 | - |
| original_filename | varchar | NO | 元のファイル名 | - |
| original_mime_type | varchar | YES | MIMEタイプ | Phase2 |
| file_size | bigint | YES | ファイルサイズ | Phase2 |
| content | text | YES | 抽出テキスト（全文検索対象） | - |
| vlm_processed_at | timestamp | YES | VLM処理完了日時 | Phase2 |
| vlm_error | text | YES | VLMエラーメッセージ | Phase2 |
| confidence | decimal | YES | VLM信頼度スコア | Phase2 |
| vlm_source_text | longtext | YES | VLM生Markdownテキスト | Phase2 |
| vlm_output_json | json | YES | VLM構造化JSON | Phase2 |
| ocr_processed_at | timestamp | YES | OCR処理完了日時 | - |
| ocr_error | text | YES | OCRエラーメッセージ | Phase2 |
| tika_processed_at | timestamp | YES | Tika処理完了日時 | - |
| tika_error | text | YES | Tikaエラーメッセージ | Phase2 |
| finalized_at | timestamp | YES | 最終化完了日時 | Phase2 |
| processing_benchmark | json | YES | 処理時間ベンチマーク | Phase2 |
| created_at | timestamp | NO | 作成日時 | - |
| updated_at | timestamp | NO | 更新日時 | - |

### 1.3 インデックス
- PRIMARY KEY: id
- INDEX: ledger_id
- FULLTEXT INDEX: content (Mroonga)
- UNIQUE INDEX: (ledger_id, column_id, hashedbasename)

### 1.4 リレーション
- belongs_to: Ledger
- has_many: Activity (spatie/activitylog)
```

**推定工数:** 2-3時間

---

#### ドキュメント#8: `docs/models/AttachedFile.md`（新規作成）

**目的:** AttachedFileモデルの公式ドキュメント

**構成:**

```markdown
# AttachedFileモデル

## 1. 概要
添付ファイルのメタデータと処理状態を管理するEloquentモデル。

## 2. リレーション
- belongsTo: Ledger
- morphMany: Activity

## 3. スコープ
- scopeVlmProcessed(): VLM処理済み
- scopeOcrProcessed(): OCR処理済み
- scopeTikaProcessed(): Tika処理済み
- scopeFinalized(): 最終化済み
- scopePendingFinalization(): 最終化待ち

## 4. アクセサ・ミューテータ
- getHasVlmAttribute(): VLM処理完了判定
- getHasOcrAttribute(): OCR処理完了判定
- getHasTikaAttribute(): Tika処理完了判定
- getIsFullyProcessedAttribute(): 全処理完了判定

## 5. 主要メソッド
- getDownloadUrl(): ダウンロードURL生成
- getPreviewUrl(): プレビューURL生成
- canReprocess(): 再処理可能判定
- markAsFinalized(): 最終化マーク

## 6. 活動ログ
- spatie/activitylogによる変更履歴記録
- 記録対象: vlm_processed_at, ocr_processed_at等の変更
```

**推定工数:** 1-2時間

---

#### ドキュメント#9: `docs/architecture/file-processing-flow.md`（新規作成）

**目的:** ファイル処理フローの包括的ドキュメント

**構成:**

```markdown
# 添付ファイル処理フロー

## 1. 全体フロー
- アップロード → VLM処理 → OCR処理 → Tika処理 → 最終化

## 2. 各エンジンの役割
### 2.1 VLM (PaddleOCR-VL)
- レイアウト理解、構造化抽出
- Markdown生成
- 信頼度スコア算出

### 2.2 OCR (OcrMyPDF)
- 画像PDF→テキスト付きPDF変換
- プレーンテキスト抽出

### 2.3 Tika (Apache Tika)
- Office文書対応
- フォールバック処理

## 3. 処理順序とフォールバック
- 優先順位: VLM > OCR > Tika
- VLM失敗時: OCR→Tika
- OCR skip-text: テキスト付きPDFは最適化のみ

## 4. 状態遷移
- 24パターンの処理状態
- UI分岐ロジック

## 5. エラーハンドリング
- リトライ戦略
- タイムアウト処理
```

**推定工数:** 2-3時間

---

## 4. 実装スケジュール案

### 4.1 Week 1（優先度: 🔴最高）

| 日 | タスク | 工数 | 累計 |
|----|--------|------|------|
| Day 1 | ドキュメント#1: Attachment.md（セクション3.2-3.5） | 2h | 2h |
| Day 2 | ドキュメント#1: Attachment.md（セクション4-6） | 2h | 4h |
| Day 3 | ドキュメント#2: vlm-ocr-technology-selection.md | 2h | 6h |
| Day 4 | ドキュメント#3: QueueProcessing.md | 1.5h | 7.5h |

### 4.2 Week 2（優先度: 🟡高）

| 日 | タスク | 工数 | 累計 |
|----|--------|------|------|
| Day 5 | ドキュメント#4-6: Services, README, vlm-ocr.md | 2h | 9.5h |
| Day 6 | ドキュメント#10: copilot-instructions.md | 0.5h | 10h |

### 4.3 Week 3（優先度: 🟢中）

| 日 | タスク | 工数 | 累計 |
|----|--------|------|------|
| Day 7-8 | ドキュメント#7: schema.md（新規） | 2.5h | 12.5h |
| Day 9 | ドキュメント#8: AttachedFile.md（新規） | 1.5h | 14h |
| Day 10-11 | ドキュメント#9: file-processing-flow.md（新規） | 2.5h | 16.5h |
| Day 12-13 | ドキュメント#11: performance-optimization.md（新規） | 2.5h | 19h |
| Day 14 | 全体レビュー、リンク整合性確認 | 1h | 20h |

**合計:** 20時間（3週間）

---

## 5. 参照元ドキュメントマップ

### 5.1 Phase別主要ドキュメント

| Phase | 主要ドキュメント | 主な内容 |
|-------|----------------|---------|
| 全体 | `attachment-ui-improvement-plan.md` | 全5フェーズのWBS、進捗管理 |
| Phase 1 | `phase1_mockup_evaluation_report.md` | モックアップ、UX評価 |
| Phase 2 | `phase2_model_extension_plan.md` | attached_filesテーブル拡張 |
| Phase 3 | `phase3_detailed_plan.md` | VLM/OCR/Tika統合、ジョブチェーン |
| Phase 4 | `phase4_detailed_plan.md` | FileInspector 4タブ実装 |
| Phase 4.6 | `phase4-6_completion_summary.md` | Phase 4完了サマリー |
| Phase 5 | `phase5_detailed_plan.md` | UI分岐24パターン実装 |
| Phase 5 | `phase5_feature_comparison_report.md` | 機能比較、OCRタブ判断 |
| UI改善 | `content_tab_ui_refinement.md` | Contentタブ最終UI改善 |

### 5.2 技術ドキュメント

| ドキュメント | 内容 |
|------------|------|
| `file-inspector-data-structure.md` | FileInspectorデータ構造設計 |
| `phase4-6-4_ui_verification_checklist.md` | UI分岐検証140項目 |
| `phase4-6-5_performance_report.md` | パフォーマンス測定結果 |
| `phase4-6-6_accessibility_report.md` | アクセシビリティ検証 |

### 5.3 WBS 5.2パフォーマンス改善ドキュメント

| ドキュメント | 内容 | 重要度 |
|------------|------|--------|
| `wbs5.2-performance-improvement/README.md` | WBS 5.2全体のガイド | ⭐⭐ |
| `2025-12-31_wbs5-2_summary.md` | WBS 5.2作業サマリー | ⭐⭐⭐ |
| `2025-12-31_phase5_completion_report.md` | Phase 5完了レポート | ⭐⭐⭐ |
| `2025-12-31_npm_build_improvement_analysis.md` | npm run build改善分析 | ⭐⭐⭐ |
| `2025-12-31_livewire3_optimization_investigation.md` | Livewire 3最適化調査 | ⭐⭐⭐ |
| `2025-12-31_drawer_event_flow_analysis.md` | イベントフロー分析 | ⭐⭐ |
| `2025-12-31_php84_fix_completion.md` | PHP 8.4修正完了 | ⭐⭐ |
| `2025-12-31_image_log_fix.md` | 画像ログ修正 | ⭐ |

---

## 6. 品質チェックリスト

### 6.1 内容の正確性

- [ ] 実装済み機能と計画段階の機能を明確に区別
- [ ] コードベースとドキュメントの整合性確認
- [ ] スクリーンショット・図表の更新
- [ ] バージョン番号、日付の正確性

### 6.2 構造と可読性

- [ ] 階層構造の統一
- [ ] 見出しレベルの適切性
- [ ] コードブロック、図表の適切な配置
- [ ] 内部リンクの動作確認

### 6.3 網羅性

- [ ] 全24パターンのUI分岐説明
- [ ] 全4タブの詳細説明
- [ ] 全ジョブクラスの説明
- [ ] エラーケースの網羅

### 6.4 保守性

- [ ] 将来の変更を想定した構造
- [ ] 作業ドキュメントへのリンク保持
- [ ] 更新履歴の記録

---

## 7. まとめ

### 7.1 更新の重要性

本計画により、以下が実現されます：

1. **開発者オンボーディング**: 新規開発者が最新の実装状況を理解
2. **保守性向上**: 公式ドキュメントとコードの整合性維持
3. **知識の体系化**: 作業ドキュメントの知見を組織資産化
4. **技術負債削減**: 古い情報の削除、最新情報への更新

### 7.2 実施タイミング

**推奨:** Phase 5完全完了後、次の大型機能開発前
**理由:** 実装が安定し、追加変更の可能性が低いタイミング

### 7.3 成果物

- 公式ドキュメント12件更新（既存8件、新規4件）
- 合計16-24時間の作業
- 体系的な添付ファイル機能ドキュメント群
- パフォーマンス測定インフラの拡張ガイドライン

---

**次のステップ:** このリストをもとに、優先度順に公式ドキュメントの更新を実施してください。

