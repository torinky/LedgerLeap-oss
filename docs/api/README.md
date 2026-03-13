# LedgerLeap API仕様概要

**詳細仕様:**
- [OpenAPI Specification (JSON)](openapi.json) - 完全なAPIリファレンス（Swagger/OpenAPI形式）

**関連ドキュメント:**
- [client-facing capability taxonomy](../work/llm-integration/2026-03-10_Client_Facing_Capability_Taxonomy.md) - client-facing capability の一覧とペルソナ別初期 skill セット
- [on-prem / local model onboarding design](../work/llm-integration/2026-03-13_OnPrem_Local_Model_Onboarding_Design.md) - on-prem / local model 前提の onboarding 役割分担
- [update path public contract](../work/llm-integration/2026-03-13_Update_Path_Public_Contract.md) - 更新系公開契約の planned workflow
- [MCP アーキテクチャと動作フロー](../development/MCP_Architecture_and_Flow.md) - LLM統合のMCPプロトコル詳解
- [MCP プロンプトガイドライン](../development/MCP_Prompt_Guidelines.md) - MCP経由でのAPI活用方法

## 概要説明
LedgerLeap APIは、外部アプリケーションやフロントエンドフレームワークとの連携を可能にするために提供されるHTTPベースのインターフェースです。このAPIを利用することで、台帳データの操作、フォルダ情報の取得、ファイルのアップロードなど、LedgerLeapの主要な機能をプログラム経由で利用できます。

client-facing では、API の役割を **検索・登録・更新・承認・集計の業務フローを支える公開契約** として扱います。

## on-prem / local model onboarding における API の位置づけ

Sprint 4 時点では、on-prem / local model 前提の onboarding で REST API を次のように扱います。

- **接続契約の主導線**: ベース URL、認証、OpenAPI、HTTP エンドポイントを確認する入口
- **非MCP クライアントの主要導線**: 既存システム統合や API gateway 経由の利用で参照する契約
- **bootstrap discovery の具体 contract は未確定**: 初回 bundle 解決用 endpoint / schema は Sprint 6 で定義する

つまり、Sprint 4 の段階では **API は「接続方法を理解するための入口」** とし、
role / model / client に応じた最小 bundle の自動解決は将来の discovery contract 側へ分離します。

## LLM統合 (MCP) について

LedgerLeap APIは、MCP (Model Context Protocol) を通じてLLMクライアント（ChatGPT、Claude等）からも利用可能です。自然言語での台帳操作や検索が可能で、以下のような対話が実現されています：

**ユーザー:** 「昨日私が作成した日報を見せて」  
**システム:** 適切な検索パラメータでAPI呼び出し → 視覚的に整理された結果を表示

詳細な技術情報については、[MCP アーキテクチャと動作フロー](../development/MCP_Architecture_and_Flow.md)を参照してください。

## 認証

LedgerLeap APIの認証は、**Laravel Sanctum** を利用しています。

*   **SPA認証**:
    LedgerLeap自身のフロントエンド（SPA: Single Page Application）から利用される場合、Sanctumのクッキーベースのセッション認証が利用されます。ログイン時に認証情報がセキュアなクッキーとして設定され、以降のリクエストで自動的に認証が行われます。
*   **APIトークン認証**:
    サードパーティアプリケーションやスクリプトからAPIを利用する場合は、APIトークンによる認証が必要です。ユーザーは自身のプロファイル画面などからAPIトークンを発行し、そのトークンをリクエストヘッダーに含めることで認証を行います。

    **ヘッダー例 (APIトークン認証):**
    ```
    Authorization: Bearer <YOUR_API_TOKEN>
    Accept: application/json
    ```

## 共通リクエスト形式

*   **HTTPメソッド**: 標準的なHTTPメソッド（GET, POST, PUT, DELETEなど）を使用します。
*   **ベースURL**: APIのベースURLは `https://your-ledgerleap-domain.com/api/` となります。（実際のドメインに置き換えてください）
*   **リクエストボディ (POST/PUT)**:
    *   データを作成・更新するリクエスト (POST, PUT) では、リクエストボディはJSON形式であることが期待されます。
    *   `Content-Type` ヘッダーには `application/json` を指定してください。
    ```text
    {
        "title": "新しい台帳エントリ",
        "content": {
            "field1": "値1",
            "field2": "値2"
        },
        "folder_id": 10
    }
    ```
*   **共通ヘッダー**:
    *   `Content-Type: application/json` (POST/PUTリクエストの場合)
    *   `Accept: application/json` (レスポンス形式としてJSONを期待する場合)

## 共通レスポンス形式

