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

    public ?int $fileId = null;

    public ?AttachedFile $file = null;

    public string $selectedTab = 'content';

    public function mount(): void
    {
        // 初期状態
        $this->open = false;
        $this->selectedTab = 'content'; // デフォルトは「内容」タブ
    }

    #[On('open-file-inspector')]
    public function openInspector(int $id): void
    {
        \Illuminate\Support\Facades\Log::info('FileInspector: openInspector called with id='.$id);

        try {
            $this->fileId = $id;

            // モックデータの場合（id=1-12）はダミーオブジェクトを作成
            if ($id >= 1 && $id <= 12 && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
                $mockFiles = \App\Services\Ledger\MockAttachmentService::getMockFiles();
                $data = null;
                foreach ($mockFiles as $f) {
                    if ($f['id'] == $id) {
                        $data = $f;
                        break;
                    }
                }

                if (!$data) {
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
                ]);

                // IDは後から設定（Eloquentの仕様）
                $this->file->id = $id;
                $this->file->exists = false;

                // モック用の追加プロパティ
                $this->file->mock_source = $data['mock_source'] ?? $data['source'] ?? null;
                $this->file->mock_confidence = $data['mock_confidence'] ?? $data['confidence'] ?? 0.0;
                $this->file->mock_preview_text = $data['mock_preview_text'] ?? $data['preview_text'] ?? null;
                $this->file->mock_ledger_title = $data['mock_ledger_title'] ?? $data['ledger_title'] ?? 'Mock Ledger';
                $this->file->mock_folder_path = $data['mock_folder_path'] ?? $data['folder_path'] ?? 'Mock Folder';
                $this->file->ocr_processed_at = $data['ocr_processed_at'] ?? null;

                \Illuminate\Support\Facades\Log::info('FileInspector: Mock file created from Service', [
                    'id' => $this->file->id,
                    'filename' => $this->file->filename,
                ]);

                $this->open = true;

                return;
            }

            $this->file = AttachedFile::with([
                'ledger:id,content_attached,ledger_define_id',
                'ledger.define:id,title',
                'ledger.folder:id,title',
                'creator:id,name',
                'modifier:id,name',
            ])->findOrFail($id);

            if (! Gate::allows('view', [AttachedFile::class, $this->file])) {
                $this->error(__('ledger.no_view_permission'));

                return;
            }

            $this->open = true;
            \Illuminate\Support\Facades\Log::info('FileInspector: Drawer opened successfully for file id='.$id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FileInspector open failed: '.$e->getMessage());
            $this->error(__('ledger.vlm.result_not_found'));
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->fileId = null;
        $this->file = null;
    }

    public function render()
    {
        return \view('livewire.attached-file.file-inspector');
    }
}
