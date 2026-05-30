<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\ShowDiff;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * Livewire\Ledger\ShowDiff テスト
 *
 * 台帳 Diff 表示コンポーネントの mount・changeOffset を検証する。
 */
#[CoversClass(ShowDiff::class)]
class ShowDiffTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected Folder $folder;

    protected LedgerDefine $ledgerDefine;

    protected Ledger $ledger;

    protected LedgerDiff $diff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['column_define' => []]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'status' => WorkflowStatus::NONE,
            'content' => [],
        ]);

        // LedgerDiff を1件作成
        $this->diff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [],
            'column_define' => [],
            'modifier_id' => $this->user->id,
        ]);
    }

    // ================================================================
    // mount / render
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->assertStatus(200);
    }

    #[Test]
    public function mount_sets_ledger_id(): void
    {
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->assertSet('ledgerId', $this->ledger->id);
    }

    #[Test]
    public function mount_counts_ledger_diffs(): void
    {
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->assertSet('ledgerDiffCount', 1);
    }

    #[Test]
    public function mount_sets_offset_to_zero_by_default(): void
    {
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->assertSet('offset', 0);
    }

    #[Test]
    public function mount_loads_current_diff_record(): void
    {
        $component = Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id]);

        $this->assertNotNull($component->get('currentDiffRecord'));
        $this->assertEquals($this->diff->id, $component->get('currentDiffRecord')->id);
    }

    #[Test]
    public function mount_accepts_specific_diff_id(): void
    {
        $secondDiff = LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [],
            'column_define' => [],
            'modifier_id' => $this->user->id,
        ]);

        $component = Livewire::test(ShowDiff::class, [
            'ledgerId' => $this->ledger->id,
            'diffId' => $this->diff->id,
        ]);

        $this->assertEquals($this->diff->id, $component->get('currentDiffRecord')->id);
    }

    // ================================================================
    // changeOffset
    // ================================================================

    #[Test]
    public function change_offset_updates_offset(): void
    {
        // Diff を2件作成して offset 変更をテスト
        LedgerDiff::factory()->create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [],
            'column_define' => [],
            'modifier_id' => $this->user->id,
        ]);

        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->call('changeOffset', 1)
            ->assertSet('offset', 1);
    }

    #[Test]
    public function change_offset_clamps_below_zero(): void
    {
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->call('changeOffset', -5)
            ->assertSet('offset', 0);
    }

    #[Test]
    public function change_offset_clamps_above_max(): void
    {
        // diff が1件なのでmax=0、offset=99 → 0にクランプ
        Livewire::test(ShowDiff::class, ['ledgerId' => $this->ledger->id])
            ->call('changeOffset', 99)
            ->assertSet('offset', 0);
    }
}
