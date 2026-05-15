# MCP ローカルLLMレスポンスサイズ削減計画

**作成日:** 2026年05月15日
**種別:** 作業計画（スプリント分解）
**親Issue:** #211
**参照:**
- `app/Mcp/Tools/SearchLedgersTool.php`
- `app/Mcp/Tools/GetLedgerDefinesTool.php`
- `app/Mcp/Servers/LedgerLeapServer.php`
- `app/Services/LedgerService.php`
- `docs/development/MCP_Architecture_and_Flow.md`
- `docs/work/llm-integration/2025-09-28_MCP_Response_Optimization_Plan.md`

---

## 1. 問題の概要

OpenCode・Continue.dev・OpenClaw から LM Studio の **Gemma4 31B** に MCPツールを呼び出すと、
ツールレスポンスがモデルのコンテキストウィンドウを超えてクラッシュする。

### 1.1 クラッシュ再現パス

```
ユーザー質問 → LLM → SearchLedgersTool (summary format) → 巨大レスポンス → LLM クラッシュ
```

**実測ボトルネック（`summary` フォーマット、10件検索時）:**

| 構成要素 | 概算トークン数 | 備考 |
|---|---|---|
| `meta.ledger_defines` (全定義 + column_define) | 3,000〜15,000 | 運用規模次第 |
| `meta.folders` (祖先パス付き) | 500〜3,000 | ネストが深い場合 |
| `meta.users` | 100〜500 | - |
| 1台帳あたりの attachment payloads | 200〜800 | payloads.text/structured/visual × 全添付 |
| `search_trace` (シノニム展開ログ) | 200〜1,000 | デバッグ用途 |
| `content` フィールド (include_content=true デフォルト) | 500〜5,000 | 台帳の全フィールド値 |
| **合計 (10件 × 添付2件平均)** | **10,000〜40,000 tokens** | ローカルLLMで容易にクラッシュ |

Gemma4 31B の推奨コンテキスト予算（ツール結果含む）は実測で約 8,000〜16,000 tokens。
`SearchLedgersTool` 1回の返答でこれを超える設定が存在する。

### 1.2 副次的なサイズ要因

- `GetLedgerDefinesTool`: `JSON_PRETTY_PRINT` + 全件返却 + `columns[].options`（select の全選択肢）
- `GetActivityLogTool`: デフォルト `limit=50` が大きめ
- 19ツール分のスキーマ定義がコンテキストに常駐
- `buildStructuredPayload()`: `pages` / `text_blocks` / `key_value_pairs` に**件数上限なし**（複数ページ PDF では数百〜数千要素になり得る）

### 1.3 コードレビューで確認されたバグ

> 詳細は `2026-05-15_MCP_LocalLLM_Plan_Validity_Investigation.md` を参照

**Bug A: `include_content=false` の実装不備** (`SearchLedgersTool.php` L130 付近)

```php
// 現状（バグあり）
if (! $includeContent) {
    $displayFields['content_preview'] = $this->generateContentPreview(...);
    // ← $ledger->content の削除が漏れている
    //   → フラグ false でもフルコンテンツがレスポンスに含まれ続ける
}
```

**修正方法:** `$includeContent` が `false` のブロック末尾に以下を追加する。

```php
unset($ledger->content);
```

**Bug B: `include_attachment_payloads` パラメータが未実装**

現行コードにパラメータが存在しない（スキーマ未定義、ハンドラ未参照）。
Sprint 1 で新規追加が必要。`false`（デフォルト）の場合は `buildStructuredPayload()` の
呼び出し自体をスキップしないと効果がない点に注意。

---

## 2. 調査根拠

> 外部調査の詳細・根拠一覧は `2026-05-15_MCP_LocalLLM_Plan_Validity_Investigation.md` を参照

### 2.1 コミュニティ類似事例

LM Studio / Ollama + MCP の組み合わせで同様のクラッシュが多数報告されている。

**共通対策パターン:**
1. **ツールレスポンスのバイト上限** — 8K〜32K bytes で切り捨て＋`__truncated__: true` を付与
2. **メタを opt-in 化** — `include_meta=false` をデフォルトにし、必要時のみ付与
3. **段階的取得パターンの強制** — 一覧は ID+タイトルのみ、詳細は `GetLedgerDetail` で別途取得
4. **サーバー側 instructions でのパラメータ指示** — `LedgerLeapServer::$instructions` に記載
5. **`model_profile` 分岐** — ローカルモデル向けに slim レスポンスパスを用意

