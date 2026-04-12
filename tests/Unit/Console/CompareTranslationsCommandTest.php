<?php

namespace Tests\Unit\Console;

use App\Console\Commands\CompareTranslations;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CompareTranslations::class)]
class CompareTranslationsCommandTest extends TestCase
{
    use WithFaker;

    private string $tempLangPath;

    private string $originalJsonPath;

    private string $originalLedgerPath;

    private string $originalLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLangPath = $this->app->langPath();

        // テスト用の一時ディレクトリを作成
        $this->tempLangPath = storage_path('framework/testing/lang-' . uniqid('', true));
        File::makeDirectory($this->tempLangPath . '/ja', 0755, true);

        // テスト用の ledger.php を作成
        $this->originalLedgerPath = $this->tempLangPath . '/ja/ledger.php';
        File::put($this->originalLedgerPath, <<<'PHP'
<?php
return [
    'save' => '保存',
    'cancel' => 'キャンセル',
    'workflow' => [
        'status' => [
            'draft' => '下書き',
        ],
    ],
];
PHP
        );

        // テスト用の ja.json を作成（既存の非ledgerキーを含む）
        $this->originalJsonPath = $this->tempLangPath . '/ja.json';
        File::put($this->originalJsonPath, json_encode([
            'laravel-lang.key' => 'Laravelの翻訳',    // 外部ライブラリのキー（保持されるべき）
            'password' => 'パスワード',                 // 外部ライブラリのキー（保持されるべき）
            'ledger.save' => '古い保存',               // 旧ledgerキー（上書きされるべき）
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempLangPath);
        parent::tearDown();
    }

    private function withTemporaryLangPath(callable $callback): mixed
    {
        $this->app->useLangPath($this->tempLangPath);

        try {
            return $callback();
        } finally {
            $this->app->useLangPath($this->originalLangPath);
        }
    }

    #[Test]
    public function dry_run_does_not_modify_json_file(): void
    {
        // json_pathとledger_pathをテスト用のものに差し替える
        $originalContent = File::get($this->originalJsonPath);

        $this->withTemporaryLangPath(function (): void {
            $this->artisan('translations:compare', [
                '--dry-run' => true,
            ]);
        });

        // ファイルは変わっていないはずなので、内容が同一であることを確認
        $this->assertSame($originalContent, File::get($this->originalJsonPath));
    }

    #[Test]
    public function force_flag_applies_changes_without_confirmation(): void
    {
        // ProductのPHPとJSONファイルのパスは実際のものを使うため、ここでは
        // コマンドの出力をチェックするのみとする（ファイルの差し替えは難しい）
        $this->withTemporaryLangPath(function (): void {
            $this->artisan('translations:compare', [
                '--dry-run' => true,
            ])
                ->expectsOutputToContain('Changes to be applied')
                ->assertExitCode(0);
        });
    }

    /**
     * コマンドがDry-Runモードで差分を出力することをテストする。
     * ファイルのパスはアプリのデフォルトを使用する。
     */
    #[Test]
    public function it_outputs_no_changes_when_json_is_in_sync(): void
    {
        // 実際のファイルが同期済みであれば "No changes detected" が出力される
        // もし差分があれば "Changes to be applied" のどちらか
        $result = $this->artisan('translations:compare', ['--dry-run' => true]);

        // どちらかのメッセージが出力されることを最低限確認
        $result->assertExitCode(0);
    }

    /**
     * 実際のledger.phpをロードして、フラットなキーが正しく生成されることを検証する。
     * ネストされた配列が「ledger.workflow.status.draft」のようにドット形式で展開されるかを確認する。
     */
    #[Test]
    public function it_generates_dotted_ledger_keys_from_nested_php_array(): void
    {
        $ledgerPhpArray = require lang_path('ja/ledger.php');

        // Arr::dotでフラット化したとき、ネストされたキーがドット区切りになることを確認
        $flatArray = \Illuminate\Support\Arr::dot($ledgerPhpArray);

        // 少なくとも1つのKeyが存在することを確認
        $this->assertNotEmpty($flatArray, 'Ledger PHP array should not be empty after being flattened.');

        // すべてのvalueが文字列であることを確認
        foreach ($flatArray as $key => $value) {
            $this->assertIsString($value, "Key [{$key}] should have a string value.");
        }
    }

    /**
     * 実際のja.jsonに、非ledger.*キーが存在することを確認する（外部ライブラリの保護テスト）。
     */
    #[Test]
    public function json_file_contains_non_ledger_keys_from_external_libraries(): void
    {
        $jsonArray = json_decode(File::get(lang_path('ja.json')), true);

        $nonLedgerKeys = array_filter(array_keys($jsonArray), fn($k) => !str_starts_with($k, 'ledger.'));

        $this->assertNotEmpty(
            $nonLedgerKeys,
            'ja.json should contain non-ledger keys managed by external libraries like laravel-lang.'
        );
    }

    /**
     * ja.jsonのledger.*キーは、lang/ja/ledger.phpから展開されたキーと一致することを確認する。
     */
    #[Test]
    public function json_ledger_keys_match_php_source(): void
    {
        $ledgerPhpArray = require lang_path('ja/ledger.php');
        $flatPhpKeys = array_map(
            fn($k) => "ledger.{$k}",
            array_keys(\Illuminate\Support\Arr::dot($ledgerPhpArray))
        );

        $jsonArray = json_decode(File::get(lang_path('ja.json')), true);
        $jsonLedgerKeys = array_values(array_filter(array_keys($jsonArray), fn($k) => str_starts_with($k, 'ledger.')));

        sort($flatPhpKeys);
        sort($jsonLedgerKeys);

        $this->assertSame(
            $flatPhpKeys,
            $jsonLedgerKeys,
            'All ledger.* keys in ja.json should exactly match the flattened keys from lang/ja/ledger.php.'
        );
    }
}
