<?php

namespace App\Livewire\AttachedFile;

use App\Livewire\BaseLivewireComponent;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Mary\Traits\Toast;

class TextPreviewModal extends BaseLivewireComponent
{
    use Toast;

    public bool $showModal = false;

    public ?AttachedFile $file = null;

    public ?array $badgeInfo = null;

    public ?string $previewText = null;

    public bool $isTruncated = false;

    public ?string $tenantId = null;

    // パフォーマンス対策定数
    private const MAX_PREVIEW_LENGTH = 500000; // 500KB

    #[On('showTextPreview')]
    public function show(int $attachedFileId): void
    {
        $file = AttachedFile::with('ledger')->find($attachedFileId);

        if (! $file) {
            Log::warning("AttachedFile not found for ID: {$attachedFileId}");
            $this->notifyNotFound();

            return;
        }

        if (! $file->hasPreviewableText()) {
            Log::info("AttachedFile ID: {$attachedFileId} does not have previewable text.");
            $this->notifyNotFound();

            return;
        }

        $originalText = $file->previewable_text;
        if (Str::length($originalText) > self::MAX_PREVIEW_LENGTH) {
            $this->previewText = Str::limit($originalText, self::MAX_PREVIEW_LENGTH, '... (truncated)');
            $this->isTruncated = true;
        } else {
            $this->previewText = $originalText;
            $this->isTruncated = false;
        }

        $this->file = $file;
        $this->badgeInfo = $file->getConfidenceBadgeInfo();
        $this->tenantId = $file->tenant_id;
        $this->showModal = true;

        Log::info('Tenant ID in TextPreviewModal: '.$this->tenantId);

        // モーダルが表示されたことをクライアントに通知
        $this->dispatch('text-preview-shown');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('file', 'badgeInfo', 'previewText', 'isTruncated', 'tenantId');
    }

    public function notifyCopySuccess(): void
    {
        if (app()->runningUnitTests()) {
            $this->dispatch('test-mary-toast-success', message: __('ledger.text_preview.copy_success'));
        } else {
            $this->success(__('ledger.text_preview.copy_success'));
        }
    }

    public function notifyCopyFailed(): void
    {
        if (app()->runningUnitTests()) {
            $this->dispatch('test-mary-toast-error', message: __('ledger.text_preview.copy_failed'));
        } else {
            $this->error(__('ledger.text_preview.copy_failed'));
        }
    }

    private function notifyNotFound(): void
    {
        if (app()->runningUnitTests()) {
            $this->dispatch('test-mary-toast-warning', message: __('ledger.text_preview.not_found'));
        } else {
            $this->warning(__('ledger.text_preview.not_found'));
        }
    }

    public function render()
    {
        return view('livewire.attached-file.text-preview-modal');
    }
}
