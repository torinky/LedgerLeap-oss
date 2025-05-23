<?php

namespace App\Mail;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkflowActionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public NotificationType $notificationType;
    public LedgerDiff $ledgerDiff;
    public ?User $causer;
    public ?string $comment;
    public string $subjectLine;
    public string $greeting;
    public string $line1;
    public ?string $line2 = null;
    public string $actionText;
    public string $actionUrl;

    public $subject;

    public function __construct(NotificationType $notificationType, Model $subject, ?User $causer, ?string $comment)
    {
        $this->notificationType = $notificationType;
        $this->subject = $subject; // Model を受け取る
        $this->causer = $causer;
        $this->comment = $comment;
        $this->configureMailContent();
    }


    /**
     * 通知タイプに応じてメールの内容を設定する
     */
    protected function configureMailContent(): void
    {
        // subject の型に応じて LedgerDiff または Ledger を取得
        $ledgerDiff = ($this->subject instanceof LedgerDiff) ? $this->subject : null;
        $ledger = ($this->subject instanceof Ledger) ? $this->subject : $ledgerDiff?->ledger;

        // $ledger が null でないことを確認してからタイトル等を取得
        $ledgerTitle = $ledger?->define?->title ?? __('ledger.unknown_ledger');
        $appName = config('app.name', 'LedgerLeap');
        $causerName = $this->causer?->name ?? __('ledger.unknown_user');
        $applicantName = $ledger?->creator?->name ?? __('ledger.unknown_user');

        switch ($this->notificationType->name) {
            case 'status_returned_to_draft':
                $this->subjectLine = __('ledger.mail.subject.returned', ['appName' => $appName, 'title' => $ledgerTitle]);
                $this->greeting = __('ledger.mail.greeting.returned', ['userName' => $causerName]);
                $this->line1 = __('ledger.mail.body.line1.returned', ['comment' => $this->comment ?: __('ledger.no_comment')]);
                $this->actionText = __('ledger.mail.action.view_ledger');
                $this->actionUrl = $ledger ? route('ledger.show', ['ledgerId' => $ledger->id]) : '#'; // $ledger null チェック
                break;
            case 'approved':
                $this->subjectLine = __('ledger.mail.subject.approved', ['appName' => $appName, 'title' => $ledgerTitle]);
                $this->greeting = __('ledger.mail.greeting.approved', ['userName' => $applicantName]); // 受信者は申請者
                $this->line1 = __('ledger.mail.body.line1.approved', ['approverName' => $causerName]);
                $this->actionText = __('ledger.mail.action.view_approved_ledger');
                $this->actionUrl = $ledger ? route('ledger.show', ['ledgerId' => $ledger->id]) : '#';
                break;
            case 'inspection_requested':
                $this->subjectLine = __('ledger.mail.subject.inspection_requested', ['appName' => $appName, 'title' => $ledgerTitle]);
                $this->greeting = __('ledger.mail.greeting.inspection_requested', ['userName' => __('担当者')]); // 受信者名は不明なので固定テキスト
                $this->line1 = __('ledger.mail.body.line1.inspection_requested', ['requesterName' => $causerName]);
                $this->actionText = __('ledger.mail.action.view_inspection_tasks');
                $this->actionUrl = $ledger ? route('notifications.index', ['tab' => 'tasks']) : '#';
                break;
            case 'approval_requested':
                $this->subjectLine = __('ledger.mail.subject.approval_requested', ['appName' => $appName, 'title' => $ledgerTitle]);
                $this->greeting = __('ledger.mail.greeting.approval_requested', ['userName' => __('担当者')]); // 受信者名は不明なので固定テキスト
                $this->line1 = __('ledger.mail.body.line1.approval_requested', ['inspectorName' => $causerName]);
                $this->actionText = __('ledger.mail.action.view_approval_tasks');
                $this->actionUrl = $ledger ? route('notifications.index', ['tab' => 'tasks']) : '#';
                break;
            case 'inspection_completed':
                $this->subjectLine = __('ledger.mail.subject.inspection_completed', ['appName' => $appName, 'title' => $ledgerTitle]);
                $this->greeting = __('ledger.mail.greeting.inspection_completed', ['userName' => $applicantName]);
                $this->line1 = __('ledger.mail.body.line1.inspection_completed', ['inspectorName' => $causerName]);
                $this->line2 = __('ledger.mail.body.line2.inspection_completed');
                $this->actionText = __('ledger.mail.action.view_ledger_status');
                $this->actionUrl = $ledger ? route('ledger.show', ['ledgerId' => $ledger->id]) : '#';
                break;
            default: // GenericNotification から呼ばれた場合など
                $this->subjectLine = __('ledger.mail.subject.generic', ['appName' => $appName, 'type' => $this->notificationType->name]);
                $this->greeting = __('ledger.mail.greeting.generic');
                $subjectTypeName = __('ledger.notification_types.' . $this->notificationType->name) ?? $this->notificationType->name;
                $this->line1 = __('ledger.mail.body.line1.generic', [
                    'causerName' => $causerName,
                    'subjectType' => $subjectTypeName,
                    'subjectId' => $this->subject->id ?? 'N/A', // subject の ID を表示
                ]);
                $this->actionText = __('ledger.mail.action.view_details');
                // 適切なリンク先を決定 (例: subject が Ledger なら詳細へ)
                $this->actionUrl = ($ledger) ? route('ledger.show', ['ledgerId' => $ledger->id]) : '#';

        }
    }

    /** Get the message envelope. */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    /** Get the message content definition. */
    public function content(): Content
    {
        $ledgerTitle = null;
        if ($this->subject instanceof Ledger) {
            $ledgerTitle = $this->subject->define?->title;
        } else {
            $ledgerTitle = $this->subject instanceof LedgerDiff
                ? $this->subject->ledger?->define?->title
                : (null);
        }

        return new Content(
            markdown: 'mail.workflow-action',
            with: [
                'greeting' => $this->greeting,
                'line1' => $this->line1,
                'line2' => $this->line2,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'comment' => $this->comment,
                'ledgerTitle' => $ledgerTitle ?? __('ledger.unknown_ledger'), // null チェック
                'causerName' => $this->causer?->name,
            ],
        );
    }

    /** Get the attachments for the message. */
    public function attachments(): array
    {
        return [];
    }
}