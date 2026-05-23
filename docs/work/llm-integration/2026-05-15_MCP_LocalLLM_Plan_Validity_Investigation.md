# MCP ローカルLLMレスポンスサイズ削減計画 — 妥当性調査レポート

**作成日:** 2026-05-15  
**種別:** 外部調査レポート（計画の証拠確認・懸念点指摘）  
**調査対象計画:** `2026-05-15_MCP_LocalLLM_Response_Size_Reduction_Plan.md`  
**関連Issue:** #211

---

## 調査概要

本レポートは、ローカルLLMクラッシュ対応計画（以下「対象計画」）の各施策について、
以下の外部ソースを照合して妥当性を検証した結果をまとめる。

| 調査ソース | URL / 概要 |
|---|---|
| MCP 公式仕様 (2025-11-25) | `modelcontextprotocol.io/specification/2025-11-25` |
| MCP Client Best Practices | `modelcontextprotocol.io/docs/develop/clients/client-best-practices` |
| MCP Apps Patterns | `apps.extensions.modelcontextprotocol.io/api/documents/Patterns.html` |
| LM Studio 公式ドキュメント | `lmstudio.ai/docs` |
| Continue.dev 公式ドキュメント | `docs.continue.dev` |
| `laravel/mcp` ソースコード | `vendor/laravel/mcp/src/Response.php` |

---

## 1. 確認済み・妥当な施策

### 1.1 デフォルトパラメータ変更（include_meta / include_attachment_payloads / include_trace → false）

**調査結果: ✅ 妥当・推奨パターンと一致**

MCP 公式の「Client Best Practices」で、ツール定義・ツール結果がコンテキストを圧迫する問題が
明示的に取り上げられている。

> "Passing large intermediate results through the model between sequential tool calls compounds the problem."  
> — *MCP Client Best Practices, 2025-11-25*

また「Apps Patterns」には **チャンク応答（chunked tool calls）** パターンが紹介されており、
大量データを単一レスポンスに詰め込まず、段階的に取得する設計が MCP の公式推奨パターンである。

> "When dealing with large amounts of data [...] that may exceed size limits for single tool call responses
> on certain host platforms, chunked responses can be employed."  
> — *MCP Apps Patterns - Reading large amounts of data via chunked tool calls*

**opt-in 化（デフォルトで省略・必要時のみ付与）は、MCP のベストプラクティスに正確に沿っている。**

---

### 1.2 include_content のデフォルトを false へ変更

**調査結果: ✅ 妥当**

LM Studio 公式ドキュメントで以下が確認された。

> "Exceeding this limit [context length] can cause the model to behave erratically."  
> — *lmstudio.ai/docs/python/model-info/get-context-length*

また LM Studio の API サンプルで `context_length: 8000` という値が使われており、
ツール結果を含む会話で実用範囲が 8K〜16K tokens 程度に収まることは実測ベースで妥当な仮定。
content フィールドの省略は最もリターンが大きい施策であり、`GetLedgerDetailTool` で
補完できる設計は 2段階取得（一覧 → 詳細）という MCP 推奨アーキテクチャと一致する。

**ただし、これは既存利用者への破壊的変更（デフォルト値の変更）となるため、
既存テストの更新とマイグレーションノートが必須。**（後述：懸念点 C1）

---

### 1.3 GetLedgerDefinesTool の limit 追加 + JSON_PRETTY_PRINT 除去

**調査結果: ✅ 妥当**

MCP 仕様の「Pagination」セクションに以下の記述がある。

> "Receivers should use cursor-based pagination for task listings to limit response size."  
> — *MCP Specification 2025-11-25 / Pagination*

`GetLedgerDefinesTool` は現在 `Response::text($resource->toJson(JSON_PRETTY_PRINT))` で
全件を整形JSONとして返しているが、`laravel/mcp` の `Response::json()` は
`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` のみでエンコードする（`JSON_PRETTY_PRINT` なし）。
**`JSON_PRETTY_PRINT` の除去は確実に効果がある。** 整形JSONは人間が読むためのもので、
LLM への入力としては非圧縮のコンパクトJSONで十分。

20件デフォルトの `limit` 追加は MCP 仕様の pagination 推奨にも合致し、妥当。

---

### 1.4 TruncatableResponse トレイトによる 32KB 安全網

**調査結果: ⚠️ 概念は妥当、実装方法に要注意**

MCP 仕様には**仕様レベルのツールレスポンスサイズ上限は存在しない**。

> "Implementers may independently set header size limits based on their deployment environment."  
> — *MCP SEP 2243 HTTP Standardization*

サーバー側での独自上限設定は仕様で許容されており、コミュニティでも広く用いられるパターン。

