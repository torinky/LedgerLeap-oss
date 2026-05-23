# [Issue]: ローカルLLM（LM Studio Gemma4 31B）からのMCPツール呼び出し時にクラッシュが発生する

## イシュー種別
バグ / パフォーマンス改善

## 概要

OpenCode・Continue.dev・OpenClaw から LM Studio 上の **Gemma4 31B** に MCP ツールを呼び出すと、
`SearchLedgersTool` 等のツールレスポンスが大きすぎてモデルのコンテキストが溢れ、LLM がクラッシュする。

## 背景 / 目的

- ローカルLLM（LM Studio / Ollama）向けに MCPを活用したい要望が増えている。
- `SearchLedgersTool` の `summary` フォーマット（デフォルト）は `meta` 辞書・添付ペイロード・`search_trace` を全量含むため、10件検索で 10,000〜40,000 tokens になり得る。
- Gemma4 31B を含むローカルモデルの実効 context budget はツール結果込みで 8,000〜16,000 tokens 程度が安定上限。
- OpenCode / Continue.dev / OpenClaw はツール結果をそのままコンテキストに追加するため、1回の検索でクラッシュが誘発される。

## 現状

**参照ファイル:**
- `app/Mcp/Tools/SearchLedgersTool.php`
- `app/Mcp/Tools/GetLedgerDefinesTool.php`
- `app/Mcp/Servers/LedgerLeapServer.php`
- `app/Services/LedgerService.php`
- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Response_Size_Reduction_Plan.md`

**確認済みの主要サイズ要因:**

| 要因 | 概算 |
|---|---|
| `meta.ledger_defines`（column_define含む全定義） | +3,000〜15,000 tokens |
| 添付ファイルごとの `payloads.text/structured/visual` | +200〜800 tokens/件 |
| `search_trace`（シノニム展開ログ） | +200〜1,000 tokens |
| `include_content=true`（デフォルト）での全フィールド値 | +500〜5,000 tokens |
| `GetLedgerDefinesTool`の `JSON_PRETTY_PRINT` + 全件返却 | 無制限 |
| `buildStructuredPayload()` の pages/text_blocks/key_value_pairs | **無制限**（複数ページPDFで数千要素） |

**コードレビューで確認されたバグ:**

| バグ | 内容 | 修正方法 |
|---|---|---|
| Bug A | `include_content=false` で `$ledger->content` が除去されない | `unset($ledger->content)` を追加 |
| Bug B | `include_attachment_payloads` パラメータが未実装 | Sprint 1 で新規実装（`false` 時に payloads 構築をスキップ） |

**インターネット上の類似事例・仕様調査:**
- LM Studio + MCP ツールの大きいレスポンスによるクラッシュは、コミュニティで多数報告済み。
- MCP 公式ベストプラクティスで「ツール定義・レスポンスによるコンテキスト肥大化は深刻な問題」と明言。
- MCP Apps Patterns でチャンク型取得（一覧→詳細）が公式推奨パターン。
- **Continue.dev の `mcpServers` 設定に `defaultToolArgs` は存在しない**（計画の暫定対応を修正済み）。
- LM Studio API は `allowed_tools` フィールドで呼び出し可能ツールを制限可能（クライアント側軽減策）。
- `TruncatableResponse` の raw byte 切り捨ては JSON を破壊するリスクがあり、データ削減型の実装が必要。

## 目標 / 完了状態

- `SearchLedgersTool` のデフォルトレスポンスが **16 KB 以下**（現状比 70% 以上削減）
- `GetLedgerDefinesTool` がコンパクトJSONで **limit 付き**返却
- 全 MCP ツールに **32KB 上限の安全網**（`TruncatableResponse` トレイト）
- ローカルLLM向けの `instructions` ガイダンスと Runbook が整備されている
- Gemma4 31B / LM Studio での OpenCode・Continue.dev・OpenClaw 利用が安定動作する

## スコープ / 非スコープ

**対象:**
- `SearchLedgersTool` のデフォルトパラメータ変更（include_meta / include_attachment_payloads / include_trace を false へ）
- `include_content` デフォルトを `false` に変更（プレビューに切り替え）
- `GetLedgerDefinesTool` の limit / compact 出力対応
- `TruncatableResponse` トレイト新規作成
- `LedgerLeapServer::$instructions` へのローカルLLM向けガイダンス追記
- `docs/runbooks/local-llm-mcp-setup.md` の新規作成

**対象外:**
- クラウドモデル（Claude / GPT）での動作変更（後退しないことのみ確認）
- MCP プロトコルレベルの変更
- LM Studio 側の設定変更（Runbook で案内のみ）
- `model_profile` 対応の自動 slim モード（Sprint 4 / 別イシューで検討）

## スプリント分解

- [ ] **Sprint 1**: `SearchLedgersTool` デフォルト削減
  - `include_meta` / `include_attachment_payloads` / `include_trace` を false デフォルトで追加
  - `include_content` のデフォルトを false に変更（200文字プレビューへ）
  - **Bug A 修正**: `include_content=false` 時に `unset($ledger->content)` を必ず実行
  - **Bug B 修正**: `include_attachment_payloads=false` 時に `buildStructuredPayload()` 等の呼び出しをスキップ
  - 添付ファイル出力のスリム化（payloads を opt-in 化）
  - デフォルト呼出しで 16 KB 以下であることをテスト
- [ ] **Sprint 2**: `GetLedgerDefinesTool` コンパクト化 + `TruncatableResponse` トレイト
  - `JSON_PRETTY_PRINT` 除去、`limit` / `include_options` パラメータ追加
  - `TruncatableResponse` トレイト新規作成・各ツールに適用
  - **注意**: raw byte 切り捨て禁止。データ削減型（フィールド削除優先）で実装
- [ ] **Sprint 3**: `instructions` 更新 + ローカルLLM Runbook
  - `LedgerLeapServer::$instructions` にローカルLLM向けガイダンス追記
  - `docs/runbooks/local-llm-mcp-setup.md` 作成（LM Studio / OpenCode / Continue.dev 設定例）
  - Continue.dev は `rules/` ディレクトリ経由の指示（`defaultToolArgs` は**非対応**）
  - LM Studio `allowed_tools` 設定についても記載
- [ ] **Sprint 4（オプション）**: `model_profile` 対応 slim サーバーモード
  - `small-local` プロファイル時に登録ツールを自動削減

## エビデンス / 参照先

- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Response_Size_Reduction_Plan.md` — 実装計画詳細
- `docs/development/MCP_Architecture_and_Flow.md` — アーキテクチャ仕様
- `docs/work/llm-integration/2025-09-28_MCP_Response_Optimization_Plan.md` — 前回最適化計画

