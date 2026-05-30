---
description: Investigate LedgerLeap bugs systematically before implementation. Gather internal evidence, external references, and propose response options.
---

# bug-investigation

## Goal

不具合・エラー・CI失敗・挙動不良について、**実装前に調査を完了**させる。
ログ、関連コード、既存ドキュメント、既存実装、外部の類似事例、公式ドキュメント、ベストプラクティスを確認したうえで、対応方針と実行案を整理する。

参照:
- [Repository Instructions](../copilot-instructions.md)
- [Bug Investigation Skill](../skills/bug-investigation/SKILL.md)
- [Bug Response Playbook](../../docs/runbooks/bug-response-playbook.md)
- [Bug Investigation Template](../../docs/templates/bug-investigation-template.md)
- [CI Failure Investigation](../skills/ci-failure-investigation/SKILL.md)
- [Permission Model](../skills/permission-model/SKILL.md)
- [Livewire Tenant Context](../skills/livewire-tenant-context/SKILL.md)
- [RAG Vector Search](../skills/rag-vector-search/SKILL.md)

## Inputs

不足している場合は、まず次を確認する。必須情報が足りないときは妥当な前提を明記して調査を進める。

- 症状の要約
- 期待される動作 / 実際の動作
- 再現手順
- 発生環境（tenant / route / browser / test / CI / queue など）
- エラーメッセージ、ログ、スタックトレース
- 直近変更（関連 Issue / PR / commit / migration / package update）
- どの層の問題か（app / Filament / vendor / translation / CSS / test など）
- その層の所有者がどこか（自前コード / vendor / 設定 / 翻訳）

## Early Checkpoints

- まず対象レイヤーを 1 つに固定する。複数候補がある場合は、最も可能性が高い 1 つを主対象として明示する。
- 所有者が不明な場合は、調査を進める前に「不明」と書き、確認を優先する。
- 仮説を立てる前に、次に反証すべきポイントを 1 つ以上決める。
- UI / Livewire / Filament の変更なら、後続の検証方法まで先に決める。

## Required Investigation Order

### 1. 内部証拠を先に集める

1. ログ、例外、ブラウザログ、CIログ、テスト失敗内容
2. 関連コード、呼び出し元、利用側、最近の変更
3. 既存テスト、既存 docs、既存 debug log、runbook、skill
4. リポジトリ内の類似実装、類似バグ修正、同名エラー

### 2. LedgerLeap 固有の確認ポイント

必要に応じて次を必ず候補に入れる。

- tenancy 初期化漏れ
- permission cache / tenant access cache のクリア漏れ
- Mroonga の single-column `MATCH() AGAINST()` 制約
- Livewire public state が object になっていないか
- `#[Lazy]` 利用箇所で `tenant()?->id` のみを前提にしていないか
- テストで Embedding / OCR / LDAP / 外部サービスに依存していないか
- Tailwind utility を追加したのに build が未反映ではないか

### 3. 外部調査を行う

優先順:

1. 公式ドキュメント / package docs
2. GitHub Issues / Discussions / release notes
3. 類似 OSS 実装例
4. 信頼できる技術記事 / Q&A

外部調査では次を分けて整理する。

- 類似**実装**事例
- 類似**エラー**事例
- 一般的なベストプラクティス

### 4. 仮説を整理する

- 仮説は 1 つに固定せず、A/B/C で列挙する
- 各仮説に「根拠」「反証」「信頼度」を付ける
- disproven な仮説や failed experiment も残す
- 誤認した対象や外した確認順も、次回のために短く残す

## Deliverable Format

最終出力は少なくとも次を含める。

### 1. 問題要約
- 症状
- 期待値 / 実際値
- 再現可否
- 影響範囲

### 2. 収集した証拠
- 確認したログ / 例外 / テスト / 画面
- 確認したコード / ファイル / シンボル
- 確認した既存 docs / runbook / 過去調査

### 3. 類似事例
- repo 内の類似実装 / 類似修正
- 外部の類似実装事例
- 外部の類似エラー事例
- 参照元 URL または出典

### 4. 仮説テーブル
| Hypothesis | Evidence for | Evidence against | Confidence |
|---|---|---|---|

### 5. 推奨対応方針
- Option A / B / C
- 推奨案の理由
- 影響範囲
- リスク

### 6. 実行提案
- 最小修正方針
- 必要なテスト
- 検証手順
- rollback 案
- 未解明点

## Guardrails

- この prompt では、**調査と方針整理で止める**。コード変更は明示的に求められたとき、または `/bug-execution` を使う。
- いきなり 1 つの原因に決め打ちしない。
- 外部情報だけで判断しない。必ず repo 内の証拠と照合する。
- negative result を省略しない。
- 再利用可能な新パターンが確定したら、runbook / template 更新候補に加えて `/skill-maintenance` を実行する。
