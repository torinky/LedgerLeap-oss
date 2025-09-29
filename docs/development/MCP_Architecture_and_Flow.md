# MCP アーキテクチャと動作フロー

**作成日:** 2025年1月19日  
**対象:** LedgerLeap開発者・技術者  
**関連ドキュメント:**
- [MCP プロンプトガイドライン](./MCP_Prompt_Guidelines.md)
- [API仕様概要](../api/README.md)
- [MCP応答最適化計画](../work/2025-09-28_MCP_Response_Optimization_Plan.md)

---

## 概要

このドキュメントでは、LedgerLeapにおけるMCP (Model Context Protocol) の技術的な動作メカニズムと、`LedgerLeapServer`クラスの`instructions`プロパティがLLMとの対話においてどのような役割を果たすかを詳細に解説します。

MCPは、LLMクライアント（ChatGPT、Claude等）とアプリケーションサーバー間の通信プロトコルであり、LedgerLeapでは台帳管理機能をLLMから自然言語で操作可能にするために活用されています。

---

## MCPアーキテクチャ概要

### システム構成図

```
┌─────────────────┐    MCP Protocol    ┌─────────────────┐
│   LLMクライアント   │ ←─────────────────→ │ LedgerLeap      │
│  (Claude/GPT等)  │                    │ MCPサーバー       │
│                 │                    │                 │
│ ・自然言語処理     │                    │ ・台帳操作ツール   │
│ ・ツール呼び出し   │                    │ ・データベース    │  
│ ・応答生成       │                    │ ・認証・権限制御   │
└─────────────────┘                    └─────────────────┘
       │                                        │
       │                                        │
   ユーザー対話                              Laravel アプリ
```

### 主要コンポーネント

#### 1. LedgerLeapServer クラス
```php
// app/Mcp/Servers/LedgerLeapServer.php
class LedgerLeapServer extends Server
{
    protected string $name = 'Ledger Leap Server';
    protected string $version = '0.0.1';
    protected string $instructions = '...'; // LLMへの動作指示
    protected array $tools = [
        GetLedgerDefinesTool::class,
        SearchLedgersTool::class,
        CreateLedgerTool::class,
    ];
}
```

#### 2. MCPツールクラス群
- **SearchLedgersTool**: 台帳検索機能
- **CreateLedgerTool**: 台帳作成機能  
- **GetLedgerDefinesTool**: 台帳定義取得機能

#### 3. Laravel MCPライブラリ
- MCPプロトコルの実装
- STDIO通信による標準入出力ベースの通信
- ツール呼び出しの管理とルーティング

---

## `instructions`プロパティの動作フロー

### フェーズ1: 初期化（MCPサーバー起動時）

```php
// LedgerLeapServer の instructions プロパティ
protected string $instructions = <<<'MARKDOWN'
    You are an assistant for the LedgerLeap ledger management system.
    
    When using tools that return responses with `__summary__`, include that summary...
    
    For search queries like "show me yesterday's reports" or "昨日作成した日報を見せて":
    1. Use SearchLedgers with appropriate date filters (created_from, created_to)
    2. Set format="summary" for better formatted responses
    3. Include creator_id filter when the user refers to "my" or "私の" documents
MARKDOWN;
```

**実行される処理:**
1. MCPサーバープロセスが起動
2. `instructions`がサーバーメタデータとして内部に保存
3. ツール一覧と共に、LLMクライアント接続待機状態に移行

### フェーズ2: 接続・能力交換（LLMクライアント接続時）

#### シーケンス図
```
LLMクライアント                MCPサーバー
       │                           │
       │ ──── initialize ──────→   │
       │                           │ Server情報を準備
       │                           │ ・name, version
       │                           │ ・instructions 
       │                           │ ・利用可能ツール一覧
       │                           │
       │ ←── server_info ────────   │
       │                           │
LLMが instructions を          │
システムプロンプトに統合        │
       │                           │
```

