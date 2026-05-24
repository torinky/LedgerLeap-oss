# Issue #219 / Sprint 2-A planning retrospective

**作成日:** 2026-05-24  
**対象:** #219, #225, #226, #227, #228, #229  
**範囲:** 公開ドキュメント分割フレームワークの再計画、issue 分解、#227/#228 の詳細化

## 1. 何を完了したか

- #219 を直接書き始めず、準備トラックとして #225 を作成した
- #225 を #226〜#229 に分解し、作業単位と完了条件を明確化した
- #227 を OpenCode / Continue.dev の両経路を前提に詳細化した
- #228 を shared SoT / repo-native assets / agent adapters の 3 層で詳細化した
- #219 に依存関係の再分解メモを残した
- Sprint 2-A1 を新セッションで始めるための handoff を用意した

## 2. 良かったこと

- **source-derived inventory を先に置く**判断に切り替えたことで、公開 doc list を仮説のまま固定せずに済んだ
- #225 を umbrella、#226〜#229 を sub-sprint に分けたことで、進捗と成果物の境界が追いやすくなった
- OpenCode と Continue.dev を同列に比較し、**shared contract と agent-specific adapter を分離**したことで #227 / #228 の責務が明確になった
- issue 本文の canonical draft をローカルに置き、GitHub へ full-body sync したため、後で追跡しやすい状態になった

## 3. 悪かったこと

- 初回の Sprint 2-A 設計は doc target list を前提にしすぎており、source coverage の unevenness を吸収できていなかった
- #227 / #228 の詳細化前は OpenCode 前提が強く、Continue.dev の mode / rules / prompts との差分が設計に出ていなかった
- `gh issue edit` は default repo 未設定だと失敗し、`--repo torinky/LedgerLeap` を毎回付ける必要があった

## 4. 上書き指示されたこと

- 「1 packet は OpenCode → LM Studio → Gemma4 26B 前提」という整理に加え、**Continue.dev も処理候補に含める**よう scope が拡張された
- 「スキルだけでなく subagent も整備したい」という要求により、#228 は reusable asset 一般ではなく **skill / agent / adapter / runbook** の分担を持つ計画へ上書きされた
- 「doc list は既に均質」という前提が否定され、**source から doc candidate list を生成する phase を先に入れる**構成へ変更された

## 5. 直接修正したこと

- `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a-doc-framework-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a1-source-inventory-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3-assets-body.md`
- `docs/work/issue-drafts/2026-05-24_issue-sprint-2a4-pilot-body.md`

## 6. 失敗した案と、その案が違うと分かった証拠

### 6.1 失敗した案

- 既存 doc list をそのまま packet 分解の正本にする
- OpenCode のみを想定した packet execution 設計を作る
- repeated task のルールを agent ごとに閉じた prompt へ載せる

### 6.2 違うと分かった証拠

- `routes/tenant.php`, `routes/api.php`, `app/Livewire/*`, `app/Filament/*`, `tests/Feature/*` を見ると、現行 doc list では feature family の粒度が揃っていない
- Continue 公式 docs では subagent 中心ではなく `Plan / Agent`, `rules`, `prompts`, `config.yaml` が正規導線になっている
- OpenCode 公式 docs では `.opencode/commands` と agent permission が repeated task の入口として使える
- Continue の `awesome-rules` と `.continue/checks` は、repeated task を source-controlled asset に落とす運用を示している

## 7. 学びの分類

| 学び | 技術 / 進め方 | 判定 | 次の置き場 | evidence |
|---|---|---|---|---|
| source-derived inventory を packet より先に置く | 進め方 | reusable 候補 | まず `docs/work/*` に保持。2-A4 で再確認後に `.github` 昇格を判断 | `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`, #226 |
| shared contract と agent adapter を分ける | 技術 + 進め方 | reusable 候補 | まず `docs/work/*` に保持。asset 実装後に runbook / skill 反映を検討 | `docs/work/issue-drafts/2026-05-24_issue-sprint-2a2-packet-contract-body.md`, `docs/work/issue-drafts/2026-05-24_issue-sprint-2a3-assets-body.md`, #227, #228 |
| `gh issue edit` は `--repo` を明示した方が安全 | 技術 | local | 今回はこの文書に留める | 実行ログ（default repo 未設定で失敗後、`--repo torinky/LedgerLeap` で成功） |

## 8. 今回は `.github` へ昇格しないもの

- OpenCode / Continue.dev 両対応の adapter 分離は有望だが、まだ **計画段階** であり、実装・pilot を経ていない
- source-derived inventory 先行も今回は planning evidence であり、運用 pattern として再利用可能かは #226 / #229 完了後に判断する

## 9. 公式資料の鮮度メモ

| claim | status | last_confirmed_at | recheck_after | recheck_trigger |
|---|---|---|---|---|
| OpenCode は Agents / Commands / Config で project-local repeated task asset を持てる | confirmed | 2026-05-24 | 90d | OpenCode docs の agent / command schema が変わったとき |
| Continue.dev は `Plan / Agent` と `rules / prompts / config.yaml` が repeated task の主導線 | confirmed | 2026-05-24 | 90d | Continue docs の Agent mode, Rules, config reference に大きな更新があったとき |

## 10. 次回の開始条件

- #226 では、まず source scan と feature family normalization だけに集中する
- #227 / #228 の detailed plan は終わっているので、2-A1 セッションでは packet schema や asset 実装へ脱線しない
- 新セッションでは handoff と ready todo から再開する
