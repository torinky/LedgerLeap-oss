<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Ledger\CreateColumn;
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


    protected function setUp(): void
    {
        parent::setUp();
        // ユーザーを作成し、すべてのテストで認証済み状態にする
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    /** @test */
    public function create_column_fails_validation_if_unique_column_is_duplicated()
    {
        // 準備: このテスト専用の「unique」カラムを持つ台帳定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, 'Non-Unique', 'text'),
                new ColumnDefine(id: 1, name: 'Unique Text', typeIdentifier: 'text', unique: true),
            ],
        ]);

        // 準備: 既存データを作成
        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $ledgerDefine->normalizeByColumnDefine($content);
        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => $normalizedContent,
        ]);

        // 実行 & 確認
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
            ->set('content.1', 'EXISTING_VALUE') // 既存の値と同じ値をセット
            ->call('saveDirectly')
            ->assertHasErrors(['content.1' => 'unique']);
    }

    /** @test */
    public function create_column_passes_validation_if_unique_column_is_not_duplicated()
    {
        // 準備: このテスト専用の「unique」カラムを持つ台帳定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                new ColumnDefine(0, 'Non-Unique', 'text'),
                new ColumnDefine(id: 1, name: 'Unique Text', typeIdentifier: 'text', unique: true),
            ],
        ]);

        // 準備: 既存データを作成
        $content = [
            0 => 'some value',
            1 => 'EXISTING_VALUE',
        ];
        $normalizedContent = $ledgerDefine->normalizeByColumnDefine($content);
        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => $normalizedContent,
        ]);

        // 実行 & 確認
        Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
            ->set('content.1', 'NEW_UNIQUE_VALUE') // 新しいユニークな値をセット
            ->call('saveDirectly')
            ->assertHasNoErrors('content.1');
    }

    /** @test */
    public function number_column_validation_works_correctly()
    {
        // 準備: このテスト専用の「number」型カラムを持つ台帳定義を作成
        $numberLedgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                // ★★★ ColumnDefineのインスタンス化を修正 ★★★
                // min, max, step, unit はコンストラクタの `options` 配列に含めます。
                new ColumnDefine(
                    id: 0,
                    name: 'Number Input',
                    typeIdentifier: 'number',
                    order: 1,
                    options: [
                        'min' => 10,
                        'max' => 20,
                        'step' => 0.5,
                        'unit' => '℃'
                    ],
                    required: true
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