# Doc Publication Packet Harness

LedgerLeap の publication packet workflow を OpenCode / Continue.dev で始めるための
**sanitized template** を置きます。

## 含まれるもの

- `opencode-config.template.jsonc`
  - OpenCode + LM Studio + remote MCP の packet trial 用 overlay
  - `.opencode/commands/*` と `.opencode/agents/*` をそのまま使う前提
- `continue-config.template.yaml`
  - LM Studio + remote MCP + packet rules + prompt blocks の最小構成

## 使い方

### OpenCode

1. `opencode-config.template.jsonc` をローカル用ファイルへコピーする
2. `__LM_STUDIO_MODEL_ID__`, `__TENANT_SLUG__`, `LEDGERLEAP_MCP_TOKEN` を自分の環境に合わせて置き換える
3. リポジトリ直下の `opencode.json` は project-wide default を維持するので、packet trial は `OPENCODE_CONFIG=/absolute/path/to/opencode-config.local.jsonc opencode -m ledgerleap-lmstudio/<model-id>` で起動する
4. `/packet-plan`, `/packet-rewrite`, `/packet-comment-sync` は repo の `.opencode/commands/*` をそのまま使う

### Continue.dev

1. `continue-config.template.yaml` をコピーする
2. `__LM_STUDIO_MODEL_ID__`, `ABSOLUTE_PROJECT_PATH`, `apiBase`, MCP URL, token placeholder を自分の環境に置き換える
3. `model` には LM Studio の `GET /v1/models` が返す **実際の model id** を入れる
4. `.continue/rules/01-doc-packet-core.md` と `.continue/rules/02-doc-packet-comment-sync.md` を参照する
5. lane 判定は `Plan` mode + `packet-plan`、本文 rewrite は `Agent` mode + `packet-rewrite`、comment のみは `Agent` mode + `packet-comment-sync` を使う

共通:

1. packet handoff / acceptance は `docs/templates/doc-publication-packet-template.md` を使う
2. operator flow は `docs/runbooks/doc-publication-packet-playbook.md` を使う

## 関連

- [Doc Publication Packet Playbook](../../runbooks/doc-publication-packet-playbook.md)
- [Doc Publication Packet Template](../../templates/doc-publication-packet-template.md)
