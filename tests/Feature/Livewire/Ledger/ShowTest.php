<?php

namespace tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Livewire\Ledger\Show;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\AutoLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use tests\TestCase;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Jobs\Ledger\GenerateThumbnail;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $inspector;
    private User $approver;
    private Ledger $ledger;
    private Role $inspectorRole;
    private Role $approverRole;
    private Folder $folder; // ADDED

    protected function setUp(): void
    {
        parent::setUp();


        // ユーザーを作成
        $this->user = User::factory()->create();
        $this->inspector = User::factory()->create();
        $this->approver = User::factory()->create();

        // ロールを作成
        $this->inspectorRole = Role::create(['name' => 'inspector']);
        $this->approverRole = Role::create(['name' => 'approver']);
        $this->inspector->assignRole($this->inspectorRole);
        $this->approver->assignRole($this->approverRole);

        // フォルダと台帳定義を作成
        $this->folder = Folder::factory()
            ->withRequiredRoles(
                inspectors: [$this->inspectorRole],
                approvers: [$this->approverRole]
            )
            ->create();

        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['workflow_enabled' => true]);

        // 台帳レコードを作成
        $this->ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->for($this->user, 'creator')
            ->create(['status' => WorkflowStatus::DRAFT]);

        // 最新のDiffを作成
        $diff = LedgerDiff::factory()->for($this->ledger)->create([
            'inspector_id' => $this->inspector->id,
            'approver_id' => $this->approver->id,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);
        $this->ledger->latest_diff_id = $diff->id;
        $this->ledger->save();
    }

    #[Test]
    public function component_renders_successfully()
    {
        $this->actingAs($this->user);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertStatus(200)
            ->assertSee($this->ledger->define->name);
    }

    #[Test]
    public function it_loads_ledger_record_on_mount()
    {
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        // ledgerRecord の id をアサート
        $component->assertSet('ledgerRecord.id', $this->ledger->id);

        // ledgerDefineRecord は protected なので、インスタンスから直接アクセスしてアサート
        $this->assertEquals($this->ledger->define->id, $component->instance()->ledgerRecord->define->id);
    }

    #[Test]
    public function it_shows_correct_buttons_when_status_is_pending_inspection()
    {
        $this->ledger->update(['status' => WorkflowStatus::PENDING_INSPECTION]);

        // 点検者でログイン
        $this->actingAs($this->inspector);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertSee(__('ledger.workflow.request_approval_short'))
            ->assertSee(__('ledger.workflow.return_to_draft_short'))
            ->assertDontSeeHtml('<button[^>]*>' . __('ledger.workflow.approve') . '</button>');

        // 他のユーザーでログイン
        $this->actingAs($this->user);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(__('ledger.workflow.request_approval_short'))
            ->assertDontSee(__('ledger.workflow.return_to_draft_short'));
    }

    #[Test]
    public function it_shows_correct_buttons_when_status_is_pending_approval()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id]
        ]);
        $this->ledger->update(['status' => WorkflowStatus::PENDING_APPROVAL]);

        // 承認者でログイン
        $this->actingAs($this->approver);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $component
            ->assertSee(__('ledger.workflow.approve'))
            ->assertSee(__('ledger.workflow.return_to_draft_short'));

        // Livewire の assertDontSeeHtml が部分文字列マッチングで誤動作する可能性があるため、PHPUnit の assertStringNotContainsString を直接使用
        // Livewire の assertDontSee が wire:snapshot の影響を受けるため、HTML から wire:snapshot を削除してアサート
        $cleanedHtml = preg_replace('/wire:snapshot="[^"]*"/s', '', $component->html());
        $this->assertStringNotContainsString(
            __('ledger.workflow.request_approval_short'),
            $cleanedHtml);
        $component->assertDontSeeHtml('<button[^>]*wire:click="openApproverSelectModal"[^>]*>');

        // 他のユーザーでログイン
        $this->actingAs($this->user);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(">" . __('ledger.workflow.approve') . "<")
            ->assertDontSee(">" . __('ledger.workflow.return_to_draft_short') . "<");
    }

    #[Test]
    public function it_shows_no_workflow_buttons_when_status_is_approved()
    {
        $this->ledger->latestDiff->update([
            'completed_inspector_role_ids' => [$this->inspectorRole->id],
            'completed_approver_role_ids' => [$this->approverRole->id],
        ]);
        $this->ledger->update(['status' => WorkflowStatus::APPROVED]);

        $this->actingAs($this->user);

        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->assertDontSee(__('ledger.workflow.request_approval_short'))
            ->assertDontSeeHtml('<button[^>]*>' . __('ledger.workflow.approve') . '</button>')
            ->assertDontSee(__('ledger.workflow.return_to_draft_short'));
    }

    #[Test]
    public function it_prepares_content_diff_correctly()
    {
        // 1. 準備: 旧/新コンテンツとカラム定義
        $oldContent = [
            'Original Text',
            "123",
            'Option A',
        ];
        $newContent = [
            'Updated Text', // 変更
            "456",          // 変更
            'Option A',   // 変更なし
            'New Value',  // 追加
        ];

        // カラム定義はオブジェクトの配列として扱う
        $oldColumnDefine = [
            (object)['id' => 0, 'name' => 'Text Column', 'type' => 'text', 'order' => 1],
            (object)['id' => 1, 'name' => 'Number Column', 'type' => 'number', 'order' => 2],
            (object)['id' => 2, 'name' => 'Select Column', 'type' => 'select', 'order' => 3],
        ];
        $newColumnDefine = [
            (object)['id' => 0, 'name' => 'Text Column', 'type' => 'text', 'order' => 1],
            (object)['id' => 1, 'name' => 'Number Column', 'type' => 'number', 'order' => 2],
            (object)['id' => 2, 'name' => 'Select Column', 'type' => 'select', 'order' => 3],
            (object)['id' => 3, 'name' => 'New Column', 'type' => 'text', 'order' => 4],
        ];

        // 2. 状態のセットアップ
        // テスト用の台帳定義と台帳を作成
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'workflow_enabled' => false, // ワークフロー無効
                'column_define' => $oldColumnDefine,
            ]);

        // --- 古い状態を作成 ---
        // CreateColumn::saveDirectly の新規作成ロジックを模倣
        $ledger = Ledger::create([
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => $oldContent,
            'content_attached' => [],
            'version' => 1,
        ]);
