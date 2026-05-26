# Doc Publication Packet Template

## Packet manifest

| Field | Value |
|---|---|
| `packet_id` | |
| `feature_family` | |
| `doc_area` | |
| `target_slug` | |
| `target_path` | |
| `public_classification` | |
| `source_status` | `confirmed` / `provisional` |
| `audience` | |
| `doc_type` | `tutorial` / `how-to` / `reference` / `explanation` |
| `doc_format_profile` | `tutorial` / `how-to` / `reference` / `explanation` |
| `comment_sync_policy` | `required` / `optional` / `not_applicable` |
| `tracking_record_location` | |
| `external_evidence_urls` | |
| `last_confirmed_at` | |
| `recheck_after` | `90d` など |

### Source inputs

- `source_paths`
- `code_anchors`
- `test_anchors`
- `comment_anchors`
- `must_exclude`
- `done_when`

### Doc format profile reference

| Profile | Required sections | Optional sections | Guardrails |
|---|---|---|---|
| `tutorial` | `summary`, `goal`, `prerequisites`, `steps`, `verification`, `next_steps` | `cleanup`, `troubleshooting`, `related_links` | learner-first、順序固定、reference 混在を避ける |
| `how-to` | `summary`, `goal`, `prerequisites`, `procedure`, `verification` | `troubleshooting`, `rollback`, `related_links` | result-first、最短手順、説明は最小限 |
| `reference` | `summary`, `contract_or_surface`, `parameters_or_fields`, `responses_or_effects`, `constraints`, `related_sources` | `examples`, `failure_modes`, `change_history` | contract-first、背景説明を混ぜない |
| `explanation` | `summary`, `problem`, `context`, `decision`, `tradeoffs`, `related_links` | `alternatives`, `faq`, `next_steps` | why-first、手順書にしない |

### Packet evidence fields

| Field | Required | Notes |
|---|---|---|
| `doc_format_profile` | yes | 上の profile から 1 つ選ぶ |
| `required_sections` / `optional_sections` | yes | profile から転記する |
| `external_evidence_urls` | yes | official / major OSS docs の根拠 |
| `last_confirmed_at` | yes | 根拠 freshness |
| `recheck_after` | yes | 再確認期限 |
| `source_anchor` | yes | public docs を裏打ちする repo anchor |
| `test_anchor` | if available | 振る舞いの検証元 |
| `comment_anchor` | if comment sync applies | comment sync 対象 |
| `style_guardrails` | yes | wording / sample / format-order の制約 |
| `comment_sync_decision` | yes | 実施 / defer / not applicable |
| `defer_reason` | if deferred | defer の根拠 |
| `tracking_record_location` | yes | private companion record の置き場 |
| `private_reference_map` | if internal refs exist | private issue / worklog / note との対応 |
| `public_reference_targets` | if public refs exist | public issue / ADR / changelog の導線 |

### Source comment policy

- Comment sync は packet の `comment_anchors` と `source_anchor` に限定する
- PHPDoc は `summary -> description -> tags` の順を守る
- Minimum tags: `@param` for complex inputs, `@return` for non-void or structured outputs, `@throws` for observable failure modes
- `@api` は stable public contract surface のみ候補にする

## Packet handoff

- Packet:
- Goal:
- Publish target:
- Reader + doc_type:
- Format profile:
- Required sections:
- Optional sections:
- Source summary:
  -
- External evidence URLs:
  -
- Freshness:
  - `last_confirmed_at`:
  - `recheck_after`:
- Traceability split:
  - `tracking_record_location`:
  - `private_reference_map`:
  - `public_reference_targets`:
- Required anchors:
  - code:
  - test:
  - comment:
- Style guardrails:
  -
- Comment sync scope:
  -
- PHPDoc minimum:
  -
- Must exclude:
  -
- Internal-only references removed from public body:
  -
- Open questions:
  -
- Unresolved risks:
  -
- Done when:
  - [ ]

## Packet acceptance

| 観点 | 判定 | エビデンス |
|---|---|---|
| format profile applied | ✅ / ❌ | |
| public target updated | ✅ / ❌ | |
| source-derived scope respected | ✅ / ❌ | |
| evidence fields captured | ✅ / ❌ | |
| code / test anchors reflected | ✅ / ❌ | |
| comment sync handled | ✅ / ❌ | |
| traceability split captured | ✅ / ❌ | |
| unresolved risks recorded | ✅ / ❌ | |

- Done when:
  - [ ] packet target が更新済み
  - [ ] `doc_format_profile` と required sections が handoff / acceptance に残っている
  - [ ] `external_evidence_urls` / `last_confirmed_at` / `source_anchor` が残っている
  - [ ] `tracking_record_location` / traceability split が handoff / acceptance に残っている
  - [ ] acceptance table が埋まっている
  - [ ] comment sync 判定が残っている
  - [ ] 次 sprint が迷わない handoff が残っている
