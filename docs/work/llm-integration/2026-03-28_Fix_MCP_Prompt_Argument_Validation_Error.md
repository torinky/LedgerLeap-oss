# 2026-03-28: MCP Prompt Argument Validation Error Fix

**作成日:** 2026年03月28日  
**ドキュメント種別:** 修正・検証記録  
**関連Issue:** [#109](https://github.com/torinky/LedgerLeap/issues/109) (Remote MCP), [#106](https://github.com/torinky/LedgerLeap/issues/106) (Clean-room harness)  
**修正対象:** `app/Mcp/Prompts/BootstrapClientSkillsPrompt.php`

## 1. 概要

MCP クライアント（IDE 拡張機能や `mcp-remote` プロキシなど）がプロンプトの一覧（discovery）を取得する際、引数を渡さずに `bootstrap-client-skills` プロンプトの内容を取得しようとしてバリデーションエラーが発生する問題を修正した。

### 事象
- エラーメッセージ: `Invalid params: client_type は必須です。例: copilot role_profile は必須です。例: operator`
- 発生タイミング: MCP クライアント（特に remote MCP アクセス時）のプロンプト初期スキャン

## 2. 原因分析

- `BootstrapClientSkillsPrompt.php` の `arguments()` メソッドで、`client_type` と `role_profile` が `required: true` として定義されていた。
- `handle()` メソッド内のバリデーションルールも `required` であったため、クライアントが discovery 目的で引数なしの呼び出しを行った場合に、サーバー側で `400 Bad Request` (Invalid params) を返していた。
- 多くの MCP クライアントは、プロンプトの利用可能性や詳細を確認するために、パラメータなしでの `prompts/get` を試行するため、この挙動は相互運用性を損なう。

## 3. 解決策

1. **引数の省略を許容**: `arguments()` 定義で `required: false` に変更。
2. **デフォルト値の導入**: `handle()` 内で引数が未指定の場合に、最も一般的と思われるデフォルト値を使用するように変更。
    - `client_type`: `copilot`
    - `role_profile`: `operator`
    - `model_profile`: `general-local`
    - `language`: `ja`
3. **バリデーションの柔軟化**: `required` ルールを `sometimes` に変更し、値が渡された場合のみ形式チェックを行うようにした。

## 4. 修正内容

### 4.1 プロンプトクラスの変更 (`app/Mcp/Prompts/BootstrapClientSkillsPrompt.php`)

```php
// arguments() メソッド
public function arguments(): array
{
    return [
        PromptArgument::make('client_type', 'クライアントの種類 (例: copilot, claude-code, gemini-cli)')
            ->required(false), // required を false に変更
        PromptArgument::make('role_profile', '役割プロファイル (例: operator, field-leader, administrator)')
            ->required(false), // required を false に変更
        // ...
    ];
}

// handle() メソッド
public function handle(array $arguments): PromptResponse
{
    $validator = Validator::make($arguments, [
        'client_type' => 'sometimes|string|in:claude-code,copilot,gemini-cli,openai-agents',
        'role_profile' => 'sometimes|string|in:operator,administrator,field-leader',
        // ...
    ]);

    // デフォルト値の設定
    $clientType = $arguments['client_type'] ?? 'copilot';
    $roleProfile = $arguments['role_profile'] ?? 'operator';
    // ...
}
```

## 5. 検証結果

### 5.1 自動テスト
`tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php` を実行し、PASS することを確認。

```bash
# sail test tests/Feature/Mcp/BootstrapClientSkillsPromptTest.php
# Tests: 4 passed (32 assertions)
```

1. `it_returns_default_content_when_no_arguments_provided`: 引数なしでの呼び出し時にデフォルト値で応答が返ること。（新規追加）
2. `it_returns_prompt_content_for_all_supported_client_types`: 指定された client_type で正しく動作すること。
3. `it_validates_supported_prompt_arguments`: 不正な値が渡された場合には適切にエラーを返すこと。

### 5.2 手動検証 (Tinker / API)
`prompts/get` API を通じて、空の `arguments` を渡してもエラーにならず、有効なプロンプトテキストが返されることを確認した。

## 6. 今後の保守への影響

- **相互運用性の向上**: 新しい MCP ツールやプロンプトを追加する際は、discovery 時にエラーにならないよう「デフォルト値を持つオプション引数」として設計することを推奨。
- **後方互換性**: 既存の `client_type` 指定呼び出しは引き続き正常に動作し、かつ引数を処理できないクライアントでもフォールバックとして動作する。
- **デバッグ**: `remote-mcp` 環境での接続トラブル時に、バリデーションエラーでブロックされることがなくなる。
