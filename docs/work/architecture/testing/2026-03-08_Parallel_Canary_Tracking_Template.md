# Parallel Canary Tracking Template

- 関連Issue: https://github.com/torinky/LedgerLeap/issues/81
- 対象Sprint: Sprint 7
- 対象workflow: `parallel-canary.yml`

## 目的

`parallel-canary.yml` の canary 実績を **job 単位** で記録し、Issue #81 の完了条件である

- 10連続成功
- フレーク率 `< 1%`
- `parallel + serial` 標準運用の判断

に必要なエビデンスを揃える。

## 判定ルール

- workflow 全体の `success` だけでは判定しない
- 以下 2 job が **両方 success** の run を `full green` と数える
  - `[Canary] Unit Tests (parallel)`
  - `[Canary] Feature Tests (parallel subset)`
- `continue-on-error: true` のため、workflow conclusion と job conclusion を分けて記録する

## Run Log テンプレート

以下を Issue #81 コメントへ追記する。

```markdown
## 📊 Sprint 7 Canary 実績ログ (YYYY-MM-DD)

| Run | Event | SHA | Unit | Feature subset | Full green | Unit sec | Feature sec | Notes |
|---|---|---|---|---|---|---:|---:|---|
| 22798967130 | pull_request | c6d662d9 | ✅ success | ✅ success | ✅ | 392 | 419 | cleanup 後 |
| 22798966638 | push | c6d662d9 | ✅ success | ✅ success | ✅ | 387 | 479 | cleanup 後 |

### 集計
- 直近 full green 連続数: **2**
- 観測 run 数: **2**
- flaky 判定 run 数: **0**
- フレーク率: **0.0%**
- 判定: ⚠️ 継続観測中（10連続成功は未達）
```

## 週次サマリー テンプレート

```markdown
## フレーク率記録（YYYY-MM-DD）
- 計測期間: YYYY-MM-DD ～ YYYY-MM-DD
- 対象 workflow: `parallel-canary.yml`
- 実行回数: N 回
- full green 回数: N 回
- 失敗回数（再実行で成功）: N 回
- フレーク率: X.X%
- Unit P50 / P95: XXs / XXs
- Feature subset P50 / P95: XXs / XXs
- 特記事項: なし / <テスト名>がフレーク
```

## 収集項目チェックリスト

- [ ] run ID
- [ ] event（push / pull_request / workflow_dispatch）
- [ ] head SHA
- [ ] Unit job conclusion
- [ ] Feature subset job conclusion
- [ ] full green 判定
- [ ] Unit 実行秒数
- [ ] Feature subset 実行秒数
- [ ] failure の場合は再実行結果
- [ ] flaky / 非 flaky の判定

## full green 連続数の数え方

1. 新しい run から時系列逆順に見る
2. Unit / Feature subset が両方 success の run を数える
3. どちらかが failure の時点で連続数を止める
4. workflow 全体が success でも、job failure なら `full green` に数えない

## Sprint 7 完了判定テンプレート

```markdown
## 🏁 Sprint 7 完了判定 (YYYY-MM-DD)

| 完了基準 | 目標 | 実測値 | 判定 |
|---|---|---|---|
| full green 連続数 | 10 run | **N run** | ✅ / ❌ |
| フレーク率 | < 1.0% | **X.X%** | ✅ / ❌ |
| Unit canary 安定性 | 継続 success | **pass/fail** | ✅ / ❌ |
| Feature subset canary 安定性 | 継続 success | **pass/fail** | ✅ / ❌ |

### 次判断
- [ ] `parallel + serial` を標準CIへ昇格
- [ ] KPI 最終測定を Issue #81 本文へ反映
- [ ] Issue close
```

## 初回記録メモ（2026-03-08 時点）

- 直近 full green run:
  - `22798967130`
  - `22798966638`
- 現時点の連続 full green 数: **2**
- 10連続成功は未達