#### 実際の情報交換内容
```json
{
  "jsonrpc": "2.0",
  "result": {
    "name": "Ledger Leap Server",
    "version": "0.0.1",
    "instructions": "You are an assistant for the LedgerLeap ledger management system...",
    "capabilities": {
      "tools": {
        "SearchLedgersTool": {
          "description": "Search for ledgers based on various criteria.",
          "inputSchema": {
            "type": "object",
            "properties": {
              "q": {"type": "string", "description": "The search keyword..."},
              "creator_id": {"type": "integer", "description": "The ID of the user..."},
              "format": {"type": "string", "enum": ["raw", "summary"], "default": "raw"}
            }
          }
        }
      }
    }
  }
}
```

### フェーズ3: 実行時の指示効果（ユーザー対話時）

#### ユーザー入力の処理例

**ユーザー:** 「昨日私が作成した日報を見せて」

#### LLMの内部的な判断プロセス

LLMは以下の情報を統合して判断：

```
┌─────────────────────────────────────────────────────────────┐
│ LLMの思考プロセス（概念的）                                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ 1. ユーザー入力解析:                                          │
│    - "昨日" → 日付フィルタが必要                              │
│    - "私が作成した" → creator_id フィルタが必要                │
│    - "日報" → キーワード検索が必要                            │
│    - "見せて" → 検索・表示が必要                              │
│                                                             │
│ 2. instructions による指示参照:                              │
│    - "For search queries like '昨日作成した日報を見せて'"     │
│    - "Use SearchLedgers with appropriate date filters"      │
│    - "Set format='summary' for better formatted responses"  │
│    - "Include creator_id filter when user refers to '私の'" │
│                                                             │
│ 3. ツール呼び出し決定:                                        │
│    - ツール: SearchLedgers                                  │
│    - パラメータ: created_from, created_to, creator_id, q    │
│    - フォーマット: summary                                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### 実際のツール呼び出し
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "SearchLedgersTool",
    "arguments": {
      "creator_id": 1,
      "created_from": "2025-01-18",
      "created_to": "2025-01-18",
      "q": "日報",
      "format": "summary"
    }
  }
}
```

#### MCPツールからの応答
```json
{
  "jsonrpc": "2.0",
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"ledgers\":[{\"id\":112,\"__display_fields__\":{\"件名\":\"2025年1月18日営業日報\",\"ステータス\":\"承認待ち\",\"更新日時\":\"2025年1月18日 18:30\"}}],\"total\":2,\"__summary__\":\"あなたが昨日作成した台帳は2件です。\"}"
      }
    ]
  }
}
```

#### LLMによる応答生成

`instructions`の以下の指示に基づいて応答を構成：

```markdown
When using tools that return responses with `__summary__`, 
include that summary at the beginning of your response.

When displaying lists of objects that contain `__display_fields__`, 
present the information in a user-friendly format:
- Use the Japanese field names from `__display_fields__`
- Present data in bullet points or tables for readability
```

**最終的なLLM応答:**
```
あなたが昨日作成した台帳は2件です。

📋 **見つかった台帳:**
• **件名:** 2025年1月18日営業日報
  - **ステータス:** 承認待ち
  - **更新日時:** 2025年1月18日 18:30

• **件名:** システム改修作業報告
  - **ステータス:** 下書き
  - **更新日時:** 2025年1月18日 20:15

何か他にお探しのものはありますか？
```

---

## 技術実装詳細

### Laravel MCP ライブラリの活用

#### サーバー起動コマンド
```bash
php artisan mcp:start ledgerleap:mcp
```

#### 設定ファイル（推定構造）
```php
// config/mcp.php または サービスプロバイダ内
'servers' => [
    'ledgerleap:mcp' => [
        'class' => LedgerLeapServer::class,
        'transport' => 'stdio', // 標準入出力による通信
    ],
]
```

#### 通信プロトコル詳細