### 2.2 本プロジェクト特有の既存設計との整合

- `GetClientBootstrapManifestTool` は既に `model_profile: small-local / general-local / remote-capable` を受け付ける
- `format=raw` vs `format=summary` の分岐は実装済みだが、**summary がデフォルトかつ最大サイズ**
- `include_content` / `content_preview_length` パラメータは既存だが **LLM が知らずにデフォルト使用**

### 2.3 外部仕様調査による新規知見（妥当性検証から追加）

| 知見 | 根拠 |
|---|---|
| MCP 仕様にはレスポンスサイズ上限が存在しない（実装者が任意設定） | MCP SEP 2243 HTTP Standardization |
| メタ opt-in 化・段階的取得は MCP 公式推奨パターン | MCP Apps Patterns – Chunked Calls |
| ツール定義のコンテキスト消費を MCP 公式が「深刻な問題」と明言 | MCP Client Best Practices |
| LM Studio API は `allowed_tools` フィールドで呼び出し可能ツールを制限できる | LM Studio Developer Docs |
| Continue.dev MCP 設定に `defaultToolArgs` フィールドは**存在しない** | Continue.dev 公式ドキュメント調査 |
| `TruncatableResponse` の raw byte slice は JSON を破壊するリスクあり | JSON仕様・実装分析 |

**⚠️ Continue.dev の `defaultToolArgs` について:**  
前バージョンの計画に記載されていた `defaultToolArgs` 設定は Continue.dev の公式未対応機能。
暫定対応は `LedgerLeapServer::$instructions` または Continue.dev の `rules/` ディレクトリを使用すること。
（Section 5 参照）

---

## 3. 対応方針

**基本方針:** `SearchLedgersTool` を「軽い一覧」+「必要時のみ詳細」の2段階導線に整理する。
デフォルトのトークン消費を削減し、ローカルLLMでも安定動作できるようにする。

### 変更対象ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `app/Mcp/Tools/SearchLedgersTool.php` | `include_meta` / `include_attachment_payloads` / `include_trace` パラメータ追加、デフォルト変更 |
| `app/Services/LedgerService.php` | `search_trace` の返却を条件付きにする |
| `app/Mcp/Tools/GetLedgerDefinesTool.php` | compact 出力・limit・include_options パラメータ追加 |
| `app/Mcp/Traits/TruncatableResponse.php` | **新規作成** — レスポンス上限トレイト |
| `app/Mcp/Servers/LedgerLeapServer.php` | `$instructions` にローカルLLM向けガイダンス追記 |
| `docs/runbooks/local-llm-mcp-setup.md` | **新規作成** — LM Studio / OpenCode / Continue.dev 設定ガイド |

---

## 4. スプリント分解

### Sprint 1: SearchLedgersTool のデフォルト削減【最優先・最大効果】

**目標:** デフォルト `summary` フォーマットのトークン消費を 70% 削減する

**タスク:**

- [ ] `SearchLedgersTool::schema()` に以下のパラメータを追加:
  ```php
  'include_meta'               => false  // デフォルト: meta辞書を省略
  'include_attachment_payloads' => false  // デフォルト: payloads.text/structured/visual を省略
  'include_trace'              => false  // デフォルト: search_trace を省略
  ```
- [ ] `SearchLedgersTool::handle()` で上記フラグを参照し、フィールドを条件付き出力
- [ ] **【Bug A 修正】** `include_content=false` 時に `$ledger->content` を確実に除去する:
  ```php
  if (! $includeContent) {
      $displayFields['content_preview'] = $this->generateContentPreview(...);
      unset($ledger->content);  // ← この行が現状漏れている
  }
  ```
- [ ] `include_content` のデフォルトを `true` → `false` に変更
  - summary モードでは `content_preview_length=200` のプレビューのみ
  - 完全な content は `GetLedgerDetailTool` で取得
- [ ] **【Bug B 修正 + 新規実装】** `include_attachment_payloads` パラメータを実装:
  - `false`（デフォルト）の場合は `buildStructuredPayload()` / `buildTextPayload()` / `buildVisualPayload()` の呼び出しを**スキップ**
  - `false` 時のデフォルト出力: `attachment_id / filename / role / order / source / mime_type` のみ
  - `true` 時のみ: `payloads / routes / resource_uri / access_guide` を完全出力
