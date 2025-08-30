<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Ledger\CreateColumn;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase; // TestCase を継承

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

    /** @test */ // PHPUnit のアノテーション
    public function create_column_fails_validation_if_unique_column_is_duplicated()
    {
        // 準備: このテスト専用の「unique」カラムを持つ台帳定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                // ▼▼▼ 名前付き引数から連想配列での初期化に修正 ▼▼▼
                new ColumnDefine([
                    'id' => 0, 'name' => 'Non-Unique', 'type' => 'text', 'order' => 1,
                    'required' => false, 'unique' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                ]),
                new ColumnDefine([
                    'id' => 1, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 2,
                    'required' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                ]),
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
            ->assertHasErrors(['content.1' => '指定のUnique Textは既に使用されています。']);
    }

    /** @test */ // PHPUnit のアノテーション
    public function create_column_passes_validation_if_unique_column_is_not_duplicated()
    {
        // 準備: このテスト専用の「unique」カラムを持つ台帳定義を作成
        $ledgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                // ▼▼▼ 名前付き引数から連想配列での初期化に修正 ▼▼▼
                new ColumnDefine([
                    'id' => 0, 'name' => 'Non-Unique', 'type' => 'text', 'order' => 1,
                    'required' => false, 'unique' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                ]),
                new ColumnDefine([
                    'id' => 1, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 2,
                    'required' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                ]),
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

    /** @test */ // PHPUnit のアノテーション
    public function number_column_validation_works_correctly()
    {
        // 準備: このテスト専用の「number」型カラムを持つ台帳定義を作成
        $numberLedgerDefine = LedgerDefine::factory()->create([
            'column_define' => [
                // ▼▼▼ 名前付き引数から連想配列での初期化に修正 ▼▼▼
                new ColumnDefine([
                    'id' => 0,
                    'name' => 'Number Input',
                    'type' => 'number', // 'typeIdentifier' から 'type' に変更
                    'order' => 1,
                    'options' => [
                        'min' => 10,
                        'max' => 20,
                        'step' => 0.5,
                        'unit' => '℃'
                    ],
                    'required' => true,
                    // 不足していたキーを追加
                    'unique' => false,
                    'sortBy' => false,
                    'hint' => '',
                    'file' => [],
                ]),
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

    /** @test */ // PHPUnit のアノテーション
    public function create_column_passes_validation_across_tenants()
    {
        // テナント1を作成
        $tenant1 = \App\Models\Tenant::factory()->create();
        // テナント2を作成
        $tenant2 = \App\Models\Tenant::factory()->create();

        // テナント1で台帳定義を作成
        $ledgerDefine1 = $tenant1->run(function () {
            return LedgerDefine::factory()->create([
                'column_define' => [
                    new ColumnDefine([
                        'id' => 0, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 1,
                        'required' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                    ]),
                ],
            ]);
        });

        // テナント2で台帳定義を作成 (同じ内容だが別テナント)
        $ledgerDefine2 = $tenant2->run(function () {
            return LedgerDefine::factory()->create([
                'column_define' => [
                    new ColumnDefine([
                        'id' => 0, 'name' => 'Unique Text', 'type' => 'text', 'unique' => true, 'order' => 1,
                        'required' => false, 'sortBy' => false, 'hint' => '', 'file' => [], 'options' => [],
                    ]),
                ],
            ]);
        });

        // テナント1で既存データを作成
        $tenant1->run(function () use ($ledgerDefine1) {
            $content = [0 => 'SHARED_UNIQUE_VALUE'];
            $normalizedContent = $ledgerDefine1->normalizeByColumnDefine($content);
            Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine1->id,
                'content' => $normalizedContent,
            ]);
        });

        // テナント2に切り替えて、テナント1と同じユニーク値で台帳を作成しようとする
        $tenant2->run(function () use ($ledgerDefine2) {
            Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine2->id])
                ->set('content.0', 'SHARED_UNIQUE_VALUE') // テナント1と同じ値をセット
                ->call('saveDirectly')
                ->assertHasNoErrors('content.0'); // エラーが発生しないことを確認
        });
    }
}