**STDIO Transport:**
- MCPサーバーは標準入力からJSONRPCメッセージを受信
- 標準出力にJSONRPCレスポンスを送信
- エラー情報は標準エラー出力に送信

**メッセージフロー:**
```
stdin  ← {"jsonrpc":"2.0","method":"tools/call","params":{...}}
stdout → {"jsonrpc":"2.0","result":{"content":[...]}}
stderr → エラーログ（該当時のみ）
```

### 認証・セキュリティ機構

#### トークンベース認証
```php
// SearchLedgersTool::handle() 内
$token = getenv('MCP_AUTH_TOKEN');
if (!$token) {
    return Response::error('Authentication token not provided.', 401);
}

$accessToken = PersonalAccessToken::findToken($token);
if (!$accessToken || !$accessToken->tokenable) {
    return Response::error('Invalid authentication token.', 401);
}

$user = $accessToken->tokenable;
Auth::setUser($user); // 認証状態設定
```

#### 権限チェック
```php
// LedgerService::searchLedgersForApi() 内
$results = $this->ledgerService->searchLedgersForApi(
    user: $user, // 認証済みユーザーを渡す
    params: $parameters,
);
```

---

## 応答最適化メカニズム

### `format=summary` パラメータの処理

#### データ変換処理
```php
// SearchLedgersTool::handle() 内
if ($format === 'summary') {
    $ledgers = collect($results['ledgers'])->map(function ($ledger) {
        // ステータスの日本語化
        $statusDisplay = match ($ledger->status->value) {
            'none' => '下書き',
            'in_progress' => '処理中',
            'pending_inspection' => '点検待ち',
            'pending_approval' => '承認待ち',
            'approved' => '承認済み',
            'rejected' => '却下',
            default => $ledger->status->value,
        };

        // 日付フォーマット
        $updatedAtFormatted = Carbon::parse($ledger->updated_at)
            ->format('Y年m月d日 H:i');

        // 表示用フィールド追加
        $ledger['__display_fields__'] = [
            '件名' => $ledger->define->title ?? '不明',
            'ステータス' => $statusDisplay,
            '更新日時' => $updatedAtFormatted,
        ];
        
        return $ledger;
    });

    // サマリー生成
    $summary = "台帳が{$results['total']}件見つかりました。";
    if ($results['total'] > 0) {
        $summary = "あなたが作成した台帳は{$results['total']}件です。";
    }

    return Response::json([
        'ledgers' => $ledgers,
        'total' => $results['total'],
        '__summary__' => $summary, // LLM向けサマリー
    ]);
}
```

### LLMへのヒント埋め込み

#### 構造化応答の生成
```json
{
  "ledgers": [
    {
      "id": 112,
      "status": "pending_approval",
      "updated_at": "2025-01-18T18:30:00.000000Z",
      // ... 元のデータ ...
      
      "__display_fields__": {
        "件名": "2025年1月18日営業日報",
        "ステータス": "承認待ち", 
        "更新日時": "2025年1月18日 18:30"
      }
    }
  ],
  "total": 2,
  "__summary__": "あなたが昨日作成した台帳は2件です。"
}
```

**設計意図:**
- `__summary__`: LLMが応答冒頭で使用する自然言語サマリー
- `__display_fields__`: LLMが視覚的に整理された表示を生成するためのヒント
- 構造化データ: 元のAPI形式を維持（プログラマティックアクセス可能）

---

## パフォーマンス考慮事項

### 通信オーバーヘッド
- **STDIO Transport**: プロセス間通信のオーバーヘッドは最小限
- **JSON Serialization**: 大量データ時は応答サイズに注意
- **Database Query**: `format=summary`時の追加変換処理コスト

### スケーラビリティ
- **プロセス分離**: 各MCPサーバーインスタンスは独立プロセス
- **状態管理**: サーバーインスタンスはステートレス設計
- **認証キャッシュ**: トークン検証の最適化機会

---

## デバッグ・ログ機能

