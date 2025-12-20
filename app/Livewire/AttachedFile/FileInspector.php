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
    public ?string $activeSource = null;
    public string $searchKeyword = '';
    public bool $isExpanded = false; // 大規模テキストの全表示フラグ
    public array $mockData = []; // モックデータ保持用

    public function mount(): void
    {
        // 初期状態
        $this->open = false;
        $this->isLoading = false;
        $this->selectedTab = 'content'; // デフォルトは「内容」タブ
    }

    /**
     * Livewireのハイドレーション時（リクエスト間）の処理
     * モックデータの場合はモデルがDBにないため、手動で再構築する
     */
    public function hydrate(): void
    {
        if ($this->fileId >= 1 && $this->fileId <= 12 && !empty($this->mockData)) {
            $this->reconstructMockFile();
        }
    }

    /**
     * 保持している $mockData から AttachedFile モデルを再構築する
     */
    private function reconstructMockFile(): void
    {
        $data = $this->mockData;
        $this->file = new AttachedFile([
            'filename' => $data['filename'],
            'original_filename' => $data['filename'],
            'mime' => $data['mime'],
            'original_mime_type' => $data['original_mime_type'] ?? $data['mime'],
            'size' => $data['size'] ?? 0,
            'created_at' => $data['created_at'] ?? now()->subDays(10),
            'updated_at' => now()->subDays(2),
            'vlm_confidence' => $data['mock_confidence'] ?? $data['confidence'] ?? 0.0,
            'vlm_markdown' => $data['mock_preview_text'] ?? $data['preview_text'] ?? null,
            'finalized_source' => strtolower($data['mock_source'] ?? $data['source'] ?? 'tika'),
            'ocr_processed_at' => $data['ocr_processed_at'] ?? null,
        ]);
        $this->file->id = $this->fileId;
        $this->file->exists = false;
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

            $this->activeSource = $this->file->finalized_source ?? 'tika';
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

        if (!$data) {
            $data = $mockFiles[0];
        }

        $this->mockData = $data;
        $this->reconstructMockFile();

        $this->activeSource = $this->file->finalized_source;
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
        $this->mockData = [];
        $this->activeSource = null;
        $this->searchKeyword = '';
        $this->isExpanded = false;
    }

    public function getPreviewText(bool $withHighlight = true): ?string
    {
        if (!$this->file) {
            return null;
        }

        // 1. テキスト取得
        if ($this->file->id >= 1 && $this->file->id <= 12 && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
            // モックデータの場合はソース固有のテキストがあれば優先
            $baseText = $this->mockData['mock_preview_text'] ?? '';
            $text = match ($this->activeSource) {
                'vlm' => $this->mockData['mock_vlm_text'] ?? ("【AI解析結果】\n" . $baseText . "\n\n※この内容はVLMによって生成された要約です。"),
                'ocr' => $this->mockData['mock_ocr_text'] ?? ("【文字認識結果】\n" . $baseText),
                'tika' => $this->mockData['mock_tika_text'] ?? ("【システム抽出結果】\n" . $baseText),
                default => $baseText,
            };
        } else {
            $text = match ($this->activeSource) {
                'vlm' => $this->file->vlm_markdown,
                'ocr', 'tika' => $this->file->getOcrTikaFormattedText($this->activeSource),
                default => null,
            };
        }

        if (!$text) {
            return null;
        }

        // 2. 段階的ロード（大規模テキスト対応）: とりあえず先頭10,000文字で制限
        $limit = 10000;
        $isTruncated = !$this->isExpanded && mb_strlen($text) > $limit;
        if ($isTruncated && $withHighlight) {
            $text = mb_substr($text, 0, $limit) . "\n\n... (テキストが長いため省略されました。全表示ボタンで確認できます) ...";
        }

        // 3. ハイライト処理 (HTML出力用のみ)
        if ($withHighlight) {
            // Markdownの場合はエスケープを Markdown レンダラーに任せるが、
            // ハイライトタグを挿入するために一旦エスケープが必要な場合がある。
            // ここでは「非Markdown」時のみ安全にエスケープしてハイライトする。
            if ($this->activeSource !== 'vlm') {
                $text = e($text);
            }

            if (!empty($this->searchKeyword)) {
                $quoted = preg_quote($this->searchKeyword, '/');
                $text = preg_replace('/(' . $quoted . ')/iu', '<mark class="bg-yellow-200 text-black px-0.5 rounded">$1</mark>', $text);
            }
        }

        return $text;
    }

/**
 * 各抽出ソースの状態を取得する（UI用）
 * @return string available|processing|missing|error
 */
public function getSourceStatus(string $source): string
{
    if (!$this->file) return 'missing';

    // モックデータの場合
    if ($this->file->id >= 1 && $this->file->id <= 12 && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
        if (empty($this->mockData)) return 'missing';

        return match ($source) {
            'vlm' => $this->mockData['mock_vlm_status'] ?? ($this->mockData['mock_vlm_text'] ? 'completed' : 'missing'),
            'ocr' => $this->mockData['mock_ocr_status'] ?? ($this->mockData['mock_ocr_text'] ? 'completed' : 'missing'),
            'tika' => $this->mockData['mock_tika_status'] ?? ($this->mockData['mock_tika_text'] ? 'completed' : 'missing'),
            default => 'missing',
        };
    }

    // 本番データの場合
    return match ($source) {
        'vlm' => $this->file->vlm_markdown ? 'completed' : ($this->file->vlm_confidence === null ? 'processing' : 'missing'),
        'ocr' => $this->file->ocr_processed_at ? 'completed' : 'processing',
        'tika' => 'completed', // Tikaは基本常に利用可能とする予定
        default => 'missing',
    };
}

    public function toggleExpand(): void
    {
        $this->isExpanded = !$this->isExpanded;
    }

    public function render()
    {
        return \view('livewire.attached-file.file-inspector');
    }
}
