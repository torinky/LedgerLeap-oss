---
description: Triage an uncontracted scenario into the right client-facing LedgerLeap contract surface and decide whether to extend, create, or defer a capability.
---

# client-facing-contract-triage

## 目的

未契約のシナリオを、既存 capability の拡張・新規 capability・非対象に分類し、REST / MCP resource / prompt / tool / optional export のどこへ載せるかを決める。

## 使う場面

- UI evaluation / feature review / issue intake で新しい業務シナリオが見つかった
- WebUI にはあるが MCP / API にない
- capability 追加か拡張かで迷う
- client-facing と developer-facing の境界を確認したい

## まず集める情報

- 何をしたいか
- WebUI で観測できる流れ
- 既存 capability / manifest / docs
- 利用者のペルソナ
- 安全性 / tenant / auth / data sensitivity

## 判定順

1. 内部クラス名ではなく、ユーザーの業務目的から始める。
2. 既存 capability に収まるかを先に見る。
3. 収まらなければ、carrier を REST / MCP resource / prompt / tool / optional export から選ぶ。
4. client-facing には WebUI で見える概念だけを残す。
5. internal details は developer-facing に送る。
6. 1 回で全部やらず、最小 slice と follow-up issue に分ける。

## 出力

- 判定: extension / new capability / non-target
- carrier: REST / MCP resource / prompt / tool / optional export
- SoT 更新先
- follow-up issue 分解案
- 保留点 / リスク

## 参照

- [client-facing-contract-promotion](../skills/client-facing-contract-promotion/SKILL.md)
- [First Access Bootstrap Discovery Contract](../../docs/work/llm-integration/2026-03-14_First_Access_Bootstrap_Discovery_Contract.md)
- [LLM integration README](../../docs/work/llm-integration/README.md)
- [Persona Use Case Scenario](../../docs/function/PersonaUseCaseScenario.md)

## 使い方

この prompt で intake と carrier を決めたあと、必要なら [client-facing-contract-promotion](../skills/client-facing-contract-promotion/SKILL.md) と client-facing-contract-maintenance agent に引き継いで、最小スライスの更新と follow-up issue 分解を進める。
