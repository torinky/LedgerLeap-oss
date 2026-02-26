# GitHub Copilot Instructions - LedgerLeap

## 1. Critical Constraints (最優先制約)

**これらを無視すると修復不可能なバグやテスト失敗を招きます。**

- **Language** 会話や思考は日本語で進めてください。
- **Mroonga 全文検索:** 複合インデックスは機能しません。検索時は必ず単独インデックスに対して `MATCH() AGAINST()` を行い、複数カラムは `OR` で結合してください。
- **テナント初期化:** 全てのFeatureテストの `setUp()` で `tenancy()->initialize($tenant)` が**必須**です。これを忘れるとリレーションが `null` を返します。
- **AsColumnArrayJson Access:** シリアライゼーションの制約により `data_get()` は動作しません。必ず `$ledger->content[0]` のように直接配列アクセスを行ってください。
    - **二重エンコード厳禁:** `files` や `chk` 型等の配列として保存されるカラムに対し、保存前に手動で `json_encode` を行わないでください。キャストが自動的にシリアライズを行うため、手動で変換するとDB上で破損データ（文字列化されたJSON）となり、UIが表示不能になります。
- **Livewire State:** パブリックプロパティは「シンプルな連想配列」のみを使用してください。オブジェクトを直接持たせるとシリアライズエラーが発生します。
- **Livewire Parent Access:** ソートやフィルタなどの高頻度な操作には `Livewire.dispatch()` を避け、`$parent.method()` または `$wire.$parent.method()` を使用してください。これにより `wire:loading` の追跡が安定し、レスポンスが向上します。
- **Model Event Reliability (Sail):** Sail環境のテストでは `touch()` が `updated` イベントを確実に発火させない場合があります。イベント駆動のテストでは `$model->update(['column' => 'value'])` を使用してください。
- **Permission Cache Invalidation:** パーミッションに影響するモデル (`Role`, `Organization`, `User`) の変更時は、必ず `UserService` を通じて関連キャッシュをクリアするか、広範囲な変更では `flushAllUserPermissionsCache()` を実行してください。
- **Database Migrations:** 全文検索が絡むテストでは `RefreshDatabase` ではなく `DatabaseMigrations` トレイトを使用してください。

## 2. MCP & Data Access (行動原理)

**推論ではなく事実に基づいた回答を徹底してください。**

- **Source of Truth:** 稼働中のデータ（台帳、統計、設定、ルート）を確認する際は、自律的に `list_tools` を確認し、適切なMCPツール（`ledgerleap-api`, `laravel-boost` 等）を**最優先で**実行すること。
- **Serena First:** ファイルの存在確認、検索、読み込みには `serena` MCPツール群を**常に、かつ唯一の手段として**使用すること。
- **サーチ・ファースト:** コードを読む前に、まず `SearchLedgersTool` で実際のレコード構造やカラム定義を確認すること。
- **コンテキストの優先順位:** ツールから得られた統計や検索結果は、ファイル内の静的な記述よりも優先してユーザーに提示すること。

## 3. Tech Stack & Key Patterns

- **Backend:** PHP 8.4 / Laravel 12.0 / MySQL (Mroonga)
- **Frontend:** Livewire / Alpine.js / TailwindCSS (daisyUI, maryUI)
- **Pattern:**
    - Logic should be in `App\Services`.
    - Interactive UI should use Livewire with "Single Source of Truth" (state in one array).
    - ACL is managed via `Spatie\Permission` and custom Folder-based permissions.

## 4. Documentation Map (詳細仕様への誘導)

**具体的な仕様やロジックについては以下のドキュメントを読み込むこと。**

- **/docs/README.md:** プロジェクト全体の目次と概要。
- **/docs/development/coding_standards.md:** 命名規則、コメント、Git規約。
- **/docs/development/Testing-Best-Practices.md:** テナント初期化、Mroongaテスト、Livewireテスト。
- **/docs/development/MCP_Architecture_and_Flow.md:** MCPの動作詳細とレスポンス設計。
- **/docs/function/Attachment.md:** VLM/OCR/Tika を含む複雑なファイル処理フロー。
- **/docs/database/schema.md:** 核心テーブル構造。

## 4a. Skills (定型ワークフロー)

**特定の操作パターンについては、以下のスキル定義を必ず参照・遵守すること。**

- **/.github/skills/github-issue-workflow/SKILL.md:** GitHubイシューの調査・更新・カバレッジ評価・進捗反映の標準フロー。イシュー操作を行う際は**必ずこのファイルを先に読むこと**。

## 5. Development Workflow

1. **Pint:** コミット前に必ず `./vendor/bin/sail pint` 実行。
2. **Error Check:** 実装・変更の完了後は必ず `laravel-boost` (`last-error`, `browser-logs`) を使用してエラーの有無を**自律的に**確認すること。
3. **Test:** 変更後は `./vendor/bin/sail test` でリグレッションを確認。
4. **Commit:** `feat(scope): 日本語説明` の形式を厳守。

---

**この指示書はAIエージェント向けです。詳細な進捗やフェーズ履歴は `/docs/work/` を参照してください。**