//        dd($ledger->content);

        $oldDiff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'content' => $oldContent,
            'column_define' => $oldColumnDefine,
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'created_at' => now()->subDay(), // 差分比較のために時刻をずらす
        ]);

        $ledger->update(['latest_diff_id' => $oldDiff->id]);

        // --- 新しい状態を作成 ---
        // CreateColumn::saveDirectly の更新ロジックを模倣

        // まず、台帳定義を更新
        $ledgerDefine->update(['column_define' => $newColumnDefine]);
        $ledger->refresh(); // defineリレーションを再読み込みさせる

        // 次に、Ledgerを更新
        $ledger->update([
            'content' => $newContent,
            'modifier_id' => $this->user->id,
            'version' => $ledger->version + 1,
        ]);

        // 新しいLedgerDiffを作成し、Ledgerのlatest_diff_idを更新
        $newDiff = LedgerDiff::create([
            'ledger_id' => $ledger->id,
            'content' => $newContent,
            'column_define' => $newColumnDefine, // 更新後の定義
            'ledger_define_id' => $ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => $ledger->version,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ]);
        $ledger->update(['latest_diff_id' => $newDiff->id]);

        // 3. テスト実行とアサーション
        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id])
            ->assertSet('hasChangedColumns', true);

//        dd($ledger->content, Ledger::find($ledger->id)->content);

        // contentChanges の内容を検証
        $contentChanges = $component->get('contentChanges');

        // 'Text Column' が変更されたことを確認
        $this->assertTrue($contentChanges[0]['changed']);
        $this->assertEquals('Original Text', $contentChanges[0]['old_value']);
        $this->assertEquals('Updated Text', $contentChanges[0]['current_value']);

        // 'Number Column' が変更されたことを確認
        $this->assertTrue($contentChanges[1]['changed']);
        $this->assertEquals(123, $contentChanges[1]['old_value']);
        $this->assertEquals(456, $contentChanges[1]['current_value']);

        // 'Select Column' が変更されていないことを確認
        $this->assertFalse($contentChanges[2]['changed']);
        $this->assertEquals('Option A', $contentChanges[2]['old_value']);
        $this->assertEquals('Option A', $contentChanges[2]['current_value']);

        // 'New Column' が追加されたことを確認 (old_value が null)
        $this->assertTrue($contentChanges[3]['changed']);
        $this->assertNull($contentChanges[3]['old_value']);
        $this->assertEquals('New Value', $contentChanges[3]['current_value']);
    }
    // #[Test]
    // public function it_renders_diff_correctly_with_mroonga_style_content()
    // {
    //     // 旧/新コンテンツとカラム定義
    //     $oldContent = [
    //         0 => 'Original Text',
    //         1 => 123,
    //         2 => 'Option A',
    //     ];
    //     $newContent = [
    //         0 => 'Updated Text',
    //         1 => 456,
    //         2 => 'Option A',
    //         3 => 'New Value',
    //     ];
    //
    //     $oldColumnDefine = [
    //         ['id' => 0, 'name' => 'Text Column', 'type' => 'text', 'order' => 1],
    //         ['id' => 1, 'name' => 'Number Column', 'type' => 'number', 'order' => 2],
    //         ['id' => 2, 'name' => 'Select Column', 'type' => 'select', 'order' => 3],
    //     ];
    //     $newColumnDefine = [
    //         ['id' => 0, 'name' => 'Text Column', 'type' => 'text', 'order' => 1],
    //         ['id' => 1, 'name' => 'Number Column', 'type' => 'number', 'order' => 2],
    //         ['id' => 2, 'name' => 'Select Column', 'type' => 'select', 'order' => 3],
    //         ['id' => 3, 'name' => 'New Column', 'type' => 'text', 'order' => 4],
    //     ];
    //
    //     // 新しい LedgerDefine を用意
    //     $ledgerDefine = LedgerDefine::factory()
    //         ->for($this->folder)
    //         ->create([
    //             'workflow_enabled' => true,
    //             'column_define' => $newColumnDefine,
    //         ]);
    //
    //     // Ledger を作成（初期は oldContent としておく）
    //     $ledger = Ledger::factory()
    //         ->for($ledgerDefine, 'define')
    //         ->for($this->user, 'creator')
    //         ->create([
    //             'status' => WorkflowStatus::DRAFT,
    //             'content' => $oldContent,
    //         ]);
    //
    //     // old 側の Diff
    //     $oldDiff = LedgerDiff::factory()->for($ledger)->create([
    //         'content' => $oldContent,
    //         'column_define' => $oldColumnDefine,
    //         'created_at' => now()->subDays(2),
    //     ]);
    //
    //     // Ledger の content を new に更新
    //     $ledger->update(['content' => $newContent]);
    //
    //     // new 側の Diff
    //     $newDiff = LedgerDiff::factory()->for($ledger)->create([
    //         'content' => $newContent,
    //         'column_define' => $newColumnDefine,
    //         'created_at' => now()->subDay(),
    //     ]);
    //     $ledger->latest_diff_id = $newDiff->id;
    //     $ledger->save();
    //
    //     // 本番に近づけるため、DB の content を Mroonga 風に直接上書き
    //     $this->insertMroongaDoubleEscaped('ledgers', $ledger->id, $newContent);
    //     $this->insertMroongaDoubleEscaped('ledger_diffs', $oldDiff->id, $oldContent);
    //     $this->insertMroongaDoubleEscaped('ledger_diffs', $newDiff->id, $newContent);
    //
    //     // 再読込してから Livewire テスト
    //     $ledger->refresh();
    //
    //     $this->actingAs($this->user);
    //     $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id]);
    //
    //     $html = $component->html();
    //
    //     // 旧値/新値/未変更/追加が表示上確認できること
    //     $this->assertStringContainsString('Original Text', $html);
    //     $this->assertStringContainsString('Updated Text', $html);
    //     $this->assertStringContainsString('123', $html);
    //     $this->assertStringContainsString('456', $html);
    //     $this->assertStringContainsString('Option A', $html);
    //     $this->assertStringContainsString('New Value', $html);
    //
    //     // 行状態のクラスがあれば軽く確認（テンプレ側のクラス名に合わせて調整可）
    //     // 正規表現は緩めにし、表示崩れでも過度にフレークしないようにする
    //     $maybeChanged = preg_match('/Original Text.*Updated Text/s', $html);
    //     $this->assertTrue((bool) $maybeChanged, 'Changed row with Original/New text should be present');
    //
    //     $maybeUnchanged = substr_count($html, 'Option A') >= 1;
    //     $this->assertTrue($maybeUnchanged, 'Unchanged value should appear');
    //
    //     $maybeAdded = substr_count($html, 'New Value') >= 1;
    //     $this->assertTrue($maybeAdded, 'Added value should appear');
    // }
    #[Test]
    public function it_finds_comparison_target_diff_correctly()
    {
        // このテストのために、setUpで作成されたdiffを一旦削除してクリーンな状態から始める
        $this->ledger->ledgerDiff()->delete();
        $this->ledger->latest_diff_id = null;
        $this->ledger->save();

        // さらに古いDiffを作成 (contentが同じ)
        $oldDiff2 = LedgerDiff::factory()->for($this->ledger)->create([
            'content' => ['oldValue1', 'oldValue2'],
            'column_define' => $this->ledger->define->column_define,
            'ledger_define_id' => $this->ledger->define->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
            'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'created_at' => now()->subDays(2), // 2日前
        ]);

        // 比較対象となる古いDiffを作成 (contentが異なる)
        $oldDiff1 = LedgerDiff::factory()->for($this->ledger)->create([
            'content' => ['oldValue1', 'oldValue2'],
            'column_define' => $this->ledger->define->column_define,
            'ledger_define_id' => $this->ledger->define->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
            'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'created_at' => now()->subDay(), // 1日前
        ]);

        // 最新のDiffのcontentを更新
        $this->ledger->update([
            'content' => ['valueA', 'valueB'],
        ]);
        $latestDiff = LedgerDiff::factory()->for($this->ledger)->create([
            'content' => ['valueA', 'valueB'],
            'column_define' => $this->ledger->define->column_define,
            'ledger_define_id' => $this->ledger->define->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
            'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
            'created_at' => now(), // 一番新しい
        ]);
        $this->ledger->latest_diff_id = $latestDiff->id;
        $this->ledger->save();

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);

        $comparisonTargetDiff = $component->get('comparisonTargetDiff');
        // findComparisonTargetDiff() が oldDiff1 を返すことを確認
        $this->assertNotNull($comparisonTargetDiff, 'Comparison target diff should not be null in the first scenario.');
        $this->assertEquals($oldDiff1->id, $comparisonTargetDiff->id);

        // contentが同じ場合、比較対象が見つからないことを確認
        // 以前のdiffのcontentをすべて最新と同じにする
        $oldDiff1->update(['content' => ['valueA', 'valueB']]);
        $oldDiff2->update(['content' => ['valueA', 'valueB']]);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $this->assertNull($component->get('comparisonTargetDiff'), 'Comparison target diff should be null when all previous contents are the same.');

        // 以前のDiffがない場合、比較対象が見つからないことを確認
        LedgerDiff::where('ledger_id', $this->ledger->id)->where('id', '!=', $latestDiff->id)->delete();
        $this->ledger->refresh();

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id]);
        $this->assertNull($component->get('comparisonTargetDiff'), 'Comparison target diff should be null when no previous diffs exist.');
    }

    #[Test]
    public function it_retries_attached_file_processing()
    {
        Bus::fake();

        $attachedFile = AttachedFile::factory()->for($this->ledger)->create([
            'status' => \App\Enums\AttachedFileStatus::PROCESSING_FAILED,
        ]);

        $this->actingAs($this->user);

        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', $attachedFile->id);

        $component->assertHasNoErrors();
        // $component->assertDispatchedJs('mary-toast', toast: ['title' => __('file.status.retry_success')]);

        $attachedFile->refresh();
        $this->assertEquals(\App\Enums\AttachedFileStatus::PENDING_INITIAL_PROCESSING, $attachedFile->status);

        Bus::assertDispatched(\App\Jobs\Ledger\ProcessAttachedFile::class, function ($job) use ($attachedFile) {
            return $job->attachedFile->id === $attachedFile->id;
        });

        // サムネイル生成ジョブも再ディスパッチされるケース
        $attachedFile->update(['status' => \App\Enums\AttachedFileStatus::THUMBNAIL_FAILED]);
        Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', $attachedFile->id);

        Bus::assertDispatched(\App\Jobs\Ledger\GenerateThumbnail::class, function ($job) use ($attachedFile) {
            return $job->attachedFileId === $attachedFile->id;
        });

        // 存在しないattachedFileIdの場合
        $component = Livewire::test(Show::class, ['ledgerId' => $this->ledger->id])
            ->call('retryProcessing', 99999);

        $component->assertHasErrors(); // エラーが発生することを確認
    }

    #[Test]
    public function it_sets_display_level_correctly_and_filters_columns()
    {
        $this->actingAs($this->user);

        // LedgerDefineのcolumn_defineを更新して、display_levelを持つカラムを設定
        $columnDefine = [
            ['id' => 1, 'name' => 'Column 1', 'display_level' => 1, 'order' => 1],
            ['id' => 2, 'name' => 'Column 2', 'display_level' => 2, 'order' => 2],
            ['id' => 3, 'name' => 'Column 3', 'display_level' => 3, 'order' => 3],
            ['id' => 4, 'name' => 'Column 4', 'display_level' => 4, 'order' => 4], // Should not be shown at level 3
        ];

        // このテスト専用の台帳定義と台帳を作成
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create(['column_define' => $columnDefine]);

        $ledger = Ledger::factory()
            ->for($ledgerDefine, 'define')
            ->create();

        // 初期状態 (displayLevel = 1)
        $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id]);
        $component->assertSet('displayLevel', 1);
        // ledgerDefineRecord は protected なので、インスタンスから直接アクセス
        $this->assertNotEmpty($component->instance()->ledgerRecord->define->column_define);
        $this->assertCount(1, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);

        // displayLevel を 2 に設定
        $component->call('setDisplayLevel', 2);
        $component->assertSet('displayLevel', 2);
        $this->assertCount(2, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);
        $this->assertEquals(2, $component->get('filteredColumns')[2]['id']);

        // displayLevel を 3 に設定
        $component->call('setDisplayLevel', 3);
        $component->assertSet('displayLevel', 3);
        $this->assertCount(3, $component->get('filteredColumns'));
        $this->assertEquals(1, $component->get('filteredColumns')[1]['id']);
        $this->assertEquals(2, $component->get('filteredColumns')[2]['id']);
        $this->assertEquals(3, $component->get('filteredColumns')[3]['id']);

        // 無効な displayLevel を設定した場合、変更されないことを確認
        $component->call('setDisplayLevel', 99);
        $component->assertSet('displayLevel', 3); // 3のまま
    }

    #[Test]
    public function it_highlights_keywords_in_detail_view()
    {
        $this->actingAs($this->user);

        // 台帳データの準備
        $keyword = '詳細キーワード';
        $contentWithKeyword = ['text_column' => 'これは' . $keyword . 'を含むテキストです。'];

        // テスト用の台帳定義を作成し、text_column を含むようにする
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    ['id' => 0, 'name' => 'text_column', 'type' => 'text', 'order' => 1],
                ],
            ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => $contentWithKeyword,
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::withQueryParams(['highlight' => $keyword])
            ->test(Show::class, ['ledgerId' => $ledger->id])
            ->assertOk();

        $displayColumns = $component->get('displayColumns');
        $this->assertStringContainsString(
            '<mark class="text-error font-bold text-lg">' . $keyword . '</mark>',
            $displayColumns[0]['html']
        );
    }

    #[Test]
    public function it_displays_auto_links_in_detail_view()
    {
        $this->actingAs($this->user);

        // 自動リンク定義の準備
        AutoLink::factory()->create([
            'label' => 'Test AutoLink Detail',
            'pattern' => '/(DOC-\\d{3})/',
            'url_template' => '/l/$1',
            'is_enabled' => true,
        ]);

        // 台帳データの準備
        $autoLinkText = 'これはDOC-001を含むテキストです。';

        // テスト用の台帳定義を作成し、text_column を含むようにする
        $ledgerDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    ['id' => 0, 'name' => 'text_column', 'type' => 'text', 'order' => 1],
                ],
            ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['text_column' => $autoLinkText],
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::test(Show::class, ['ledgerId' => $ledger->id])
            ->assertOk();

        $displayColumns = $component->get('displayColumns');
        $this->assertStringContainsString(
            '<a href="/l/DOC-001"',
            $displayColumns[0]['html']
        );
        $this->assertStringContainsString(
            'DOC-001',
            $displayColumns[0]['html']
        );
    }
}