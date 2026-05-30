# MCP ツールのテストパターン

**最終更新:** 2026-02-28
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## 統合テスト vs 詳細テストの責任分担

MCPツールでは認証機能が共通化されているため、責任分担を明確にしないと重複テストが大量発生する。

```php
// ✅ 統合テスト (McpToolsAuthenticationTest.php)
// 複数ツールの認証一貫性を検証
/**
 * 責任範囲:
 * - 全MCPツールの認証動作の一貫性検証
 * - AuthenticatedMcpTraitの統合動作確認
 * - トークン検証・権限チェックの基本動作
 */
public function test_all_tools_reject_invalid_tokens()
{
    $tools = [
        new CreateLedgerTool(),
        new GetLedgerDefinesTool(),
        new SearchLedgersTool(),
    ];
    foreach ($tools as $tool) {
        // 各ツールで統一された認証動作を確認
    }
}

// ✅ 詳細テスト (CreateLedgerToolTest.php)
// 認証後のビジネスロジックに集中
/**
 * 責任範囲:
 * - 台帳作成のビジネスロジック
 * - リクエストパラメータのバリデーション
 * - サービス層との連携
 *
 * 注意: 認証関連のテストは McpToolsAuthenticationTest.php で統合的にテスト
 */
public function test_creates_ledger_with_valid_data()
{
    // 認証は前提として、台帳作成ロジックのみテスト
}
```

---

## MCPツール用モック設定パターン

`User` モデルのイベントリスナーが外部サービス（`WritableFolderRepository`）を呼び出すため、モックが複雑化する。

```php
protected function setUp(): void
{
    parent::setUp();

    $this->folderRepository = Mockery::mock(WritableFolderRepository::class);

    // デフォルトモック（Userモデルのイベントリスナー用）
    $this->folderRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
    $this->folderRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);

    $this->app->instance(WritableFolderRepository::class, $this->folderRepository);
}

public function test_specific_behavior()
{
    // 特定の動作のみオーバーライド
    $this->folderRepository->shouldReceive('getAccessibleFolderIds')
        ->with(Mockery::type(User::class), FolderPermissionType::WRITE)
        ->andReturn([$folder->id]);
}
```

---

## Resource クラスのテストパターン

MCPツールの出力は Resource クラスで加工されるため、モデル属性と異なる形式になる。

```php
// ❌ 間違いたアサーション（モデル属性で検証）
$this->assertEquals('Test Title', $responseData['title']);

// ✅ 正しいアサーション（Resource 出力で検証）
// LedgerDefineResource では title → name に変換される
$this->assertEquals('Test Title', $responseData['name']);
```

**一般的な Resource 出力テスト:**

```php
public function test_resource_output_structure()
{
    $responseData = json_decode($response->content(), true);
    $this->assertIsArray($responseData);
    $this->assertArrayHasKey('id', $responseData);
    $this->assertArrayHasKey('name', $responseData);  // title が name に変換
}
```

---

## enum 値のモック指定パターン

`FolderPermissionType` は小文字の value（`'read'`, `'write'`）だが、定数名は大文字（`READ`, `WRITE`）。

```php
// ✅ 正しい enum 参照
FolderPermissionType::READ   // value = 'read'
FolderPermissionType::WRITE  // value = 'write'
FolderPermissionType::ADMIN  // value = 'admin'
```

---

## ファクトリ属性の正規化

データベースカラム名とファクトリ属性名の不一致に注意：

```php
// ❌ 古いファクトリ定義
Folder::factory()->create(['name' => 'Test Folder']);
// → 'name' カラムが存在しない場合エラー

// ✅ 正しいファクトリ定義
Folder::factory()->create(['title' => 'Test Folder']);
```

テスト失敗時は以下をチェック：
1. マイグレーションファイルでの実際のカラム名
2. Eloquent モデルの `$fillable` 設定
3. ファクトリでの属性名

---

## テスト構造設計パターン

```
tests/Unit/Mcp/
├── Tools/
│   ├── McpToolsAuthenticationTest.php    # 【統合】認証一貫性（6テスト）
│   ├── CreateLedgerToolTest.php         # 【詳細】台帳作成機能（5テスト）
│   ├── GetLedgerDefinesToolTest.php     # 【詳細】データフィルタリング（5テスト）
│   └── SearchLedgersToolTest.php        # 【詳細】検索機能（5テスト）
└── Traits/
    └── AuthenticatedMcpToolTest.php     # 【内部】トレイト単体テスト（15テスト）
```