*   **成功時のレスポンス**:
    *   通常、成功時のレスポンスボディはJSON形式で返却されます。
    *   主要なデータは `data` キー以下に格納されることが一般的です。
    *   一覧取得APIなどでは、ページネーション情報（`meta`, `links` キーなど）が含まれる場合があります。

    **例 (単一リソース取得):**
    ```text
    {
        "data": {
            "id": 1,
            "title": "台帳エントリのタイトル",
            "status": "draft"
        }
    }
    ```
    **例 (リソース一覧取得):**
    ```text
    {
        "data": [
            { "id": 1, "title": "..." },
            { "id": 2, "title": "..." }
        ],
        "links": {
            "first": "/api/ledgers?page=1",
            "last": "/api/ledgers?page=5",
            "prev": null,
            "next": "/api/ledgers?page=2"
        },
        "meta": {
            "current_page": 1,
            "from": 1,
            "last_page": 5,
            "total": 42
        }
    }
    ```
*   **エラー時のレスポンス**:
    *   エラー発生時もJSON形式でエラー情報が返却されます。
    *   `message` キーにエラーの概要メッセージが含まれます。
    *   バリデーションエラーの場合は、`errors` キーに各フィールドごとのエラー詳細が含まれることがあります。

## 主要エンドポイント

| メソッド | パス | 説明 | 備考 |
|---------|------|------|------|
| **GET** | `/api/v1/ledger-defines` | 台帳定義（テンプレート）の一覧取得 | フォルダID等でフィルタ可能 |
| **POST** | `/api/v1/ledgers` | 新しい台帳レコードの作成 | |
| **GET** | `/api/v1/search` | 高度な全文検索（キーワード / 条件検索） | 添付資料を含む確認に利用 |

## planned: update path 公開契約（Sprint 5 定義）

> **注意:** この節は **planned public contract** です。実装済み endpoint の正確な一覧は引き続き [OpenAPI Specification (JSON)](openapi.json) を正本とします。ここに書く planned contract は、実装後に OpenAPI へ反映します。

Sprint 5 では、更新系公開契約を次のように整理しました。

| 種別 | メソッド | パス | 役割 |
|---|---|---|---|
| supporting read path | **GET** | `/api/v1/ledgers/{ledger}` | 更新前に単一レコードの最新内容・状態を確認する |
| primary update path | **PATCH** | `/api/v1/ledgers/{ledger}` | 必要項目だけを部分更新する |
| deferred contract | **PUT** | `/api/v1/ledgers/{ledger}` | 将来の完全置換向け候補。初期公開契約の主対象にはしない |

### Sprint 5 の判断

- update path は **検索結果だけで即更新せず、単一レコード read path を前提**にする
- 初期公開契約の主契約は **PATCH** とする
- `PENDING_INSPECTION` / `PENDING_APPROVAL` の編集保存では、client-facing に **`DRAFT` へ戻る** ことを説明する
- `APPROVED` は初期公開契約では **原則更新不可** とする
- `dry_run` は拡張候補だが、初期 API 実装の必須要件にはしない

### 検索API活用例

```bash
curl -H "Authorization: Bearer {token}" \
     -H "Accept: application/json" \
     "http://localhost/api/v1/search?q=日報&limit=5"
```

詳細なパラメータやスキーマについては [OpenAPI Specification (JSON)](openapi.json) を参照してください。planned contract の判断根拠は [update path public contract](../work/llm-integration/2026-03-13_Update_Path_Public_Contract.md) を参照してください。

## セキュリティ考慮事項

APIを利用する際は、以下のセキュリティ考慮事項に留意してください。

*   **認証情報の管理**:
    APIトークンや認証情報は厳重に管理し、第三者に漏洩しないようにしてください。
*   **HTTPSの利用**:
    APIへのアクセスは必ずHTTPS経由で行い、通信の盗聴や改ざんを防止してください。
*   **入力データの検証**:
    APIリクエストで送信するデータは適切に検証し、不正なデータや攻撃コードが含まれないようにしてください。
*   **エラーメッセージの取り扱い**:
    エラーメッセージにはシステム内部の情報が含まれることがあります。外部に漏洩しないように注意してください。

## レート制限
現在、APIリクエストに対する明確なレート制限は設けていませんが、将来的にシステムの安定性維持のために導入される可能性があります。レート制限が導入された場合は、適切なHTTPヘッダー（`X-RateLimit-Limit`, `X-RateLimit-Remaining`など）で情報が提供されます。

## 今後の拡充予定
*   各APIエンドポイントの詳細な仕様（リクエストパラメータ、レスポンスフィールド、具体的なパスなど）のドキュメント化。
*   より高度な検索・フィルタリングオプションの追加。
*   Webhook機能の提供。
*   APIバージョニングの導入。

このドキュメントはLedgerLeap APIの概要を提供するものです。APIは継続的に改善・拡張されるため、最新の情報については開発チームにお問い合わせください。