- [ ] `LedgerService::searchLedgersForApi()` の `search_trace` は常に生成し、MCP層で `include_trace` フラグが false ならツール側で除去する（サービス層に MCP 関心事を混入させない）
- [ ] テスト: デフォルトレスポンスのトークン数推定をアサーション（JSON 文字列長 ≤ 16,000 bytes）

**完了条件:**
- `SearchLedgersTool` をデフォルト引数で呼び出したとき、レスポンスが 16 KB 以下
- `include_meta=true`, `include_content=true`, `include_attachment_payloads=true` で従来と同等のレスポンスが返ること（後退なし）
- Bug A: `include_content=false` でレスポンスに `content` キーが含まれないこと
- Bug B: `include_attachment_payloads=false` でレスポンスに `payloads` キーが含まれないこと
- 既存テストが通ること

---

### Sprint 2: GetLedgerDefinesTool コンパクト化 + TruncatableResponse トレイト

**目標:** 定義一覧の肥大化を防ぎ、全ツールに共通の安全網を設ける

**タスク:**

- [ ] `GetLedgerDefinesTool::handle()` の変更:
  - `JSON_PRETTY_PRINT` → コンパクト JSON に変更（空白を除去）
  - `limit` パラメータ追加（デフォルト 20、最大 100）
  - `include_options` パラメータ追加（デフォルト `false`: select の全選択肢を省略）
  - `offset` パラメータ追加（ページネーション対応）
- [ ] `app/Mcp/Traits/TruncatableResponse.php` を新規作成:
  ```php
  trait TruncatableResponse
  {
      private const MAX_RESPONSE_BYTES = 32_000;

      /**
       * データ削減型の安全な切り捨て（JSON破壊リスクなし）
       * 削除優先順位: search_trace → payloads.structured/visual → payloads.text.lines
       *               → meta.ledger_defines の column_define → ledgers 末尾件数削減
       */
      protected function truncateIfNeeded(array $data): array
      {
          $json = json_encode($data, JSON_UNESCAPED_UNICODE);
          if (mb_strlen($json, '8bit') <= self::MAX_RESPONSE_BYTES) {
              return $data;
          }
          // 段階的フィールド削除 + __truncated__: true を付与
          // raw byte slice は JSON を破壊するため使用禁止
      }
  }
  ```
  > **設計注意:** `mb_substr` 等による raw byte 切り捨ては JSON 構造を破壊するため使用禁止。
  > フィールド削除優先度（高→低）の順で段階的に縮小し、最終的に件数削減で調整する。
- [ ] `SearchLedgersTool`・`GetLedgerDefinesTool`・`GetActivityLogTool` に `TruncatableResponse` を適用
- [ ] テスト: `TruncatableResponse` 単体テスト（境界値 / truncated フラグ検証）

**完了条件:**
- `GetLedgerDefinesTool` が 20件以下でコンパクトJSONを返す
- 3万バイト超のレスポンスを強制的に切り捨てて `__truncated__: true` を付与する

---

### Sprint 3: LedgerLeapServer instructions 更新 + ローカルLLM設定 Runbook

**目標:** LLM自身がスリム化パスを選択できるよう誘導し、運用者向け設定手順を整備する

**タスク:**

- [ ] `LedgerLeapServer::$instructions` に以下のガイダンスを追記:
  ```
  For local models (LM Studio, Ollama): always use include_content=false,
  include_meta=false on SearchLedgersTool. Use mode=count before fetching
  records. Fetch full detail with GetLedgerDetailTool only when needed.
  ```
- [ ] `docs/runbooks/local-llm-mcp-setup.md` を新規作成:
  - LM Studio の推奨設定 (`num_ctx: 32768` 以上、`n_gpu_layers` 等)
  - OpenCode / Continue.dev / OpenClaw の MCP 設定例（デフォルト引数プリセット）
  - クライアント別のトラブルシューティング
- [ ] `resources/ai/capabilities/*.yaml` の `model_profile: general-local` に slim パスの使用指示を追記（該当 YAML を確認して対象ファイルを特定）

**完了条件:**
- `instructions` にローカルモデル向けの検索導線が明記されている
- Runbook が `docs/runbooks/local-llm-mcp-setup.md` に存在する

---

### Sprint 4（オプション）: model_profile 対応の slim サーバーモード

**目標:** `GetClientBootstrapManifestTool` の `model_profile` と連動して、ローカルモデル向けにツール数と出力を自動絞り込む

