<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Ledger\CreateColumn;
use App\Livewire\Ledger\ModifyColumn;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerColumnValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // 重複禁止カラムを持つ台帳定義を作成
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, 'Non-Unique', 'text'),
                new ColumnDefine(1, 'Unique Text', 'text', 2, [], false, true), // unique = true
            ],
        ]);
    }

    /** @test */
    public function create_column_fails_validation_if_unique_column_is_duplicated()
    {
        // 準備: 既存データを作成 (正規化して保存)
        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $this->ledgerDefine->normalizeByColumnDefine($content);

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $normalizedContent,
        ]);

        // 実行 & 確認
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content.1', 'EXISTING_VALUE') // 既存の値と同じ値をセット
            ->call('saveDirectly')
            ->assertHasErrors('content.1');
    }

    /** @test */
    public function create_column_passes_validation_if_unique_column_is_not_duplicated()
    {
        // 準備: 既存データを作成 (正規化して保存)
        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $this->ledgerDefine->normalizeByColumnDefine($content);

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $normalizedContent,
        ]);

        // 実行 & 確認
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $this->ledgerDefine->id])
            ->set('content.1', 'NEW_UNIQUE_VALUE') // 新しいユニークな値をセット
            ->call('saveDirectly')
            ->assertHasNoErrors(['content.1' => 'unique']);
    }

    /** @test */
    public function number_column_validation_works_correctly()
    {
        // 準備: number 型のカラムを持つ台帳定義を作成
        $numberLedgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(
                    0,                  // id
                    'Number Input',     // name
                    'number',           // typeIdentifier
                    1,                  // order
                    [],                 // options
                    true,               // required
                    false,              // unique
                    false,              // sortBy
                    '',                 // hint
                    [],                 // file
                    10,                 // min
                    20,                 // max
                    0.5,                // step
                    '℃'                 // unit
                ),
            ],
        ]);

        // --- 成功ケース ---
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('content.0', 15.5)
            ->call('saveDirectly')
            ->assertHasNoErrors();

        // --- 失敗ケース: min 未満 ---
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('content.0', 9.9)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'min']);

        // --- 失敗ケース: max 超過 ---
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('content.0', 20.1)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'max']);

        // --- 失敗ケース: step 不一致 ---
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('content.0', 15.6)
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'multiple_of']);

        // --- 失敗ケース: numeric でない ---
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $numberLedgerDefine->id])
            ->set('content.0', 'not a number')
            ->call('saveDirectly')
            ->assertHasErrors(['content.0' => 'numeric']);
    }
}
