<?php

namespace App\Mail;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskClaimedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Ledger $ledger;

    public User $claimer; // 引き継ぎ操作を行ったユーザー

    public ?User $originalAssignee; // 元の担当者 (null の場合あり)

    public User $newAssignee; // 新しい担当者 (claimer と同じ場合あり)

    public ?string $comment;

    public string $recipientType; // 'new_assignee', 'original_assignee', 'applicant'

    public string $subjectLine;

    public string $greeting;

    public string $line1;

    public ?string $line2 = null;

    public string $actionText;

    public string $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Ledger $ledger, User $claimer, ?User $originalAssignee, User $newAssignee, ?string $comment, string $recipientType)
    {
        $this->ledger = $ledger;
        $this->claimer = $claimer;
        $this->originalAssignee = $originalAssignee;
        $this->newAssignee = $newAssignee;
        $this->comment = $comment;
        $this->recipientType = $recipientType; // 通知先に応じて内容を調整するため

        $this->configureMailContent();
    }

    protected function configureMailContent(): void
    {
        $appName = config('app.name', 'LedgerLeap');
        $ledgerTitle = $this->ledger->define?->title ?? __('ledger.unknown_ledger');

        $this->subjectLine = __('ledger.mail.subject.task_claimed', ['appName' => $appName, 'title' => $ledgerTitle]);
        $this->line1 = __('ledger.mail.body.line1.task_claimed_common', ['ledgerTitle' => $ledgerTitle]);
        $this->actionText = __('ledger.mail.action.view_task_details');
        $this->actionUrl = route('ledger.show', ['tenant' => $this->ledger->tenant_id, 'ledgerId' => $this->ledger->id]);

        switch ($this->recipientType) {
            case 'new_assignee':
                $this->greeting = __('ledger.mail.greeting.task_claimed_to_new_assignee', [
                    'newAssigneeName' => $this->newAssignee->name,
                    'claimerName' => $this->claimer->name,
                ]);
                break;
            case 'original_assignee':
                $this->greeting = __('ledger.mail.greeting.task_claimed_to_original_assignee', [
                    'originalAssigneeName' => $this->originalAssignee?->name ?? __('ledger.unknown_user'),
                    'title' => $ledgerTitle,
                    'claimerName' => $this->claimer->name,
                ]);
                break;
            case 'applicant':
                $this->greeting = __('ledger.mail.greeting.task_claimed_to_applicant', [
                    'applicantName' => $this->ledger->creator?->name ?? __('ledger.assignee'),
                    'title' => $ledgerTitle,
                    'originalAssigneeName' => $this->originalAssignee?->name ?? __('ledger.original_assignee'),
                    'newAssigneeName' => $this->newAssignee->name,
                ]);
                break;
            default:
                $this->greeting = __('ledger.mail.greeting.task_claimed_common'); // フォールバック
        }

        if ($this->comment) {
            $this->line2 = __('ledger.mail.body.line2.task_claimed_comment_prefix').' '.$this->comment;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.task-claimed-mail', // 新しいテンプレート
            with: [
                'greeting' => $this->greeting,
                'line1' => $this->line1,
                'line2' => $this->line2,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'comment' => $this->comment,
                'ledgerTitle' => $this->ledger->define?->title ?? __('ledger.unknown_ledger'),
                'claimerName' => $this->claimer->name,
                'originalAssigneeName' => $this->originalAssignee?->name,
                'newAssigneeName' => $this->newAssignee->name,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
