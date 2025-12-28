<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ActivityLogFormatter;
use App\Models\AttachedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'hash123.pdf' => 'Original Document.pdf'
            ]
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
                'hash789.pdf' => 'TestFile.pdf'
            ]
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
    public function it_handles_various_file_events()
    {
        $user = User::factory()->create();
        $file = AttachedFile::factory()->create(['hashedbasename' => 'hash_events.pdf']);
        
        $ledger = $file->ledger;
        $ledger->content = [
             'col_1' => [
                'hash_events.pdf' => 'EventFile.pdf'
            ]
        ];
        $ledger->save();
        $file->refresh();

        $events = [
            'downloaded',
            'downloaded_original',
            'downloaded_ocr_pdf',
            'viewed_thumbnail',
            'downloaded_vlm'
        ];

        foreach ($events as $event) {
            $activity = activity()
                ->performedOn($file)
                ->causedBy($user)
                ->event($event)
                ->log($event);

            $description = ActivityLogFormatter::getOperationDescription($activity);
            $this->assertEquals(__('ledger.activity.event.' . $event, ['resource' => 'EventFile.pdf']), $description);
        }
    }
}
