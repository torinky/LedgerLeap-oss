<?php

namespace App\Livewire\AttachedFile;

use App\Models\AttachedFile;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TextPreviewModal extends Component
{
    public bool $showModal = false;
    public ?AttachedFile $file = null;
    public ?array $badgeInfo = null;
    public ?string $previewText = null;
    public bool $isTruncated = false;

    // パフォーマンス対策定数
    private const MAX_PREVIEW_LENGTH = 500000; // 500KB

    #[On('showTextPreview')]
    public function show(int $attachedFileId): void
    {
        $file = AttachedFile::find($attachedFileId);

        if (!$file) {
            Log::warning("AttachedFile not found for ID: {$attachedFileId}");
            $this->notifyNotFound();
            return;
        }

        if (!$file->hasPreviewableText()) {
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
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('file', 'badgeInfo', 'previewText', 'isTruncated');
    }

    public function notifyCopySuccess(): void
    {
        $this->dispatch('mary-toast', title: __('ledger.text_preview.copy_success'), icon: 'o-check');
    }

    public function notifyCopyFailed(): void
    {
        $this->dispatch('mary-toast', title: __('ledger.text_preview.copy_failed'), icon: 'o-x-mark', type: 'error');
    }

    private function notifyNotFound(): void
    {
        $this->dispatch('mary-toast', title: __('ledger.text_preview.not_found'), icon: 'o-exclamation-triangle', type: 'warning');
    }

    public function render()
    {
        return view('livewire.attached-file.text-preview-modal');
    }
}
