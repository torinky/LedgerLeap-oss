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

        // processing status should show warning color/icon or animate-ping
        $view->assertSee('animate-ping');
        $view->assertSee('bg-warning');
    }
}
