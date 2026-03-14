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
- `required_guides` などの guide ID は論理参照先であり、MCP Resource / REST discovery / 配布ファイルのどれで返すかは Sprint 6 の discovery contract で具体化する
- `required_guides` は **client-facing で意味が通る guide ID** に限定し、`constraints/*` のような developer-facing 名前空間を bootstrap discovery へ直接出さない
- Sprint 6 では `GET /api/v1/ai/bootstrap-manifest` を初期 discovery contract として追加し、manifest 群を bootstrap bundle 解決の入力として扱う
- REST/MCP の役割分担と local model budget の詳細は `docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md` を参照する

