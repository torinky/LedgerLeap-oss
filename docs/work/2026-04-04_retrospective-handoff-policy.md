# Retrospective Handoff Policy Update

**作成日**: 2026-04-04

---

## 背景

今回の振り返りで、完了後の学び抽出は不具合対応だけでなく、すべての作業に共通する動作として扱うべきだと整理した。

また、振り返りは次の 2 層で行う。

1. **進め方の改善**
   - 対象レイヤーの固定
   - 証拠順序
   - 仮説比較
   - 検証ゲート
   - 手戻り防止

2. **個別具体の手法改善**
   - 使ったコマンド
   - 設定
   - UI の見え方
   - テンプレート
   - 文言
   - 実装パターン

---

## 反映先

- `/.github/prompts/skill-maintenance.prompt.md`
- `/.github/skills/skill-maintenance/SKILL.md`
- `docs/runbooks/ai-asset-maintenance-playbook.md`
- `AGENTS.md`

---

## メモ

- 1 回の完了報告で終わらせず、学び抽出と再利用判断を分ける
- 再利用できない学びでも、まず `docs/work/*` に残す
- `.github` へ昇格するのは、同種のパターンが再現してから

