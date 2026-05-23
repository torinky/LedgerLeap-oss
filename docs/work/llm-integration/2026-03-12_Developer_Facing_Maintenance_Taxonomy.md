# LedgerLeap developer-facing maintenance taxonomy

**作成日:** 2026年03月12日  
**ドキュメント種別:** 作業ファイル（developer-facing maintenance taxonomy）  
**関連Issue:** [#83](https://github.com/torinky/LedgerLeap/issues/83), [#86](https://github.com/torinky/LedgerLeap/issues/86)

## 1. 目的

この文書は、LedgerLeap の LLM / AI 関連資産における **developer-facing の保守先** を整理し、client-facing 契約と混線しないようにするための taxonomy です。

Sprint 3 では次を明確にします。

1. 内部事情をどこへ置くか
2. `.github` / `docs/work` / 生成系資産の責務をどう分けるか
3. 何を正本（SoT）とし、何を派生物として扱うか
4. stale な説明をどこへ移送すべきか

## 2. この文書が扱う範囲

### 扱うもの
- AI 資産の routing / discovery / maintenance
- repo-wide な内部制約の置き場
- path-specific ルールの置き場
- 人向け runbook と AI 向け prompt / skill の境界
- capability manifest と生成系 bootstrap pack の関係

### 扱わないもの
- client-facing capability の内容定義そのもの
- on-prem / local model onboarding 契約の詳細
- update API / Update MCP Tool の仕様策定
- bootstrap discovery contract の詳細

これらは Sprint 2 / 4 / 5 / 6 の対象とする。

## 3. 資産分類と一次反映先

既存の routing ルール自体は [`AGENTS.md`](../../../AGENTS.md)、[`/.github/instructions/ai-assets.instructions.md`](../../../.github/instructions/ai-assets.instructions.md)、[`skill-maintenance`](../../../.github/skills/skill-maintenance/SKILL.md)、[`AI運用資産メンテナンス・プレイブック`](../../runbooks/ai-asset-maintenance-playbook.md) を正本とする。

この節では、Sprint 3 で迷いやすい **LLM / AI 関連資産の実務上の分類** だけをまとめる。

| 資産カテゴリ | 一次反映先 | 主な読者 | 置くべき内容 | ここに置かないもの |
|---|---|---|---|---|
| repo-wide な短い内部制約 | [`/.github/copilot-instructions.md`](../../../.github/copilot-instructions.md) | 全開発者・全エージェント | Mroonga 制約、tenant test trap、cast 制約、permission cache などの短い不変条件 | 長い手順、詳細事例、個別ワークフロー |
| path-specific ルール | `/.github/instructions/*.instructions.md` | 該当ファイルを触る開発者・エージェント | Livewire / Laravel / tests / AI assets の自動適用ルール | repo 全体ルール、運用 runbook |
| JetBrains 入口の workflow | `/.github/prompts/*.prompt.md` | JetBrains 利用者 | slash で起動する作業入口、手順の要約 | 長い判断木、詳細資料 |
| 再利用可能な診断・判断木 | `/.github/skills/*/SKILL.md` | エージェント・開発者 | recurring workflow、判断木、再利用手順 | 長い証跡例、運用会話ログ |
| 長い例・深い資料 | `/.github/skills/*/references/*.md` | 開発者・保守担当 | 長文例、証跡形式、詳細手順 | repo-wide short rules |
| agent-wide routing / discovery | [`/AGENTS.md`](../../../AGENTS.md) | 全エージェント | 配置方針、routing policy、maintenance loop | 実装詳細、client-facing capability 本文 |
| 人向け運用手順 | `docs/runbooks/*` | 人間の開発者・保守担当 | 作業順、完了条件、同期手順 | always-on ルール、path-specific 自動適用 |
| client-facing capability manifest の正本 | [`resources/ai/capabilities/README.md`](../../../resources/ai/capabilities/README.md) と `resources/ai/capabilities/*.yaml` | AI 資産保守者 | client-facing capability の機械可読な定義、生成元 | Laravel 内部都合、テスト罠、DB 事情 |
| client-facing taxonomy / 計画索引 | [`docs/work/llm-integration/README.md`](README.md) と `docs/work/llm-integration/*.md` | 計画策定者・保守担当 | 現行計画、taxonomy、進捗、既知ギャップ | repo-wide invariants の正本 |
| developer-facing の正式機能仕様 | `docs/function/*.md` | 開発者 | 台帳・検索・ワークフロー等の開発者向け機能説明 | client-facing 向け短文化した capability card |
| 生成系 bootstrap pack / snippet | `ai:bootstrap-client-skills` 出力、[`GenerateClientSkillPack`](../../../app/Console/Commands/GenerateClientSkillPack.php)、[`ClientSkillBootstrapService`](../../../app/Services/Ai/ClientSkillBootstrapService.php) | 開発者・検証者 | client ごとの派生ファイル生成、実験用 bootstrap pack | 計画の正本、routing ルールの正本 |

## 4. SoT / 派生物 / 補助資産の境界

### 4.1 正本として扱うもの
- AI 資産の routing と maintenance policy: [`AGENTS.md`](../../../AGENTS.md)
- repo-wide / path-specific の常時ルール: [`.github/copilot-instructions.md`](../../../.github/copilot-instructions.md), `/.github/instructions/*.instructions.md`
- client-facing capability の生成元: [`resources/ai/capabilities/README.md`](../../../resources/ai/capabilities/README.md), `resources/ai/capabilities/*.yaml`
- 現行スプリント計画と進捗: [`docs/work/llm-integration/README.md`](README.md), [`2026-03-09_Client_Skill_Bootstrap_Strategy.md`](./2026-03-09_Client_Skill_Bootstrap_Strategy.md)

### 4.2 派生物として扱うもの
- `ai:bootstrap-client-skills` が生成する client 別 skill / prompt / snippet
- manifest を要約した生成 README
- client 環境へコピーする配布用 skill pack

### 4.3 補助資産として扱うもの
- `docs/work/llm-integration` 配下の履歴的な作業メモ
- 実験段階の generator prototype
- 特定クライアント向けの一時的な bootstrap 配置検証

## 5. developer-facing に閉じるべき内部事情

次の内容は client-facing へ残さず、developer-facing 側で管理する。

- Mroonga の単一カラム `MATCH() AGAINST()` 制約
- tenancy 初期化ルール、tenant-aware component の罠
- `AsColumnArrayJson` や cast-array 列の実装上の注意
- Livewire / Laravel / queue / service / model 名などの内部実装事情
- Feature test の trait 選定や test trap
- permission cache / tenant access cache のクリア要件
- generator / sync script / derived output の都合

### 主な保守先の目安

| 内部事情の種類 | 主な保守先 |
|---|---|
| repo-wide で毎回効く短い罠 | [`.github/copilot-instructions.md`](../../../.github/copilot-instructions.md) |
| path-specific な編集ルール | `/.github/instructions/*.instructions.md` |
| 再利用可能な修正手順 | `/.github/skills/*/SKILL.md` |
| 人向けの連続作業手順 | `docs/runbooks/*` |
| 実装・機能の深い説明 | `docs/function/*.md` |

## 6. client-facing に残してはいけない事項

Sprint 3 時点で client-facing から隔離すべきものは次です。

- DB テーブル / カラム構造
- Mroonga / tenancy / Livewire / Laravel といった技術名ベースの説明
- cast / queue / observer / service class / model class の都合
- テスト用 trait や CI 最適化手順
- permission cache や event-driven test の実装メモ
- generator 実装の内部構造

client-facing では、引き続き **WebUI で観測できる概念** と **業務フロー** に限定する。

## 7. generator prototype の位置づけ

Sprint 3 では、`php artisan ai:bootstrap-client-skills` とその実装を次のように扱う。

- **正本ではない**
- **client-facing capability manifest から派生する生成物** である
- **developer convenience / experimental bootstrap** の位置づけとする
- `client-first / MCP / API first` の親計画を置き換えない

したがって、client-facing capability を変えるときは **先に** `resources/ai/capabilities/*.yaml` を更新し、必要に応じて生成物を再出力する。

## 8. stale な説明の整理方針

今後、内部制約が client-facing 側へ混入していた場合は次の順で整理する。

1. primary destination を 1 つ決める
2. その一次反映先へ内容を移す
3. client-facing 側は短い説明かリンクだけ残す
4. prompt / skill / runbook / AGENTS の近接資産を確認する
5. 重複した古い文面を削除する

詳細手順は [`skill-maintenance`](../../../.github/skills/skill-maintenance/SKILL.md) と [`AI運用資産メンテナンス・プレイブック`](../../runbooks/ai-asset-maintenance-playbook.md) を使う。

## 9. Sprint 3 時点の既知ギャップ

- client-facing taxonomy では `workflow-review` / `activity-audit` / `analytics-report` まで定義済みだが、`resources/ai/capabilities/` の manifest はまだ `ledger-search` / `ledger-create` / `ledger-update` の 3 件のみ
- `ledger-update` は Update API / Update MCP Tool 未実装のため、manifest 上も `planned` のまま
- bootstrap discovery contract と on-prem / local model onboarding の詳細は Sprint 4〜6 の対象であり、この文書では扱わない

これらは Sprint 3 の完了阻害ではなく、**SoT と派生物の境界が明確になった上で次スプリントへ送る既知ギャップ** として扱う。

## 10. Sprint 3 の結論

Sprint 3 の developer-facing maintenance taxonomy として、LedgerLeap の AI 資産は次の順で考える。

1. まず `.github` と `AGENTS.md` で **ルールの正本** を管理する
2. `resources/ai/capabilities/*.yaml` を **client-facing capability の生成元** とする
3. `docs/work/llm-integration` を **計画・索引・ギャップ管理** に使う
4. generator / bootstrap pack は **派生物かつ補助資産** として扱う

この境界を守ることで、client-facing と developer-facing の混線を防ぎ、以後の `/skill-maintenance` でも primary destination を迷いにくくする。

