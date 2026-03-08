# AI 指示書の同期と共有計画：GitHub Copilot と Gemini CLI の挙動統一 (最終版)

**作成日:** 2026年03月08日  
**ドキュメント種別:** 作業ファイル（設計・実装計画）

## 📖 関連ドキュメント

### 設定ファイル（正のソース - 読み取り専用）
- [.github/copilot-instructions.md](../../.github/copilot-instructions.md) - GitHub Copilot メイン指示書
- [.github/instructions/](../../.github/instructions/) - スタック別専門知識
- [.github/skills/](../../.github/skills/) - 構造化されたスキル・知識

### Gemini CLI 設定（同期・加工先）
- [.gemini/GEMINI.md](../../.gemini/GEMINI.md) - Gemini CLI 指示のインデックス・行動規範

---

## 1. エグゼクティブサマリー

### 1.1. 背景
GitHub Copilot をメイン、Gemini CLI を補足として使用する体制において、指示資産の重複と齟齬を防ぐ。特に `GEMINI.md` が肥大化し、`.github` 内の既存知識と重複していた。

### 1.2. 目的
**`.github` を「正（SSOT）」とし、Gemini CLI はその情報を参照・同期して利用する。`GEMINI.md` は「行動規範」と「知識の地図」に特化させ、軽量かつ高度に統合された状態を実現する。**

### 1.3. 整理方針
1. **SSOT 原則**: 技術ルールや制約は `.github` 内のファイルに集約。
2. **Index-based GEMINI.md**: `GEMINI.md` は情報を再掲せず、タスクに応じた参照先を示す「地図」として機能させる。
3. **One-way Sync**: `bin/sync-ai-instructions.sh` により、`.github` -> `.gemini` への一方向同期を行い、元のファイルを汚さず最新化する。
4. **Delta Memories**: Gemini 特有の知見（まだ `.github` に反映されていないもの）のみを `GEMINI.md` に暫定保持する。

---

## 2. 成果物

### 2.1. 同期基盤
- `bin/sync-ai-instructions.sh`: 専門知識（instructions）、テンプレート（prompts）、スキル（skills）を `.github` から `.gemini` に自動ミラーリングするスクリプト。

### 2.2. スリム化された GEMINI.md
- 指示の重複を全て排除。
- 「どのタスクでどのファイルを読み込むべきか」というメタ指示に特化。
- Gemini CLI 特有の自律的な動作（Research -> Strategy -> Execution）を強制。

---

## 3. 運用フロー

1. **ルールの更新**: 共通ルールは `.github` 内のファイルを編集する。
2. **情報の同期**: `bin/sync-ai-instructions.sh` を実行し、Gemini 環境を最新にする。
3. **Gemini の活用**: `GEMINI.md` の地図に従い、タスクに応じた指示を `read_file` や `activate_skill` で動的にロードし、遵守する。
4. **教訓の昇格**: `GEMINI.md` 内の「Delta Memories」が安定・重要と判断された場合、`.github` 側の正本へ手動で統合する。

---
**作成者:** LedgerLeap 開発チーム (Gemini CLI)  
**ステータス:** Implementation Complete & Optimized