**懸念点:** 計画では `__truncated__: true` をレスポンスに付与する案が示されているが、
MCP 仕様の `CallToolResult` スキーマには標準フィールドとして `isError` が存在する。
`__truncated__: true` はカスタムフィールドであり、各 MCP クライアント（Claude Desktop / Continue.dev 等）
がこのフィールドを意図通りに解釈する保証はない。

一方で `isError: true` を使うと、クライアントが「ツール実行エラー」として処理してしまう問題がある
（リトライや異常系フローに入る可能性）。

**推奨:** `__truncated__: true` という慣例は概ね適切で、MCP 仕様上も禁止されていないが、
単純なバイト切り捨てではなく「台帳件数を減らす＋フラグを付与」という **データ削減型の切り捨て** を推奨。
raw byte slice はJSONが不正になるリスクがある（後述：懸念点 C3）。

---

### 1.5 LedgerLeapServer::$instructions へのガイダンス追記

**調査結果: ✅ 妥当な活用法**

MCP 仕様上、`instructions` は「LLM の理解を向上させるヒント」として定義されている
（`Tool.description` と同様の位置づけ）。ローカルモデル向けのパラメータ指示を
`instructions` に記載することは仕様の意図に合致する。

---

### 1.6 19ツール登録によるコンテキスト消費（Sprint 4 の位置づけ）

**調査結果: ⚠️ Sprint 4 の優先度引き上げを推奨**

MCP の「Client Best Practices」は、ツール定義のコンテキスト消費を深刻な問題として
**専用セクションを設けて詳述**している。

> "Loading every tool definition into the model's context window upfront wastes tokens,
> increases latency, and degrades model performance."  
> — *MCP Client Best Practices, 2025-11-25*

LedgerLeap は現在 19 ツールを登録しており、full スキーマをコンテキストに展開すると
それだけでかなりのトークンを消費する。対象計画の Sprint 1〜3 はレスポンスサイズを
削減するが、**ツール定義の定数コストは削減されない。**

`small-local` プロファイルでのツール削減（Sprint 4）は「オプション」扱いだが、
MCP 公式推奨のプログレッシブ検出パターンとも一致しており、**対症療法（レスポンス削減）と
根本対策（ツール数削減）の両輪が必要。** Sprint 4 の優先度引き上げを検討すべき。

---

## 2. 懸念点・要修正事項

### C1: include_content デフォルト変更は破壊的変更

**重要度: 高**

現在の `include_content` のデフォルトは `true`（全フィールド値を含む）。
これを `false` に変更すると、既存の Cloud LLM ワークフロー（Claude / GPT）や
自動テストへの影響が発生する可能性がある。

`tests/Unit/Mcp/Tools/SearchLedgersToolTest.php` が `include_content: true` の
動作を前提にしているテストを含む場合、テストが失敗する。

**対処策:** `CHANGELOG` またはマイグレーションノートへの記載と、
Sprint 1 テストで「`include_content=true` での従来動作が維持されること」の
アサーションを明示的に追加する。

---

### C2: Continue.dev の `defaultToolArgs` は公式非対応の可能性が高い

**重要度: 高（暫定対応 Section 5 に誤誘導リスクあり）**

対象計画の「即効策」セクションに以下の設定例がある。

```json
{
  "mcpServers": {
    "ledgerleap": {
      "defaultToolArgs": { ... }
    }
  }
}
```

**Continue.dev 公式ドキュメントには `defaultToolArgs` プロパティの記載が存在しない。**
Continue.dev の MCP 設定で認められているフィールドは
`name`, `command`, `args`, `env`, `cwd`, `requestOptions`, `connectionTimeout` であり、
`defaultToolArgs` は非対応の可能性が高い。

**代替手段（公式対応済み）:**

- Continue.dev の `rules` 機能: `.continue/rules/ledgerleap-local-llm.md` に
  「SearchLedgersTool 使用時は include_content=false, include_meta=false, limit=5 を使うこと」を記載
- LM Studio の system prompt に検索パラメータの指示を追記
- MCP server の `instructions` に記載（既に Sprint 3 の範囲として計画済み）

---

### C3: TruncatableResponse の「切り捨て方式」が未定義

**重要度: 中**

計画では「安全な切り捨てロジック」と記載されているが、具体的な実装方針が不明。

**`mb_substr` 等によるバイト単位の raw 切り捨ては JSON を破壊するリスクがある。**
LLM がパースエラーになる不完全なJSONをそのまま渡すと、エラー応答の連鎖が起きる。

**推奨する優先度付きデータ削減戦略（上位から順に削除）:**

1. `search_trace` を削除
2. `payloads.structured` / `payloads.visual` を削除
3. 各 attachment の `payloads.text.lines` を削除（`text` のみ残す）
4. `meta.ledger_defines` の `column_define` を削除（`id`, `name` のみ残す）
5. 配列の末尾件数を削減し `__truncated_items__` フィールドで削除件数を報告