### ログ出力例
```php
// SearchLedgersTool 内でのデバッグログ
Log::info('MCP SearchLedgers called', [
    'user_id' => $user->id,
    'parameters' => $parameters,
    'results_count' => $results['total']
]);
```

### MCP Inspector の活用
```bash
# MCP通信の詳細確認
php artisan mcp:inspector ledgerleap:mcp
# → http://localhost:6274 でブラウザベースのデバッグ画面が開く
```

---

## エラーハンドリング

### 認証エラーの処理
```php
if (!$token) {
    return Response::error('Authentication token not provided.', 401);
}

if (!$accessToken || !$accessToken->tokenable) {
    return Response::error('Invalid authentication token.', 401);
}
```

### 権限エラーの処理
```php
try {
    $results = $this->ledgerService->searchLedgersForApi($user, $parameters);
} catch (UnauthorizedException $e) {
    return Response::error('Insufficient permissions.', 403);
} catch (Exception $e) {
    Log::error('MCP search error', ['exception' => $e]);
    return Response::error('Internal server error.', 500);
}
```

---

## 今後の拡張可能性

### 新しいツールの追加
```php
// 将来的な拡張例
protected array $tools = [
    GetLedgerDefinesTool::class,
    SearchLedgersTool::class,
    CreateLedgerTool::class,
    UpdateLedgerTool::class,        // 台帳更新
    DeleteLedgerTool::class,        // 台帳削除
    GetWorkflowStatusTool::class,   // ワークフロー状況確認
    ExecuteWorkflowTool::class,     // ワークフロー実行
];
```

### 多言語対応
```php
// instructions の多言語化
protected string $instructions = match (app()->getLocale()) {
    'ja' => $this->getJapaneseInstructions(),
    'en' => $this->getEnglishInstructions(),
    default => $this->getEnglishInstructions(),
};
```

### リソース・プロンプト機能
```php
// 将来的な機能拡張
protected array $resources = [
    LedgerTemplatesResource::class,  // 台帳テンプレート提供
    HelpDocumentResource::class,     // ヘルプドキュメント提供
];

protected array $prompts = [
    CreateDailyReportPrompt::class,  // 日報作成支援プロンプト
    DataAnalysisPrompt::class,       // データ分析支援プロンプト
];
```

---

## 関連技術情報

### 利用ライブラリ・パッケージ
- **Laravel MCP**: Laravel公式のMCP実装ライブラリ
- **Laravel Sanctum**: API認証ライブラリ
- **Carbon**: 日付処理ライブラリ（日本語フォーマット対応）
- **Mroonga**: 日本語全文検索エンジン

### 関連設定ファイル
- `config/sanctum.php`: API認証設定
- `config/queue.php`: 非同期処理設定（将来的なバックグラウンド処理用）
- `.vscode/mcp.json`: 開発環境でのMCP設定

### 関連コマンド
```bash
# MCPサーバー起動
php artisan mcp:start ledgerleap:mcp

# MCPデバッグ・検査
php artisan mcp:inspector ledgerleap:mcp

# API トークン生成（管理画面またはTinker経由）
php artisan tinker
>>> $user = User::find(1);
>>> $token = $user->createToken('MCP Access');
>>> echo $token->plainTextToken;
```

---

## まとめ

LedgerLeapのMCPアーキテクチャは、以下の要素が連携して高品質なLLM対話体験を提供しています：

1. **`instructions`プロパティ**: LLMの動作を制御する中核的な指示システム
2. **最適化されたツール応答**: `__summary__`と`__display_fields__`による構造化ヒント
3. **堅牢な認証・権限管理**: Sanctumベースのセキュアなアクセス制御
4. **高性能な全文検索**: Mroongaによる日本語対応検索エンジン

この設計により、「昨日私が作成した日報を見せて」のような自然言語クエリが、適切なデータベース検索と視覚的に整理された応答に自動変換され、直感的で効率的なユーザー体験を実現しています。