<?php

namespace App\Livewire\AttachedFile;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class FileInspector extends Component
{
    use InitializesTenantContext, Toast;

    public bool $open = false;

    public bool $isLoading = false;

    public ?int $fileId = null;

    public ?AttachedFile $file = null;

    public string $selectedTab = 'content';

    public function mount(): void
    {
        // 初期状態
        $this->open = false;
        $this->isLoading = false;
        $this->selectedTab = 'content'; // デフォルトは「内容」タブ
    }

    #[On('open-file-inspector')]
    public function openInspector(int|array $id): void
    {
        if (is_array($id)) {
            $id = $id['id'] ?? null;
        }

        if (!$id) {
            return;
        }

        \Illuminate\Support\Facades\Log::info('FileInspector: openInspector called with id='.$id);

        $this->fileId = $id;
        $this->file = null;
        $this->isLoading = true;

        // モックデータの場合（id=1-12）は即座にロード
        if ($id >= 1 && $id <= 12 && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
            // 開発環境でローディングUIを確認できるように僅かな遅延を入れる
            if (app()->environment('local')) {
                usleep(800000); // 0.8秒
            }
            $this->loadMockData($id);
            $this->open = true;
            $this->isLoading = false;

            return;
        }

        // 実データの場合はデータをロード
        $this->loadData($id);
    }

    /**
     * 実データをロードする
     */
    public function loadData(int $id): void
    {
        try {
            $this->file = AttachedFile::with([
                'ledger:id,content,content_attached,ledger_define_id',
                'ledger.define:id,folder_id,title,workflow_enabled',
                'ledger.define.folder:id,title',
                'creator:id,name',
                'modifier:id,name',
                'activities.causer:id,name',
            ])->findOrFail($id);

            // 権限チェック: LedgerPolicy::view を使用
            // AttachedFilePolicyが空実装のため、親である台帳の権限を確認する
            if (! Gate::allows('view', $this->file->ledger)) {
                $this->error(__('ledger.no_view_permission'));
                $this->dispatch('mary-toast', type: 'error', title: __('ledger.no_view_permission'));
                $this->close();

                return;
            }

            $this->open = true;
            $this->isLoading = false;
            \Illuminate\Support\Facades\Log::info('FileInspector: Data loaded successfully for file id='.$id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FileInspector loadData failed: '.$e->getMessage());
            $this->error(__('ledger.vlm.result_not_found'));
            $this->dispatch('mary-toast', type: 'error', title: __('ledger.vlm.result_not_found'));
            $this->close();
        }
    }

    /**
     * モックデータをロードする
     */
    private function loadMockData(int $id): void
    {
        $mockFiles = \App\Services\Ledger\MockAttachmentService::getMockFiles();
        $data = null;
        foreach ($mockFiles as $f) {
            if ($f['id'] == $id) {
                $data = $f;
                break;
            }
        }

        if (! $data) {
            $data = $mockFiles[0];
        }

        $this->file = new AttachedFile([
            'filename' => $data['filename'],
            'original_filename' => $data['filename'],
            'mime' => $data['mime'],
            'original_mime_type' => $data['original_mime_type'] ?? $data['mime'],
            'size' => $data['size'] ?? 0,
            'created_at' => $data['created_at'] ?? now()->subDays(rand(1, 30)),
            'updated_at' => now()->subDays(rand(0, 5)),
            'vlm_confidence' => $data['mock_confidence'] ?? $data['confidence'] ?? 0.0,
            'vlm_markdown' => $data['mock_preview_text'] ?? $data['preview_text'] ?? null,
            'finalized_source' => strtolower($data['mock_source'] ?? $data['source'] ?? 'tika'),
            'ocr_processed_at' => $data['ocr_processed_at'] ?? null,
        ]);

        // IDは後から設定（Eloquentの仕様）
        $this->file->id = $id;
        $this->file->exists = false;

        // モック用の追加プロパティ（Blade互換性のため）
        $this->file->mock_source = $data['mock_source'] ?? $data['source'] ?? null;
        $this->file->mock_confidence = $data['mock_confidence'] ?? $data['confidence'] ?? 0.0;
        $this->file->mock_preview_text = $data['mock_preview_text'] ?? $data['preview_text'] ?? null;
        $this->file->mock_ledger_title = $data['mock_ledger_title'] ?? $data['ledger_title'] ?? 'Mock Ledger';
        $this->file->mock_folder_path = $data['mock_folder_path'] ?? $data['folder_path'] ?? 'Mock Folder';

        \Illuminate\Support\Facades\Log::info('FileInspector: Mock data loaded', [
            'id' => $this->file->id,
            'filename' => $this->file->filename,
        ]);
    }

    public function close(): void
    {
        $this->open = false;
        $this->isLoading = false;
        $this->fileId = null;
        $this->file = null;
    }

    public function render()
    {
        return \view('livewire.attached-file.file-inspector');
    }
}