**タスク:**
- [ ] `LedgerLeapServer` に `protected string $modelProfile = 'general-local'` を追加
- [ ] `model_profile=small-local` 時に登録ツール数を削減（`GetSearchTermsTool`, `GetUserActivityStatsTool`, `GetFolderStatsTool`, `ReadMcpResourceTool` を除外）
- [ ] HTTP transport で `?model_profile=small-local` クエリパラメータを受け取りサーバー初期化時に反映する仕組みを設計

**完了条件（TBD）:**
- `small-local` モードで登録ツールが 10 件以下になる
- 既存テストに後退なし

---

## 5. 即効策（実装前の暫定対応）

Sprint 1 実装完了まで、クライアント設定で症状を緩和できる。

### LM Studio 設定

```
Context Length: 32768 以上を設定（推奨: 65536）
GPU Layers: 全レイヤーをGPUオフロード推奨
contextOverflowPolicy: stopAtLimit（コンテキスト超過時にクラッシュさせず停止）
```

**LM Studio API の `allowed_tools` によるツール制限（APIアクセス時）:**

LM Studio の API リクエストに `allowed_tools` フィールドを指定することで、
特定のリクエストで呼び出せるツールを絞り込める（LLM に渡すスキーマを削減）。
クライアントアプリケーション（OpenCode等）がこのフィールドに対応している場合は有効。

```json
{
  "allowed_tools": [
    "SearchLedgersTool",
    "GetLedgerDetailTool",
    "GetLedgerDefinesTool"
  ]
}
```

### Continue.dev（`config.json` または `.continue/rules/`）

> ⚠️ **注意:** Continue.dev の `mcpServers` 設定に `defaultToolArgs` フィールドは**存在しない**。
> 下記の `rules/` ディレクトリへの Markdown ファイル追加を使用すること。

**推奨: `.continue/rules/ledgerleap-local-llm.md` を作成**

```markdown
# LedgerLeap MCP ローカルLLM向けルール

MCPツールを呼び出す場合のルール:
- SearchLedgersTool: 必ず `include_content=false`, `include_meta=false`, `limit=5` を使用
- 詳細は SearchLedgersTool を呼ぶ前に GetLedgerDetailTool で個別に取得
- 最初は `mode=count` で件数を確認してから検索する
```

### OpenCode / OpenClaw

ツール呼び出し前に LLM に以下を伝える（システムプロンプトまたは最初のメッセージ）:

```
MCPを使う際は、SearchLedgersTool では必ず include_content=false,
include_meta=false, limit=5 を指定してください。
まず mode=count で件数を確認してから、必要な件数だけ取得してください。
```

### OpenCode での MCP 設定例（`.opencode/config.json`）

OpenCode がツール引数のデフォルト設定に対応している場合は以下を参考にする
（OpenCode のバージョンにより対応状況が異なる可能性がある点に注意）:

```json
{
  "mcp": {
    "servers": {
      "ledgerleap": {
        "systemPrompt": "SearchLedgersTool では include_content=false, include_meta=false, limit=5 をデフォルトで使用してください。"
      }
    }
  }
}
```

---

## 6. 完了後の期待効果

| 指標 | 現状 | Sprint 1後 | Sprint 2後 |
|---|---|---|---|
| SearchLedgersTool デフォルト応答 (10件) | 10,000〜40,000 tokens | 2,000〜5,000 tokens | 2,000〜5,000 tokens + 安全網 |
| GetLedgerDefinesTool (全件) | 無制限 | 変化なし | 20件 + compact JSON |
| クラッシュ発生率 (Gemma4 31B) | 高 | 大幅低下 | ほぼゼロ |

---

## 7. 参照リンク

- `app/Mcp/Tools/SearchLedgersTool.php` — 現行実装
- `app/Mcp/Tools/GetLedgerDefinesTool.php` — 現行実装
- `app/Mcp/Servers/LedgerLeapServer.php` — サーバー設定
- `app/Services/LedgerService.php` — `searchLedgersForApi()`
- `docs/development/MCP_Architecture_and_Flow.md` — アーキテクチャ仕様
- `docs/work/llm-integration/2026-05-15_MCP_LocalLLM_Plan_Validity_Investigation.md` — **妥当性調査レポート（外部仕様・類似実装・懸念点）**
- `docs/work/llm-integration/2025-09-28_MCP_Response_Optimization_Plan.md` — 前回最適化計画
- `docs/work/llm-integration/2025-10-03_MCP_SearchLedgersTool_Response_Refactoring_Plan.md` — 前回リファクタリング計画

