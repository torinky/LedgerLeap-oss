<?php

namespace Tests\Unit\Rules;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Rules\UniqueColumnValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueColumnValueTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // 台帳定義を作成（デフォルトのファクトリを使用）
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => Folder::factory(),
        ]);

        // テスト用にカラム定義を追加（ColumnDefineオブジェクトとして）
        $this->ledgerDefine->column_define = collect([
            new ColumnDefine(
                0,
                'タイトル',
                'text',
                1,
                [],
                true,  // required
                true,  // unique
                null,
                'タイトルのヒント',
                [],
                1
            ),
            new ColumnDefine(
                1,
                '数量',
                'number',
                2,
                [],
                false, // required
                true,  // unique
                null,
                '数量のヒント',
                [],
                1
            ),
        ]);
        $this->ledgerDefine->save();
    }

    public function test_it_passes_when_value_is_unique(): void
    {
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['既存タイトル', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', '新しいタイトル', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_fails_when_value_is_duplicate(): void
    {
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['重複タイトル', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', '重複タイトル', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_allows_same_value_when_editing(): void
    {
        User::unguard();
        $existingLedger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['既存タイトル', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, $existingLedger->id);

        $calledFail = false;
        $rule->validate('content.0', '既存タイトル', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_passes_for_null_or_empty_value(): void
    {
        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', null, function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);

        $calledFail = false;
        $rule->validate('content.0', '', function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);
    }

    public function test_it_checks_uniqueness_for_text_with_numbers(): void
    {
        // 数値を含むテキストの重複チェック
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['タイトル999', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', 'タイトル999', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_treats_zero_as_valid_value(): void
    {
        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        // 文字列の '0' は有効な値として扱われる
        $calledFail = false;
        $rule->validate('content.0', '0', function ($message) use (&$calledFail) {
            $calledFail = true;
        });
        $this->assertFalse($calledFail);
    }

    public function test_it_handles_special_characters(): void
    {
        // 特殊文字を含むテキストの重複チェック
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['タイトル@#$%', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', 'タイトル@#$%', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_handles_multi_byte_characters(): void
    {
        // マルチバイト文字（日本語）の重複チェック
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['これは日本語のタイトルです', 100],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueColumnValue($this->ledgerDefine->id, 0, null);

        $calledFail = false;
        $rule->validate('content.0', 'これは日本語のタイトルです', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertTrue($calledFail);
    }
}
