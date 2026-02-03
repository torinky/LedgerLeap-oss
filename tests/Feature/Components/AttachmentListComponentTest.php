<?php

namespace Tests\Feature\Components;

use Tests\TestCase;

class AttachmentListComponentTest extends TestCase
{
    public function test_attachment_list_renders_direct_download_link_for_rpa()
    {
        $files = [
            [
                'id' => 1,
                'filename' => 'test.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'downloadUrl' => 'http://example.com/download/1',
            ],
        ];

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="compact" />', ['files' => $files]);

        // RPA compatibility check: Verify class "direct-download-link" exists
        $view->assertSee('direct-download-link');
        $view->assertSee('href="http://example.com/download/1"', false);
    }

    public function test_attachment_list_renders_icon_only_mode()
    {
        $files = [
            [
                'id' => 1,
                'filename' => 'test.jpg',
                'mime' => 'image/jpeg',
                'status' => 'completed',
                'downloadUrl' => '#',
            ],
        ];

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="icon-only" />', ['files' => $files]);

        // Verify icon-only specific markup (e.g., small button, no filename text visible in same way)
        // Adjust assertion based on actual implementation details for icon-only
        $view->assertSee('fa-file-image');
        $view->assertSee('direct-download-link');
    }

    public function test_attachment_list_renders_processing_status()
    {
        $files = [
            [
                'id' => 1,
                'filename' => 'processing.pdf',
                'mime' => 'application/pdf',
                'status' => 'processing',
                'downloadUrl' => '#',
            ],
        ];

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="compact" />', ['files' => $files]);

        $view->assertSee('animate-ping');
        $view->assertSee('bg-warning');
    }

    public function test_attachment_list_includes_alpine_display_limit_attributes()
    {
        $files = array_fill(0, 8, [
            'id' => 1,
            'filename' => 'test.pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'downloadUrl' => '#',
        ]);

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="compact" />', ['files' => $files]);

        // Verify Alpine.js data includes displayLimit and totalCount
        $view->assertSee('displayLimit: 4', false); // compact mode default
        $view->assertSee('totalCount: 8', false);
        $view->assertSee('showAll: false', false);
    }

    public function test_attachment_list_includes_correct_event_payload_for_file_inspector()
    {
        $files = [
            [
                'id' => 123,
                'filename' => 'test.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'downloadUrl' => '#',
                'column_id' => 456,
            ],
        ];

        $view = $this->blade(
            '<x-ledger.attachment-list :files="$files" mode="compact" columnId="789" search="keyword" />',
            ['files' => $files]
        );

        // Verify the handleFileClick function dispatches correct event payload structure
        $view->assertSee('id: fileId', false);
        $view->assertSee('column_id: fileColumnId || this.columnId', false);
       $view->assertSee('search: this.search', false);
        
        // Verify Alpine x-data includes required properties
        $view->assertSee('search:', false);
        $view->assertSee('columnId:', false);
    }

    public function test_attachment_list_shows_more_button_when_files_exceed_limit()
    {
        $files = array_fill(0, 6, [
            'id' => 1,
            'filename' => 'test.pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'downloadUrl' => '#',
        ]);

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="compact" />', ['files' => $files]);

        // compact mode has displayLimit=4, so with 6 files we should see "show more" button
        $view->assertSee('x-on:click="toggleShowAll()"', false);
        $view->assertSee('+2'); // hidden count
    }

    public function test_attachment_list_does_not_show_more_button_when_within_limit()
    {
        $files = array_fill(0, 3, [
            'id' => 1,
            'filename' => 'test.pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'downloadUrl' => '#',
        ]);

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="compact" />', ['files' => $files]);

        // compact mode has displayLimit=4, so with 3 files we should NOT see "show more" button
        $view->assertDontSee('x-on:click="toggleShowAll()"', false);
    }

    public function test_attachment_list_icon_only_mode_has_higher_display_limit()
    {
        $files = array_fill(0, 5, [
            'id' => 1,
            'filename' => 'test.pdf',
            'mime' => 'application/pdf',
            'status' => 'completed',
            'downloadUrl' => '#',
        ]);

        $view = $this->blade('<x-ledger.attachment-list :files="$files" mode="icon-only" />', ['files' => $files]);

        // icon-only mode has displayLimit=5
        $view->assertSee('displayLimit: 5', false);
        $view->assertSee('totalCount: 5', false);
    }
}

