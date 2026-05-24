# Sprint 2-A4: pilot packet 実行とコメント同期方針の検証

## 概要
Sprint 2-A1〜2-A3 で定めた source inventory, packet contract, skill / subagent / runbook を使い、1〜2 packet を pilot 実行して、docs + source comment 同期と #219 向け backlog の妥当性を実証します。

## 背景 / 目的
- 机上で packet schema を作るだけでは、Gemma4 26B での bounded execution が本当に回るか分からない
- source comment 同期は運用負荷があるため、pilot で scope を検証する必要がある
- #219 を開始する前に、packet backlog の優先順位と難易度を固定したい

## 現状
- source-derived inventory, packet schema, reusable asset はまだ未確定
- #219 は packet backlog がないため implementation sprint として即着手しづらい

## 目標 / 完了状態
- 1〜2 packet が end-to-end で実行されている
- docs と source comment の同時更新ルールが実証されている
- #219 向け packet backlog と優先順位が確定している

## スコープ / 非スコープ
### 対象
- pilot packet の選定
- packet 実行
- comment sync 実証
- packet backlog 凍結

### 対象外
- #219 全 packet の消化
- 大量の public doc 完成
- OSS sync

## 方針候補 / メモ
1. pilot は `configuration`, `search`, `mcp` のような bounded で差分が見えやすい packet を選ぶ
2. docs と comment の双方に drift が出やすい packet を 1 件含める
3. pilot の結果をもとに #219 backlog を優先度順に並べる

## スプリント分解
- [ ] pilot packet 候補を選定する
- [ ] 1〜2 packet を end-to-end 実行する
- [ ] comment sync scope を評価する
- [ ] #219 packet backlog と優先順位を凍結する

## エビデンス / 参照先
- Sprint 2-A1〜2-A3 の成果物
- `docs/work/2026-05-24_issue-219_chunked-doc-framework-plan.md`
- `/.github/skills/doc-publication-audit/SKILL.md`

## 完了条件
- [ ] pilot packet の handoff と成果物が残っている
- [ ] comment sync 方針の可否と scope が明文化されている
- [ ] #219 は packet backlog 実行の implementation sprint として開始できる状態になっている
