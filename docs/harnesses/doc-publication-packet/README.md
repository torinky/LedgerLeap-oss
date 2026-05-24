# Doc Publication Packet Harness

LedgerLeap の publication packet workflow を Continue.dev で始めるための **sanitized template** を置きます。

## 含まれるもの

- `continue-config.template.yaml`
  - LM Studio + remote MCP + packet rules + prompt blocks の最小構成

## 使い方

1. `continue-config.template.yaml` をコピーする
2. `ABSOLUTE_PROJECT_PATH`, `apiBase`, MCP URL, token placeholder を自分の環境に置き換える
3. `.continue/rules/01-doc-packet-core.md` と `.continue/rules/02-doc-packet-comment-sync.md` を参照する
4. packet handoff / acceptance は `docs/templates/doc-publication-packet-template.md` を使う

## 関連

- [Doc Publication Packet Playbook](../../runbooks/doc-publication-packet-playbook.md)
- [Doc Publication Packet Template](../../templates/doc-publication-packet-template.md)
