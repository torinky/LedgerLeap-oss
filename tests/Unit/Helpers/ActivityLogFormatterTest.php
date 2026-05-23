<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ActivityLogFormatter;
use App\Models\AttachedFile;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ActivityLogFormatterTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // AttachedFile::factory()->create() → Ledger生成 → LedgerObserver → ProcessLedgerForRagJob
        // Queue::fake() でジョブを実際には実行させず Embeddingコンテナへの接続を防ぐ。
        Queue::fake();
    }

    #[Test]
    public function it_returns_correct_subject_name_for_attached_file()
    {
        $file = AttachedFile::factory()->create([
            'filename' => 'test_document.pdf',
            'hashedbasename' => 'hash123.pdf',
        ]);

        // Setup Ledger content to return original_filename
        $ledger = $file->ledger;
        $ledger->content = [
            'col_1' => [
                'hash123.pdf' => 'Original Document.pdf',
            ],
        ];
        $ledger->save();

        // Reload file to ensure accessor works
        $file->refresh();

        // Create an activity
        $activity = activity()
            ->performedOn($file)
            ->log('test');

        $name = ActivityLogFormatter::getSubjectNameForDisplay($activity);

        $this->assertEquals('添付ファイル: [ Original Document.pdf ] ', $name);
    }

    #[Test]
    public function it_returns_filename_subject_name_for_attached_file_if_original_filename_is_null()
    {
        $file = AttachedFile::factory()->create([
            'filename' => 'test_document.pdf',
            'hashedbasename' => 'hash456.pdf',
        ]);

        // Ensure Ledger content does NOT have the filename
        $ledger = $file->ledger;
        $ledger->content = [];
        $ledger->save();
        $file->refresh();

        // Create an activity
        $activity = activity()
            ->performedOn($file)
            ->log('test');

        $name = ActivityLogFormatter::getSubjectNameForDisplay($activity);

        $this->assertEquals('添付ファイル: [ test_document.pdf ] ', $name);
    }

    #[Test]
    public function it_returns_formatted_description_for_file_operations()
    {
        $user = User::factory()->create();
        $file = AttachedFile::factory()->create([
            'hashedbasename' => 'hash789.pdf',
        ]);

        $ledger = $file->ledger;
        $ledger->content = [
            'col_1' => [
                'hash789.pdf' => 'TestFile.pdf',
            ],
        ];
        $ledger->save();
        $file->refresh();

        $activity = activity()
            ->performedOn($file)
            ->causedBy($user)
            ->event('downloaded')
            ->log('Downloaded file');

        $description = ActivityLogFormatter::getOperationDescription($activity);

        $this->assertEquals(__('ledger.activity.event.downloaded', ['resource' => 'TestFile.pdf']), $description);
    }

    #[Test]
    public function it_returns_file_name_display_and_inspector_link_for_attached_file_subject()
    {
        $file = AttachedFile::factory()->create([
            'filename' => 'test_document.pdf',
            'hashedbasename' => 'hash123.pdf',
        ]);

        $file->refresh();

        $activity = activity()
            ->performedOn($file)
            ->log('test');

        $this->assertSame('添付ファイル: test_document.pdf', ActivityLogFormatter::getSubjectDisplay($activity));
        $this->assertSame(
            route('ledger.show', ['tenant' => tenant()->id, 'ledgerId' => $file->ledger->id, 'file' => $file->id]),
            ActivityLogFormatter::getSubjectDetailLink($activity)
        );
    }

    #[Test]
    public function it_uses_the_subject_tenant_when_the_live_tenant_context_is_missing()
    {
        $file = AttachedFile::factory()->create([
            'filename' => 'tenant_fallback.pdf',
            'hashedbasename' => 'hash-fallback.pdf',
        ]);

        $activity = activity()
            ->performedOn($file)
            ->log('test');

        $currentTenant = tenant();
        tenancy()->end();

        try {
            $this->assertSame(
                route('ledger.show', ['tenant' => $file->tenant_id, 'ledgerId' => $file->ledger->id, 'file' => $file->id]),
                ActivityLogFormatter::getSubjectDetailLink($activity)
            );
        } finally {
            if ($currentTenant) {
                tenancy()->initialize($currentTenant);
            }
        }
    }

    #[Test]
    public function it_translates_attached_file_subject_display_in_fallback_path()
    {
        $activity = new Activity;
        $activity->subject_type = AttachedFile::class;
        $activity->subject_id = 18;
        $activity->setRelation('subject', new class
        {
            public int $id = 18;
        });

        $display = ActivityLogFormatter::getSubjectDisplay($activity);

        $this->assertSame(__('ledger.activity.model_name.attached_file').': 18', $display);
    }

    #[Test]
    public function it_translates_content_attached_attribute_label_in_changes()
    {
        $payload = [
            'event' => 'updated',
            'properties' => [
                'attributes' => [
                    'content_attached' => '{"1":"new value"}',
                ],
                'old' => [
                    'content_attached' => '{"1":"old value"}',
                ],
            ],
        ];

        $changes = ActivityLogFormatter::formatChanges($payload);

        $this->assertStringContainsString(__('ledger.activity.changes.attribute_label.content_attached'), $changes->toHtml());
    }

    #[Test]
    public function it_handles_various_file_events()
    {
        $user = User::factory()->create();
        $file = AttachedFile::factory()->create(['hashedbasename' => 'hash_events.pdf']);

        $ledger = $file->ledger;
        $ledger->content = [
            'col_1' => [
                'hash_events.pdf' => 'EventFile.pdf',
            ],
        ];
        $ledger->save();
        $file->refresh();

        $events = [
            'downloaded',
            'downloaded_original',
            'downloaded_ocr_pdf',
            'viewed_thumbnail',
            'downloaded_vlm',
        ];

        foreach ($events as $event) {
            $activity = activity()
                ->performedOn($file)
                ->causedBy($user)
                ->event($event)
                ->log($event);

            $description = ActivityLogFormatter::getOperationDescription($activity);
            $this->assertEquals(__('ledger.activity.event.'.$event, ['resource' => 'EventFile.pdf']), $description);
        }
    }
}