これにより JSON 構造を維持したまま段階的に縮小できる。

---

### C4: Gemma4 31B の「8K〜16K tokens 安定上限」の根拠確認

**重要度: 低〜中**

計画では「Gemma4 31B の推奨コンテキスト予算は 8,000〜16,000 tokens」とあるが、
Gemma 3 / 4 世代のモデルは最大 128K context を公称している。

LM Studio での実効コンテキストは GPU VRAM やロード時の `contextLength` 設定に依存する。
「クラッシュ」の直接原因が context overflow なのか、VRAM 不足によるスワップ・OOM なのかで
対策が異なる可能性がある。

**Runbook に「クラッシュ原因の特定手順」を含めること:**
- LM Studio の context 使用率表示確認
- VRAM 使用量の確認（`nvidia-smi` 等）
- `contextLength` を明示的に設定して再現確認

---

### C5: search_trace 生成をサービス層で制御することの設計懸念

**重要度: 低**

計画では `LedgerService::searchLedgersForApi()` に `include_trace` フラグを渡す案が示されている。
これはサービス層が MCP 層の関心事（trace を含めるかどうか）に結合するため、
設計上のレイヤー分離が崩れる。

**代替案（推奨）:** trace は常にサービス層で生成し、MCP ツール層でフィルタリングする。
（生成コスト自体が低い場合は `include_trace=false` なら `unset($result['search_trace'])` するだけで十分）

---

## 3. 調査まとめ表

| 施策 | 妥当性 | 根拠ソース |
|---|---|---|
| include_meta / include_attachment_payloads / include_trace を false デフォルト | ✅ 確認済み | MCP Apps Patterns (chunked calls), Client Best Practices |
| include_content デフォルトを false に | ✅ 有効（破壊的変更の管理が必要） | LM Studio docs (context overflow causes erratic behavior) |
| GetLedgerDefinesTool: limit + JSON_PRETTY_PRINT 除去 | ✅ 確認済み | MCP Pagination 仕様、laravel/mcp Response::json() 実装確認 |
| TruncatableResponse 32KB 安全網 | ⚠️ 概念は妥当・切り捨て実装は要設計 | MCP SEP 2243 (実装者が独自に設定可能) |
| instructions へのガイダンス追記 | ✅ 確認済み | MCP spec instructions フィールドの設計意図と一致 |
| Sprint 4 model_profile slim モード | ✅ MCP 公式推奨（優先度引き上げを推奨） | MCP Client Best Practices（Progressive Discovery） |
| Continue.dev `defaultToolArgs` 設定例 | ❌ 公式非対応の可能性大 | Continue.dev 公式ドキュメントに該当フィールドなし |

---

## 4. 推奨アクション（優先度順）

| 優先度 | アクション | 対象 |
|---|---|---|
| MUST | C1: Sprint 1 PR に `include_content=true` の後退確認テストを明示追加 | `SearchLedgersToolTest.php` |
| MUST | C2: Section 5 の Continue.dev `defaultToolArgs` サンプルを削除し `rules/` 経由の代替手順に書き直す | `Reduction_Plan.md` Section 5 |
| SHOULD | C3: TruncatableResponse の実装をデータ削減型（フィールド削除優先）に設計 | `TruncatableResponse.php` 設計 |
| SHOULD | Sprint 4 を Sprint 2 と並行で「推奨」扱いに昇格検討 | Issue #211 スコープ |
| MAY | C4: Runbook に「クラッシュ原因の特定手順（context overflow vs VRAM 不足）」を追記 | `local-llm-mcp-setup.md` |

---

## 5. 参照元リンク

| 参照先 | 内容 |
|---|---|
| [MCP Client Best Practices](https://modelcontextprotocol.io/docs/develop/clients/client-best-practices) | Progressive Discovery, token usage |
| [MCP Apps Patterns – Chunked Calls](https://apps.extensions.modelcontextprotocol.io/api/documents/Patterns.html) | 大量データの分割取得パターン |
| [MCP Spec – Tools 2025-11-25](https://modelcontextprotocol.io/specification/2025-11-25/server/tools) | CallToolResult, isError スキーマ |
| [MCP Spec – Pagination](https://modelcontextprotocol.io/specification/2025-11-25/server/utilities/pagination) | cursor-based pagination 推奨 |
| [LM Studio – Context Length](https://lmstudio.ai/docs/python/model-info/get-context-length) | context overflow の影響 |
| [LM Studio – MCP API](https://lmstudio.ai/docs/developer/core/mcp) | `context_length` API パラメータ |
| [Continue.dev – MCP Servers](https://docs.continue.dev/customize/mcp-tools) | MCP 設定フィールド一覧 |
| `vendor/laravel/mcp/src/Response.php` | `Response::json()` の JSON エンコードフラグ確認 |