## 完了条件

- [ ] `SearchLedgersTool` をデフォルト引数で呼び出したとき、JSON レスポンスが **16,000 bytes 以下**
  - Evidence: `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` のサイズアサーション
- [ ] Bug A: `include_content=false` でレスポンスに `content` キーが含まれないこと
  - Evidence: `SearchLedgersToolTest::testContentExcludedWhenFlagFalse()`
- [ ] Bug B: `include_attachment_payloads=false` でレスポンスに `payloads` キーが含まれないこと
  - Evidence: `SearchLedgersToolTest::testPayloadsExcludedWhenFlagFalse()`
- [ ] `include_meta=true`, `include_content=true`, `include_attachment_payloads=true` で従来と同等のレスポンスが取得できること（後退なし）
  - Evidence: 既存テストが通ること
- [ ] `GetLedgerDefinesTool` がデフォルト 20件・コンパクトJSONを返すこと
  - Evidence: `tests/Unit/Mcp/Tools/GetLedgerDefinesToolTest.php`
- [ ] 32KB 超のレスポンスに `__truncated__: true` が付与されること（データ削減型・JSON構造維持）
  - Evidence: `tests/Unit/Mcp/Traits/TruncatableResponseTest.php`
- [ ] `docs/runbooks/local-llm-mcp-setup.md` が存在すること
- [ ] クラウドモデル向けの既存テストに後退がないこと

## 関連ドキュメント

- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Response_Size_Reduction_Plan.md`
- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Plan_Validity_Investigation.md` — 外部仕様調査レポート
- `docs/work/issue-drafts/2026-05-15_mcp-local-llm-crash-response-size.md`
- `docs/development/MCP_Architecture_and_Flow.md`

## GitHub 追跡

- Parent Issue: #211
- Sprint 1: #212
- Sprint 2: #213
- Sprint 3: #214

## 確認事項

- [x] バグ / パフォーマンス改善イシューであることを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した

