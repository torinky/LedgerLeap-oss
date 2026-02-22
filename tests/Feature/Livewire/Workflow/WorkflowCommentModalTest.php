<?php

namespace Tests\Feature\Livewire\Workflow;

use App\Livewire\Workflow\WorkflowCommentModal;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(WorkflowCommentModal::class)]
class WorkflowCommentModalTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Ledger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $folder = Folder::factory()->create();
        $define = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $this->ledger = Ledger::factory()->create(['ledger_define_id' => $define->id]);
    }

    // ================================================================
    // 初期表示
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->assertStatus(200)
            ->assertSet('showCommentModal', false);
    }

    // ================================================================
    // open() — open-workflow-comment-modal イベント
    // ================================================================

    #[Test]
    public function open_event_sets_modal_state(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.return_to_draft'),
                actionLabel: __('ledger.workflow.return_to_draft'),
                actionClass: 'btn-error',
                actionType: 'return_to_draft',
                ledgerId: $this->ledger->id,
                initialComment: 'test comment',
                text: __('ledger.workflow.return_to_draft_confirm') ?? ''
            )
            ->assertSet('showCommentModal', true)
            ->assertSet('actionType', 'return_to_draft')
            ->assertSet('targetLedgerId', $this->ledger->id)
            ->assertSet('comment', 'test comment');
    }

    #[Test]
    public function open_event_with_null_initial_comment_defaults_to_empty_string(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.approve'),
                actionLabel: __('ledger.workflow.approve'),
                actionClass: 'btn-primary',
                actionType: 'approve',
                ledgerId: $this->ledger->id,
                initialComment: null,
                text: ''  // null ではなく空文字（$text は string 型）
            )
            ->assertSet('showCommentModal', true)
            ->assertSet('comment', '');
    }

    // ================================================================
    // executeAction()
    // ================================================================

    #[Test]
    public function execute_action_dispatches_event_and_closes_modal(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.approve'),
                actionLabel: __('ledger.workflow.approve'),
                actionClass: 'btn-primary',
                actionType: 'approve',
                ledgerId: $this->ledger->id,
                initialComment: null,
                text: ''
            )
            ->set('comment', 'LGTM')
            ->call('executeAction')
            ->assertDispatched('workflow-action-with-comment', function ($event, $params) {
                return $params['actionType'] === 'approve'
                    && $params['ledgerId'] === $this->ledger->id
                    && $params['comment'] === 'LGTM';
            })
            ->assertSet('showCommentModal', false);
    }

    #[Test]
    public function execute_action_return_to_draft_requires_comment(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.return_to_draft'),
                actionLabel: __('ledger.workflow.return_to_draft'),
                actionClass: 'btn-error',
                actionType: 'return_to_draft',
                ledgerId: $this->ledger->id,
                initialComment: null,
                text: ''
            )
            ->set('comment', '') // 空コメント → バリデーションエラー
            ->call('executeAction')
            ->assertHasErrors(['comment']);
    }

    #[Test]
    public function execute_action_approve_allows_empty_comment(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.approve'),
                actionLabel: __('ledger.workflow.approve'),
                actionClass: 'btn-primary',
                actionType: 'approve',
                ledgerId: $this->ledger->id,
                initialComment: null,
                text: ''
            )
            ->set('comment', '') // 空コメントOK
            ->call('executeAction')
            ->assertHasNoErrors();
    }

    // ================================================================
    // closeModal()
    // ================================================================

    #[Test]
    public function close_modal_resets_state(): void
    {
        Livewire::actingAs($this->user)
            ->test(WorkflowCommentModal::class)
            ->dispatch('open-workflow-comment-modal',
                title: __('ledger.workflow.approve'),
                actionLabel: __('ledger.workflow.approve'),
                actionClass: 'btn-primary',
                actionType: 'test',
                ledgerId: $this->ledger->id,
                initialComment: null,
                text: ''
            )
            ->set('comment', 'some comment')
            ->call('closeModal')
            ->assertSet('showCommentModal', false)
            ->assertSet('comment', '')
            ->assertSet('actionType', '')
            ->assertSet('targetLedgerId', null);
    }
}
