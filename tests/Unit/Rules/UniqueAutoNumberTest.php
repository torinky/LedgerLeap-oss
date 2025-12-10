<?php

namespace Tests\Unit\Rules;

use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Rules\UniqueAutoNumber;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UniqueAutoNumberTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    protected LedgerDefine $ledgerDefine;

    protected ColumnDefine $columnDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // Translatorをモックして、translate()が常に引数を返すようにする
        $translatorMock = Mockery::mock(Translator::class);
        $translatorMock->shouldReceive('get')->andReturnUsing(function ($key) {
            return $key; // 渡されたキーをそのまま返す
        });
        $this->app->instance('translator', $translatorMock);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => Folder::factory(),
        ]);
        $this->columnDefine = new ColumnDefine(
            0,
            '資料番号',
            'auto_number',
            1,
            [
                'prefix' => 'DOC-',
                'digits' => 3,
                'revision' => '-A',
            ],
            true,
            true, // unique = true
            null,
            'ヒント',
            [],
            3,
            null
        );
    }

    public function test_it_passes_when_no_duplicate_exists(): void
    {
        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine);

        $calledFail = false;
        $rule->validate('content.0', 'DOC-001-B', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_fails_when_duplicate_exists_for_new_record(): void
    {
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-A',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine);

        $calledFail = false;
        $rule->validate('content.0', 'DOC-001-B', function ($message) use (&$calledFail) {
            $calledFail = true;
            $this->assertEquals('validation.unique', $message);
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_passes_when_duplicate_is_itself_during_edit(): void
    {
        User::unguard();
        $existingLedger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-A',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine, $existingLedger->id);

        $calledFail = false;
        $rule->validate('content.0', 'DOC-001-B', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_fails_when_duplicate_is_another_record_during_edit(): void
    {
        User::unguard();
        Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'DOC-001-A',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        $editingLedger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [
                0 => 'DOC-002-X',
            ],
            'creator_id' => User::factory()->create()->id,
            'modifier_id' => User::factory()->create()->id,
        ]);
        User::reguard();

        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine, $editingLedger->id);

        $calledFail = false;
        $rule->validate('content.0', 'DOC-001-B', function ($message) use (&$calledFail) {
            $calledFail = true;
            $this->assertEquals('validation.unique', $message);
        });

        $this->assertTrue($calledFail);
    }

    public function test_it_passes_when_input_does_not_match_auto_number_pattern(): void
    {
        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine);

        $calledFail = false;
        // 自動採番のパターンに一致しない値
        $rule->validate('content.0', 'NOT-A-NUMBER', function ($message) use (&$calledFail) {
            $calledFail = true;
        });

        $this->assertFalse($calledFail);
    }

    public function test_it_passes_for_null_or_empty_values(): void
    {
        $rule = new UniqueAutoNumber($this->ledgerDefine->id, $this->columnDefine);

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
}
