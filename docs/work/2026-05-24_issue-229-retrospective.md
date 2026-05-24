# Issue #229 retrospective

- status: confirmed
- last_confirmed_at: 2026-05-24
- recheck_after: 90d
- recheck_trigger: another publication-packet pilot validates comment-sync scope, a user-facing packet adds unnecessary source comments, or `gh issue edit` fails again because the repo was assumed instead of passed explicitly

## 良かったこと

### 技術要素
- `portal-and-navigation` と `search-api` を並べて実行したことで、`comment_sync_policy = not_applicable` と `required` の境界を実データで比較できた。
- packet ごとに `manifest / handoff / acceptance` を残したため、#219 が「次に何から書くか」を packet 単位で引き継げる状態になった。
- REST Search API の packet では `SearchController` / `SearchRequest` だけに comment sync を絞り、MCP tool や service internals まで広げずに済んだ。

### 作業の進め方
- packet record、公開 doc、issue body / completion comment を同じ pass で更新したことで、#229 完了判定と #219 引き継ぎのズレを残さずに済んだ。
- user-facing packet と developer-facing packet を 1 件ずつ選んだことで、pilot 自体が backlog 凍結の判断材料になった。
- GitHub issue の canonical draft をローカルに持ち、全文置換 + 再取得確認で同期したため、issue body と手元の正本を揃えられた。

## 悪かったこと

### 技術要素
- `gh issue edit` を最初に default repo 前提で実行して失敗し、`-R torinky/LedgerLeap` 付きに切り替えるやり直しが発生した。
- #219 の local plan には #229 section の古い候補 (`configuration.md`, `mcp.md`, `search.md`) が残っており、pilot 完了後に actual backlog へ直す必要があった。

### 作業の進め方
- #229 完了直後の時点では、#225 umbrella handover と #219 開始コメントがまだ未更新で、次 sprint の入口が一段遅れた。

## 上書き指示されたこと

### 技術要素
- なし。

### 作業の進め方
- なし。

## 修正・エビデンス

- pilot packet:
  - `docs/work/issue-229/2026-05-24_packet-portal-and-navigation.md`
  - `docs/work/issue-229/2026-05-24_packet-search-api.md`
- published docs:
  - `docs/getting-started/portal-and-navigation.md`
  - `docs/api/search-api.md`
- issue canonicals:
  - `docs/work/issue-drafts/2026-05-24_issue-sprint-2a4-pilot-body.md`
  - `docs/work/issue-drafts/2026-05-24_issue-229-completion-comment.md`
- related tests:
  - `tests/Feature/Livewire/MyPortalTest.php`
  - `tests/Feature/Api/SearchApiTest.php`
  - `tests/Feature/Search/SearchControllerAdditionalTest.php`

## 学び（ reusable / local / retire ）

- `reusable` / 技術要素: comment sync の pilot は、`not_applicable` packet と `required` packet を最低 1 件ずつ含めると、境界条件を比較したうえで backlog を凍結しやすい。
- `reusable` / 作業の進め方: publication packet sprint の完了時は、packet record 作成 → issue body sync → downstream sprint handoff を同じ pass で終えると sequencing drift を防げる。
- `reusable` / 技術要素: `gh` で issue body を同期するときは default repo を当てにせず、`-R torinky/LedgerLeap` を明示してから再取得確認まで行う。
- `local` / 技術要素: #219 の次の実装順は `portal-and-navigation` と `search-api` の既存成果物を基点にし、続きは `bootstrap-manifest-api` と `mcp-client-guide` を比較しながら進める。
