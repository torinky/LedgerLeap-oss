## 📐 Sprint 2-A4 handover — #219 開始順と packet backlog (2026-05-24)

### 現状

- #225 / #226 / #227 / #228 / #230 / #229 までの preparation sprint が完了
- #219 は **packet backlog を実行する implementation sprint** として開始できる状態
- pilot で実証済みの packet:
  - `docs/getting-started/portal-and-navigation.md`
  - `docs/api/search-api.md`

### 優先実行順

| 優先度 | packet | 状態 | comment sync | 主な起点 |
|---|---|---|---|---|
| 1 | `docs/getting-started/portal-and-navigation.md` | pilot 済み | `not_applicable` | `docs/work/issue-229/2026-05-24_packet-portal-and-navigation.md` |
| 2 | `docs/api/search-api.md` | pilot 済み | `required` | `docs/work/issue-229/2026-05-24_packet-search-api.md` |
| 3 | `docs/api/bootstrap-manifest-api.md` | 未着手 | `required` 想定 | `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md` |
| 4 | `docs/api/mcp-client-guide.md` | 未着手 | `required` 想定 | `docs/runbooks/doc-publication-packet-playbook.md` |

### 次セッションの開始順

1. `docs/work/issue-229/2026-05-24_packet-portal-and-navigation.md`
2. `docs/work/issue-229/2026-05-24_packet-search-api.md`
3. `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md`
4. `docs/templates/doc-publication-packet-template.md`
5. `docs/runbooks/doc-publication-packet-playbook.md`

### Changed files

| ファイル | 変更内容 | #219 での扱い |
|---|---|---|
| `docs/getting-started/portal-and-navigation.md` | end-user 向け pilot packet | getting-started 系の公開文体と `not_applicable` packet の見本として再利用 |
| `docs/api/search-api.md` | REST Search API reference packet | API reference 系 packet と bounded comment sync の見本として再利用 |
| `docs/work/issue-229/2026-05-24_packet-portal-and-navigation.md` | tutorial packet handoff / acceptance | 次の user-facing packet の manifest 雛形として再利用 |
| `docs/work/issue-229/2026-05-24_packet-search-api.md` | reference packet handoff / acceptance | 次の API packet の manifest 雛形として再利用 |
| `docs/work/2026-05-24_issue-230_doc-format-and-source-comment-evidence.md` | format / evidence / PHPDoc minimum の正本 | 新しい packet を起こす前に必ず参照 |

### TODO comment locations

- なし（TODO マーカーは追加していない）

### Open questions

- `docs/api/bootstrap-manifest-api.md` を先に進めるか、`docs/api/mcp-client-guide.md` を先に進めるかは、REST 契約を優先するか MCP client onboarding を優先するかで決める
- `docs/contributing/*` は引き続き provisional queue のままなので、#219 本文執筆に混ぜる前に別 source scan の要否を確認する
