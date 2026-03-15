# Evaluation Harnesses

LedgerLeap の LLM / AI 評価で使う **copyable fixture / harness** をここに置きます。

## 目的

- 開発用 repo そのものを評価環境として使わず、比較可能な評価用 workspace を定義する
- OS / client / persona ごとの差分を、コードではなくディレクトリ構成とテキスト資産で管理する
- `docs/runbooks/*` の人向け手順と分けて、**配布・複製できる雛形** を保持する

## 一覧

- [`gemini-clean-room/`](/docs/harnesses/gemini-clean-room/README.md)
  - Gemini CLI の clean-room 評価用 base harness
  - Mac / Windows の配置メモ、sanitized settings template、証跡テンプレートを含む

## 位置づけ

- ここに置くもの: reusable harness、fixture、sanitized template、allowed / forbidden artifact 境界
- ここに置かないもの: 逐次的な運用手順、日々の作業 playbook、repo-wide invariants

関連:
- [AI運用資産メンテナンス・プレイブック](../runbooks/ai-asset-maintenance-playbook.md)
- [LedgerLeap Agent Routing](../../AGENTS.md)


