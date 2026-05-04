<?php

namespace App\Livewire\Ledger;

use App\Helpers\SearchHelper;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Reactive;
use Stancl\Tenancy\Tenancy;

#[Lazy(isolate: false)]
class RecordsTableRow extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public int $ledgerId;

    public int $columnId;

    public bool $canView = false;

    #[Reactive]
    public ?string $highlightKeyword = null;

    public string|int|null $currentTenantId = null;

    public ?int $selectedFileId = null;

    public function mount(): void
    {
        if (is_null($this->tenantId)) {
            $this->tenantId = $this->currentTenantId ?? request()->route()?->originalParameters()['tenant'] ?? null;
        }

        if ($this->tenantId) {
            $this->initializeTenantContext(app(Tenancy::class));
        }
    }

    public function render()
    {
        $ledgerRecord = Ledger::with(['define'])->findOrFail($this->ledgerId);
        $attachmentsStartedAt = microtime(true);
        $attachments = $this->loadAttachments($ledgerRecord);

        $attachmentsFetchDurationMs = (microtime(true) - $attachmentsStartedAt) * 1000;

        $files = $this->buildFiles($attachments, $ledgerRecord);

        return view('livewire.ledger.records-table-row', [
            'files' => $files,
            'attachmentsFetchDurationMs' => $attachmentsFetchDurationMs,
            'columnId' => $this->columnId,
            'canView' => $this->canView,
            'currentTenantId' => $this->currentTenantId,
            'highlightKeyword' => $this->highlightKeyword,
            'selectedFileId' => $this->selectedFileId,
        ]);
    }

    public function placeholder()
    {
        return view('livewire.ledger.records-table-row-placeholder', [
            'columnCount' => 1,
        ]);
    }

    private function loadAttachments(Ledger $ledgerRecord): Collection
    {
        return AttachedFile::where('ledger_id', $ledgerRecord->id)
            ->where('column_id', $this->columnId)
            ->get()
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function buildOriginalFilenameMap(Ledger $ledgerRecord): array
    {
        $originalFilenameMap = [];

        foreach ((array) ($ledgerRecord->content ?? []) as $columnContent) {
            if (! is_array($columnContent)) {
                continue;
            }

            foreach ($columnContent as $hashedbasename => $originalName) {
                if (is_string($originalName) && $originalName !== '') {
                    $originalFilenameMap[$hashedbasename] = $originalName;
                }
            }
        }

        return $originalFilenameMap;
    }

    /**
     * @param  Collection<int, AttachedFile>  $attachments
     * @return array<int, array<string, mixed>>
     */
    private function buildFiles(Collection $attachments, Ledger $ledgerRecord): array
    {
        $searchKeywords = SearchHelper::extractKeywords($this->highlightKeyword);
        $originalFilenameMap = $this->buildOriginalFilenameMap($ledgerRecord);
        $tenantId = $this->resolveTenantId($ledgerRecord->tenant_id);

        $files = [];

        foreach ($attachments as $attachment) {
            $routeParams = [
                'tenant' => $tenantId,
                'attachedFile' => $attachment->id,
            ];

            $filename = $originalFilenameMap[$attachment->hashedbasename]
                ?? ($attachment->original_filename ?? $attachment->filename);
            $originalMimeType = $attachment->original_mime_type ?? $attachment->mime ?? 'application/octet-stream';
            $isImage = str_starts_with($originalMimeType, 'image/');
            $mainDownloadUrl = route('file.download', $routeParams);
            $thumbnailUrl = $isImage
                ? route('file.download', $routeParams + ['thumbnail' => true])
                : null;
            $originalDownloadUrl = route('file.download', $routeParams + ['original' => true]);
            $optimizedPdfDownloadUrl = $mainDownloadUrl;

            $primaryDownload = [
                'url' => $mainDownloadUrl,
                'label' => __('ledger.download'),
                'icon' => 'fa-solid fa-download',
            ];
            $secondaryDownload = null;

            if ($isImage) {
                $primaryDownload = [
                    'url' => $originalDownloadUrl,
                    'label' => __('ledger.uploadedFile.download_image'),
                    'icon' => 'fa-solid fa-download',
                ];
                $secondaryDownload = [
                    'url' => $optimizedPdfDownloadUrl,
                    'label' => 'PDF',
                    'icon' => 'fa-solid fa-file-pdf',
                    'tooltip' => __('ledger.uploadedFile.download_pdf_with_text'),
                ];
            } elseif ($originalMimeType === 'application/pdf' && $attachment->optimized) {
                $primaryDownload = [
                    'url' => $optimizedPdfDownloadUrl,
                    'label' => __('ledger.uploadedFile.download_optimized_pdf'),
                    'icon' => 'fa-solid fa-file-pdf',
                ];
                $secondaryDownload = [
                    'url' => $originalDownloadUrl,
                    'label' => 'Original',
                    'icon' => 'fa-solid fa-file',
                    'tooltip' => __('ledger.uploadedFile.download_original_pdf'),
                ];
            }

            $status = $attachment->getDisplayStatus()->value ?? 'completed';
            $isHit = ! empty($searchKeywords)
                && SearchHelper::hasHit($filename ?? '', $searchKeywords);

            $files[] = [
                'id' => $attachment->id,
                'column_id' => $attachment->column_id,
                'filename' => $filename,
                'mime' => $originalMimeType,
                'status' => $status,
                'size' => $attachment->size,
                'thumbnailUrl' => $thumbnailUrl,
                'downloadUrl' => $mainDownloadUrl,
                'primary_download' => $primaryDownload,
                'secondary_download' => $secondaryDownload,
                'created_at' => $attachment->created_at,
                'is_hit' => $isHit,
            ];
        }

        return $files;
    }
}
