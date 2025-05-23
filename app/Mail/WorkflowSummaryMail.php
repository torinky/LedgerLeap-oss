<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $inspectionCount;
    public int $approvalCount;
    public int $totalCount;

    /** Create a new message instance. */
    public function __construct(int $inspectionCount, int $approvalCount)
    {
        $this->inspectionCount = $inspectionCount;
        $this->approvalCount = $approvalCount;
        $this->totalCount = $inspectionCount + $approvalCount;
    }

    /** Get the message envelope. */
    public function envelope(): Envelope
    {
        $appName = config('app.name', 'LedgerLeap');
        // 翻訳キーを使用
        return new Envelope(
            subject: __('ledger.mail.subject.summary', ['appName' => $appName, 'count' => $this->totalCount]),
        );
    }

    /** Get the message content definition. */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workflow-summary',
            with: [
                'inspectionCount' => $this->inspectionCount,
                'approvalCount' => $this->approvalCount,
                'totalCount' => $this->totalCount,
                'actionUrl' => route('notifications.index', ['tab' => 'tasks']),
            ],
        );
    }

    /** Get the attachments for the message. */
    public function attachments(): array
    {
        return [];
    }
}