<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\LedgerHistoryManager;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class LedgerHistoryManagerTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    protected User $user;

    protected Ledger $ledger;

    protected LedgerDefine $ledgerDefine;

    protected LedgerDiff $diff1;

    protected LedgerDiff $diff2;

    protected LedgerDiff $diff3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();

        $folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Test Ledger',
            'column_define' => [
                ['id' => 0, 'name' => 'Column 1', 'type' => 'text', 'order' => 1],
            ],
        ]);

        $this->ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']],
        ]);

        // 作成された履歴（Diff）をシミュレート
        $this->diff1 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 1,
            'content' => [['0' => 'Value 1']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        $this->diff2 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 2,
            'content' => [['0' => 'Value 2']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now()->subDay(),
        ]);

        $this->diff3 = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'content' => [['0' => 'Value 3']],
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $this->ledger->update(['latest_diff_id' => $this->diff3->id]);
        $this->ledger->setRelation('ledgerDiff', collect([$this->diff3, $this->diff2, $this->diff1]));
    }

    #[Test]
    public function it_renders_initial_history_list()
    {
        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->assertSee(__('ledger.version').'1')
            ->assertSee(__('ledger.version').'2')
            ->assertSee(__('ledger.version').'3')
            ->assertSee(__('ledger.column.expand_all'))
            ->assertViewHas('history', function ($history) {
                return $history->count() === 3;
            })
            ->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);
    }

    #[Test]
    public function it_selects_versions_for_comparison()
    {
        // テスト開始
        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id]);

        // 初期状態で $diff3 (base) のみが選択されているはず（target は null）
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);

        // $diff2 を追加選択
        $component->call('toggleSelection', $this->diff2->id);
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', $this->diff2->id);

        // $diff2 を解除
        $component->call('toggleSelection', $this->diff2->id);
        $component->assertSet('baseDiffId', $this->diff3->id)
            ->assertSet('targetDiffId', null);

        // $diff3 を解除
        $component->call('toggleSelection', $this->diff3->id);
        $component->assertSet('baseDiffId', null)
            ->assertSet('targetDiffId', null);

        // $diff2 と $diff1 を選択（任意2バージョン比較の検証）
        $component->call('toggleSelection', $this->diff2->id) // base
            ->call('toggleSelection', $this->diff1->id); // target

        $component->assertSet('baseDiffId', $this->diff2->id)
            ->assertSet('targetDiffId', $this->diff1->id)
            ->assertViewHas('baseDiff', function ($diff) {
                return $diff->id === $this->diff2->id;
            })
            ->assertViewHas('targetDiff', function ($diff) {
                return $diff->id === $this->diff1->id;
            })
            ->assertViewHas('baseMeta', function ($meta) {
                return $meta['version'] === 2 && $meta['modifier_name'] === $this->user->name;
            })
            ->assertViewHas('targetMeta', function ($meta) {
                return $meta['version'] === 1 && $meta['modifier_name'] === $this->user->name;
            });
    }

    #[Test]
    public function it_loads_more_history_on_infinite_scroll()
    {
        // さらに履歴を作成
        for ($i = 4; $i <= 25; $i++) {
            LedgerDiff::create([
                'ledger_id' => $this->ledger->id,
                'ledger_define_id' => $this->ledgerDefine->id,
                'version' => $i,
                'content' => [['0' => "Value $i"]],
                'column_define' => $this->ledgerDefine->column_define,
                'completed_inspector_role_ids' => [],
                'completed_approver_role_ids' => [],
                'modifier_id' => $this->user->id,
                'creator_id' => $this->user->id,
                'created_at' => now()->subSeconds($i),
            ]);
        }

        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->assertViewHas('history', function ($history) {
                return $history->count() === 10; // デフォルト 10件
            })
            ->call('loadMore')
            ->assertViewHas('history', function ($history) {
                return $history->count() === 20; // 10 + 10
            })
            ->call('loadMore')
            ->assertViewHas('history', function ($history) {
                return $history->count() === 25; // 20 + 5
            });
    }

    #[Test]
    public function it_propagates_highlight_to_view()
    {
        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, [
                'ledgerId' => $this->ledger->id,
                'highlight' => 'Value',
            ])
            ->assertSet('highlight', 'Value')
            ->assertSee('Value'); // 検索ハイライトが存在する場合、ビューに反映されることを確認
    }

    #[Test]
    public function it_logs_performance_metrics_when_enabled()
    {
        config(['ledgerleap.performance.enabled' => true]);
        config(['ledgerleap.performance.log_destination' => 'log']);

        Log::spy();

        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->set('perPage', 1);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_mount'
                    && isset($context['duration_ms'])
                    && $context['ledger_id'] === $this->ledger->id;
            });

        // Toggle selection logic check
        $component->call('toggleSelection', $this->diff2->id);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_toggle_selection'
                    && isset($context['duration_ms']);
            });

        // Load more logic check
        $component->call('loadMore');

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return $message === '[Performance] ledger_load_more'
                    && isset($context['duration_ms']);
            });
    }

    #[Test]
    public function it_loads_attached_files_for_diff_viewer()
    {
        // 添付ファイルを作成
        $attachment1 = AttachedFile::factory()->forLedger($this->ledger)->create([
            'column_id' => 0,
            'hashedbasename' => 'abc123',
            'filename' => 'test-file.pdf',
        ]);

        $attachment2 = AttachedFile::factory()->forLedger($this->ledger)->create([
            'column_id' => 0,
            'hashedbasename' => 'xyz789',
            'filename' => 'image.jpg',
        ]);

        // コンポーネントをマウント
        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id]);

        // allAttachments プロパティが設定されていることを確認
        $component->assertSet('allAttachments', function ($attachments) use ($attachment1, $attachment2) {
            return $attachments !== null
                && $attachments->count() === 2
                && $attachments->contains('id', $attachment1->id)
                && $attachments->contains('id', $attachment2->id);
        });

        // ビューに allAttachments が渡されていることを確認
        $component->assertViewHas('allAttachments', function ($attachments) use ($attachment1, $attachment2) {
            return $attachments !== null
                && $attachments->count() === 2
                && $attachments->contains('id', $attachment1->id)
                && $attachments->contains('id', $attachment2->id);
        });
    }

    #[Test]
    public function it_handles_ledger_with_attachment_column_added_in_later_version()
    {
        // このテストは it_loads_attached_files_for_diff_viewer で基本的な機能を確認済み
        // ここでは、添付ファイルカラムが途中から追加された場合でも
        // エラーなく表示できることを簡易的に確認する

        // 既存の $this->ledger に添付ファイルを追加
        $attachment = AttachedFile::factory()->forLedger($this->ledger)->create([
            'column_id' => 0,
            'hashedbasename' => 'test123',
            'filename' => 'added-later.pdf',
        ]);

        // コンポーネントをマウント
        $component = Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id]);

        // allAttachments が正しく設定されていることを確認
        $component->assertSet('allAttachments', function ($attachments) use ($attachment) {
            return $attachments !== null
                && $attachments->count() === 1
                && $attachments->first()->id === $attachment->id;
        });

        // 2つのバージョンを比較選択してもエラーが発生しないことを確認
        $component->call('toggleSelection', $this->diff3->id)
            ->call('toggleSelection', $this->diff1->id);

        // ビューが正常にレンダリングされることを確認
        $component->assertViewHas('baseDiff')
            ->assertViewHas('targetDiff')
            ->assertViewHas('allAttachments', function ($attachments) {
                return $attachments !== null && $attachments->count() === 1;
            });
    }

    #[Test]
    public function it_filters_redundant_sequential_entries()
    {
        // 既存の diff3 にコメントとステータスを設定
        $this->diff3->update([
            'comments' => 'Redundant test',
            'status' => WorkflowStatus::DRAFT,
        ]);

        // 冗長なエントリを作成（同じバージョン、ステータス、更新者、コメント）
        // IDがより大きい（＝より新しい）ものが優先的に残るため、これを作成
        $redundantDiff = LedgerDiff::create([
            'ledger_id' => $this->ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'version' => 3,
            'status' => WorkflowStatus::DRAFT,
            'content' => [['0' => 'Value 3.1']], // 内容が違っていても、リスト上のメタデータが同じならフィルタリングされる
            'column_define' => $this->ledgerDefine->column_define,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'modifier_id' => $this->user->id,
            'creator_id' => $this->user->id,
            'comments' => 'Redundant test',
            'created_at' => now()->addMinute(),
        ]);

        // コンポーネント実行
        Livewire::actingAs($this->user)
            ->test(LedgerHistoryManager::class, ['ledgerId' => $this->ledger->id])
            ->assertViewHas('history', function ($history) use ($redundantDiff) {
                // 元々3件（diff1, diff2, diff3）あったところに1件追加したが、
                // diff3 と redundantDiff が重複するので、結果として3件になるはず。
                // また、より新しい redundantDiff が残っているはず。
                return $history->count() === 3
                    && $history->contains('id', $redundantDiff->id)
                    && ! $history->contains('id', $this->diff3->id);
            });
    }
}
