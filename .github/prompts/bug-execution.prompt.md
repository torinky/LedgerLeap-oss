---
description: Execute a selected LedgerLeap bug fix with minimal changes, verification, and rollback awareness.
---

# bug-execution

## Goal

調査済みの不具合に対して、選択された対応方針を **最小変更で実装し、検証し、記録する**。

参照:
- [Repository Instructions](../copilot-instructions.md)
- [Bug Execution Skill](../skills/bug-execution/SKILL.md)
- [Bug Response Playbook](../../docs/runbooks/bug-response-playbook.md)
- [Bug Investigation Template](../../docs/templates/bug-investigation-template.md)
- [AI Asset Maintenance Playbook](../../docs/runbooks/ai-asset-maintenance-playbook.md)
- [Git Commit Prompt](./git-commit.prompt.md)

## Preconditions

実装前に次を確認する。

- 調査結果または issue コメントで、採用する方針が決まっている
- 期待される完了条件が明確
- 影響範囲と rollback 条件が把握できている

もし調査結果が曖昧なら、先に `/bug-investigation` を実行して証拠を固める。

## Execution Workflow

### 1. 実装契約を固定する

着手前に短く整理する。

- 入力 / 前提条件
- 変更するファイル候補
- 期待される出力 / 挙動
- 失敗モード / rollback 条件

### 2. 最小変更で修正する

- 根本原因に近い箇所を優先して修正する
- ついでの大規模 refactor は避ける
- LedgerLeap の既存パターン、命名、UI、テスト方針に合わせる
- public API や挙動を変える場合は影響範囲を明示する

### 3. 回帰防止を加える

可能なら次を行う。

- 再現テストを追加 / 更新
- 既存テストの期待値更新
- docs / runbook / template への最小追記

### 4. LedgerLeap-specific Validation

変更内容に応じて必要な確認を行う。

- PHP / Laravel 変更: `./vendor/bin/sail pint`
- テスト変更 or 挙動変更: `./vendor/bin/sail test` または対象テスト
- Livewire / browser 問題: browser logs / UI smoke
- Tailwind utility 追加: `./vendor/bin/sail npm run build`
- 権限まわり: permission cache と tenant access cache の反映確認
- tenant 問題: tenant 初期化と `tenant_id` fallback の確認

### 5. 実行結果をまとめる

最終的に次を報告する。

- 変更ファイル
- 何を直したか
- 追加 / 更新したテスト
- 実行した確認項目
- 残リスク
- 必要な follow-up

### 6. 完了後の振り返りを切り出す

次のどちらかに当てはまる場合は、実装報告とは別に振り返りを短く抽出する。

- issue / sprint が完了した
- ユーザーから明示的に「振り返りをしてください」と指示された

振り返りでは、次を必ず確認する。

- 今回の学びが再利用可能か
- その学びは `docs/work/*` に残すべきか、それとも `.github` 資産へ昇格すべきか
- 同じ手戻りを防ぐために、prompt / skill / runbook / template のどこへ同期するか

再利用可能な学びがあれば、`/skill-maintenance` を実行して整理する。

## Deliverable Format

### 実装サマリ
- 採用した仮説
- 修正方針
- 変更ファイル

### 検証結果
- 実行した lint / test / build / smoke test
- 結果（PASS / FAIL）
- 必要なら再試行理由

### リスクと次の一手
- 残っている懸念
- 今回見送った項目
- 追加で runbook / skill 化すべき学び

## Guardrails

- 調査で否定された仮説に戻らない
- build / test が壊れたまま終えない
- 新規 utility class, cache, tenancy, Mroonga, Livewire serialization まわりは見落とさない
- 変更後は、結果だけでなく **なぜその案を採ったか** も短く残す
- 再利用可能な実装・検証パターンが確定したら `/skill-maintenance` で `.github` 資産へ反映する
