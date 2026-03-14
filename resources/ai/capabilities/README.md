# LedgerLeap AI Capability Manifests

このディレクトリは、LedgerLeap の AI 向け能力定義の正本（SSOT）を置く場所です。

## 目的

- LedgerLeap 固有の業務能力をクライアント非依存で定義する
- MCP Prompt / Resource / Tool の説明資産と整合を取る
- Copilot / Claude Code / Gemini CLI / OpenAI Agents 向けの生成元にする

## 使い方の方針

1. まずこのディレクトリの manifest を更新する
2. そこから各クライアント向けの Skill / Prompt / Agent / README を生成する
3. 既存の `.github/skills` / `.github/instructions` と矛盾しないように保守する
4. `ai:bootstrap-client-skills` は discovery の主契約ではなく、manifest と bootstrap manifest の解決結果を **client 別ディレクトリへ実ファイル化する optional downstream export** として使う
5. MCP / API tool description を slim 化するときは、client-facing workflow / fallback / examples の受け皿として manifest を先に更新する
6. manifest へ移した guidance が reusable rule になる場合は、移送理由と証拠を `docs/work/*` または `.github/skills/*/references/*.md` から辿れるようにする

## 現在の対象能力

- `ledger-search.yaml`
- `ledger-create.yaml`
- `ledger-update.yaml`
- `workflow-review.yaml`
- `activity-audit.yaml`
- `analytics-report.yaml`

## 設計メモ

- `status: active` は現行実装に乗っている能力
- `status: planned` は設計優先度が高いが、API / MCP 実装が未完了の能力
- `ledger-update` は Update API / Update MCP Tool の初期契約が実装済みのため `active`
- manifest は **capability 定義の正本** であり、onboarding 時の bundle 解決や placement instruction の最終 contract そのものではない
- `ai:bootstrap-client-skills` の出力は **派生物** であり、SoT ではない
- tool description から client-facing process guidance を外す場合、workflow / examples / constraints の一次受け皿は manifest とし、tool 側には contract と誤用防止の説明だけを残す
- `required_guides` などの guide ID は論理参照先であり、MCP Resource / REST discovery / 配布ファイルのどれで返すかは Sprint 6 の discovery contract で具体化する
- `required_guides` は **client-facing で意味が通る guide ID** に限定し、`constraints/*` のような developer-facing 名前空間を bootstrap discovery へ直接出さない
- Sprint 6 では `GET /api/v1/ai/bootstrap-manifest` を初期 discovery contract として追加し、manifest 群を bootstrap bundle 解決の入力として扱う
- optional file export / package generation の判断と overwrite policy は `docs/work/llm-integration/2026-03-14_Optional_Client_Bootstrap_Export_Flow.md` を参照する
- REST/MCP の役割分担と local model budget の詳細は `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md` を参照する

