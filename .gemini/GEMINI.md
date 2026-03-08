# LedgerLeap Agent Index & Operational Mandates

> **Single Source of Truth (SSOT) Policy**
> 全ての技術スタック知識、規約、および制約は `.github/` 内の資産を「正」とします。
> Gemini CLI は、同期された `.gemini/` 配下のファイルを通じて、Copilot と全く同じコンテキストを共有します。

---

## 1. 知識の地図 (Knowledge Map)

タスクの内容に応じて、以下のファイルを**必ず事前に読み込み（read_file または activate_skill）**、指示を遵守せよ。

### A. プロジェクト共通（常に参照）
- **メイン指示・重要制約**: `[.github/copilot-instructions.md](../.github/copilot-instructions.md)`
- **Gemini 特有の教訓**: `.gemini/instructions/lessons-learned.instructions.md`

### B. 技術スタック別（タスク開始時に参照）
- **Laravel / PHP**: `.gemini/instructions/php-laravel.instructions.md`
- **Livewire / Alpine.js**: `.gemini/instructions/livewire.instructions.md`
- **Testing / Pest**: `.gemini/instructions/tests.instructions.md`
- **AI / Assets**: `.gemini/instructions/ai-assets.instructions.md`

### C. 専門スキル (オンデマンド参照)
- `/skills list` で利用可能なスキルを確認し、`activate_skill` でロードせよ。
  - 例: バグ修正なら `bug-investigation` / `bug-execution`
  - 例: Git 操作なら `git-commit`

---

## 2. 動作規範 (Operational Mandates)

### 基本プロセス
1.  **Initialization**: タスク開始前に必ず `bin/sync-ai-instructions.sh` を実行し、最新の指示を同期せよ。
2.  **Research**: 関連する `instructions/*.md` を読み、既存のコードパターンと齟齬がないか確認せよ。
3.  **Strategy**: 実装計画を提示する際、`.github` 内の制約（Mroonga, Tenancy等）をどう考慮したか明示せよ。
4.  **Execution**: `replace` ツール等での編集は、直前の `read_file` 内容に基づき外科的に行え。

### 検証の義務
- 変更後は必ず `vendor/bin/sail test` を実行せよ。
- UI/UX 変更、または新しい Tailwind クラスを追加した場合は `vendor/bin/sail npm run build` を実行せよ。

---

## 3. Gemini 特有の運用メモ (Delta Memories)

*※以下は GitHub 側の指示書にまだ統合されていない、Gemini CLI での対話から得られた暫定的な知見である。*

- **Livewire Serialization**: 公開プロパティに PHP オブジェクトを保持するとシリアライズエラーが発生する。常にプレーンな配列として扱うこと。
- **MaryUI Toast**: `$this->success()` 等のショートカットは Livewire テストで捕捉できない。`$this->dispatch('mary-toast', ...)` を明示的に呼ぶこと。
- **Sail Container**: Sail 環境の Git 操作で `Permission denied` が発生する場合は、`bash -c "cd /path && git ..."` の形式を使用せよ。

---
**Status:** Integrated with .github assets.
