<?php

namespace App\Livewire\AttachedFile;

use App\Helpers\SearchHelper;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Gate;
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

    public ?string $mockLedgerTitle = null;

    public ?string $mockFolderPath = null;

    public ?string $mockCreatorName = null;

    public function mount(): void
    {
        // 初期状態
        $this->open = false;
        $this->isLoading = false;
        $this->selectedTab = 'content'; // デフォルトは「内容」タブ
    }

    /**
     * 現在のファイルがモックデータかどうかを判定
     */
    private function isMockFile(): bool
    {
        if (! $this->file) {
            return false;
        }

        // モックデータの場合は exists が false かつ mockData が存在する
        return ! $this->file->exists && ! empty($this->mockData);
    }

    /**
     * Livewireのハイドレーション時（リクエスト間）の処理
     * モックデータの場合はモデルがDBにないため、手動で再構築する
     */
    public function hydrate(): void
    {
        // mockDataが存在し、かつfileIdが設定されている場合にのみ再構築
        if (! empty($this->mockData) && $this->fileId) {
            $this->reconstructMockFile();
        }
    }

    /**
     * 保持している $mockData から AttachedFile モデルを再構築する
     */
    private function reconstructMockFile(): void
    {
        $data = $this->mockData;
        $this->file = (new AttachedFile)->forceFill([
            'filename' => $data['filename'] ?? '',
            'mime' => $data['mime'] ?? '',
            'original_mime_type' => $data['original_mime_type'] ?? $data['mime'] ?? '',
            'size' => $data['size'] ?? 0,
            'created_at' => $data['created_at'] ?? now()->subDays(10),
            'updated_at' => now()->subDays(2),
            'vlm_confidence' => $data['mock_confidence'] ?? $data['confidence'] ?? 0.0,
            'vlm_markdown' => $data['mock_preview_text'] ?? $data['preview_text'] ?? null,
            'finalized_source' => strtolower($data['mock_source'] ?? $data['source'] ?? 'tika'),
            'ocr_processed_at' => $data['ocr_processed_at'] ?? null,
            'tika_processed_at' => ($data['created_at'] ?? now()->subDays(10))->addSeconds(5),
            'vlm_processing_time_ms' => $data['vlm_processing_time_ms'] ?? (($data['mock_source'] ?? '') === 'VLM' ? 3500 : null),
            'processing_finalized_at' => now()->subDays(1),
        ]);
        $this->file->id = $this->fileId;
        $this->file->exists = false;

        // モック用の追加情報をセット
        $this->mockLedgerTitle = $data['mock_ledger_title'] ?? null;
        $this->mockFolderPath = $data['mock_folder_path'] ?? null;
        $this->mockCreatorName = $data['mock_creator_name'] ?? null;

        // Tikaメタデータをモックとしてシミュレート
        if (isset($data['mock_metadata'])) {
            $this->file->setRelation('ledger', (new \App\Models\Ledger)->forceFill([
                'content_attached' => [
                    $this->file->column_id => [
                        $this->file->hashedbasename => [
                            'meta' => $data['mock_metadata'],
                        ],
                    ],
                ],
            ]));
        }
    }

    public function openInspector($payload): void
    {
        $id = null;
        $search = null;

        if (is_array($payload)) {
            $id = $payload['id'] ?? null;
            $search = $payload['search'] ?? null;
            // column_idは送信されても使用しない（AttachedFile IDは一意のため）
        } else {
            $id = $payload;
        }

        if (! $id) {
            return;
        }

        \Illuminate\Support\Facades\Log::info('FileInspector: openInspector called', [
            'id' => $id,
            'search' => $search,
        ]);

        $this->fileId = $id;
        $this->searchKeyword = $search ?? '';
        $this->file = null;
        $this->isLoading = true;

        // 実データが存在するかチェック
        $realFileExists = AttachedFile::where('id', $id)->exists();

        // 実データが存在する場合は実データを優先、存在しない場合でモックモードが有効なら場合モックデータを使用
        if (! $realFileExists && $id >= 10001 && $id <= 10012 && \App\Services\Ledger\MockAttachmentService::isEnabled()) {
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
            $currentUser = auth()->user();
            $currentTenant = tenant();

            \Illuminate\Support\Facades\Log::info('FileInspector: loadData started', [
                'id' => $id,
                'user_id' => $currentUser?->id,
                'user_email' => $currentUser?->email,
                'tenant_id' => $currentTenant?->id ?? tenant('id'),
                'tenancy_initialized' => app(\Stancl\Tenancy\Tenancy::class)->initialized,
            ]);

            $this->file = AttachedFile::with([
                'ledger:id,content,content_attached,ledger_define_id',
                'ledger.define:id,folder_id,title,workflow_enabled',
                'ledger.define.folder:id,title,tenant_id,parent_id',
                'creator:id,name',
                'modifier:id,name',
                'activities.causer:id,name',
            ])->findOrFail($id);

            \Illuminate\Support\Facades\Log::info('FileInspector: File loaded', [
                'file_id' => $this->file->id,
                'has_ledger' => $this->file->ledger !== null,
            ]);

            // 権限チェック: LedgerPolicy::view を使用
            // AttachedFilePolicyが空実装のため、親である台帳の権限を確認する
            if (! $this->file->ledger) {
                \Illuminate\Support\Facades\Log::error('FileInspector: Ledger relation is null');
                $this->error(__('ledger.vlm.result_not_found'));
                $this->dispatch('mary-toast', type: 'error', title: __('ledger.vlm.result_not_found'));
                $this->close();

                return;
            }

            if (! Gate::allows('view', $this->file->ledger)) {
                $user = auth()->user();
                $ledger = $this->file->ledger;
                $folder = $ledger->define->folder ?? null;

                \Illuminate\Support\Facades\Log::warning('FileInspector: Permission denied', [
                    'user_id' => $user?->id,
                    'user_email' => $user?->email,
                    'user_roles' => $user?->roles->pluck('name')->toArray(),
                    'ledger_id' => $ledger->id,
                    'folder_id' => $folder?->id,
                    'folder_title' => $folder?->title,
                    'folder_tenant_id' => $folder?->tenant_id,
                    'folder_parent_id' => $folder?->parent_id,
                    'current_tenant_id' => tenant('id'),
                    'tenancy_initialized' => app(\Stancl\Tenancy\Tenancy::class)->initialized,
                    'gate_raw_result' => Gate::inspect('view', $ledger)->message(),
                ]);
                $this->error(
                    title: __('ledger.file_inspector.messages.permission_denied_title'),
                    description: __('ledger.file_inspector.messages.permission_denied_description')
                );
                $this->close();

                return;
            }

            // 利用可能な最初のソースを自動選択
            $this->activeSource = $this->selectFirstAvailableSource();

            // サムネイル生成チェック：画像ファイルでサムネイルがない場合は生成
            $this->ensureThumbnailExists();

            $this->open = true;
            $this->isLoading = false;
            \Illuminate\Support\Facades\Log::info('FileInspector: Data loaded successfully', [
                'file_id' => $id,
                'active_source' => $this->activeSource,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FileInspector loadData failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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

        $this->mockData = $data;
        $this->reconstructMockFile();

        // 利用可能な最初のソースを自動選択
        $this->activeSource = $this->selectFirstAvailableSource();
        \Illuminate\Support\Facades\Log::info('FileInspector: Mock data loaded', [
            'id' => $this->file->id,
            'filename' => $this->file->filename,
            'active_source' => $this->activeSource,
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

    /**
     * サムネイルの存在を確認し、なければ生成ジョブをディスパッチ
     */
    private function ensureThumbnailExists(): void
    {
        if (! $this->file || $this->isMockFile()) {
            return;
        }

        // 画像ファイルでない場合はスキップ
        if (! str_starts_with($this->file->original_mime_type ?? $this->file->mime, 'image/')) {
            return;
        }

        // サムネイルパスをチェック
        if (! $this->file->hashed_filename) {
            return;
        }

        $thumbnailPath = \App\Helpers\AttachedFilePathHelper::getThumbnailStoragePath(
            $this->file->hashed_filename,
            $this->file->tenant_id
        );

        // サムネイルが存在しない場合は生成ジョブをディスパッチ
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($thumbnailPath)) {
            \App\Jobs\Ledger\GenerateThumbnail::dispatch($this->file->id);
            \Illuminate\Support\Facades\Log::info('FileInspector: Dispatched thumbnail generation job', [
                'file_id' => $this->file->id,
                'filename' => $this->file->filename,
            ]);
        }
    }

    /**
     * ソースを切り替える（ローディングUIを表示するため専用メソッド）
     */
    public function switchSource(string $source): void
    {
        $this->activeSource = $source;
        $this->isExpanded = false; // ソース切り替え時は展開状態をリセット

        // Alpine.jsの状態をリセットするイベントを発行
        $this->dispatch('source-switched');
    }

    /**
     * 利用可能な最初のソースを選択する
     */
    private function selectFirstAvailableSource(): string
    {
        // 優先順位: vlm -> ocr -> tika -> structured
        $priority = ['vlm', 'ocr', 'tika', 'structured'];

        foreach ($priority as $source) {
            $status = $this->getSourceStatus($source);
            if ($status === 'completed') {
                return $source;
            }
        }

        // どれもない場合はtikaをデフォルトとする
        return 'tika';
    }

    public function getPreviewText(bool $withHighlight = true): ?string
    {
        if (! $this->file) {
            return null;
        }

        // 1. テキスト取得
        if ($this->isMockFile()) {
            // モックデータの場合はソース固有のテキストがあれば優先
            $baseText = $this->mockData['mock_preview_text'] ?? '';
            $text = match ($this->activeSource) {
                'vlm' => $this->mockData['mock_vlm_text'] ?? ("【AI解析結果】\n".$baseText."\n\n※この内容はVLMによって生成された要約です。"),
                'ocr' => $this->mockData['mock_ocr_text'] ?? ("【文字認識結果】\n".$baseText),
                'tika' => $this->mockData['mock_tika_text'] ?? ("【システム抽出結果】\n".$baseText),
                'structured' => isset($this->mockData['mock_vlm_structured_data'])
                    ? json_encode($this->mockData['mock_vlm_structured_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : null,
                default => $baseText,
            };
        } else {
            $text = match ($this->activeSource) {
                'vlm' => $this->file->vlm_markdown,
                'ocr', 'tika' => $this->file->getOcrTikaFormattedText($this->activeSource),
                'structured' => $this->file->vlm_structured_data
                    ? json_encode($this->file->vlm_structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : null,
                default => null,
            };
        }

        if (! $text) {
            return null;
        }

        // 2. 段階的ロード（大規模テキスト対応）: とりあえず先頭10,000文字で制限
        $limit = 10000;
        $isTruncated = ! $this->isExpanded && mb_strlen($text) > $limit;
        if ($isTruncated && $withHighlight) {
            $text = mb_substr($text, 0, $limit)."\n\n... (テキストが長いため省略されました。全表示ボタンで確認できます) ...";
        }

        // 3. ハイライト処理 (HTML出力用のみ)
        if ($withHighlight && ! empty($this->searchKeyword)) {
            $keywords = SearchHelper::extractKeywords($this->searchKeyword);

            if (! empty($keywords)) {
                // vlm (Markdown) と structured (JSON) の場合はエスケープせず、それ以外はエスケープしてハイライト
                $shouldEscape = ! in_array($this->activeSource, ['vlm', 'structured']);
                $text = SearchHelper::highlight($text, $keywords, 'bg-yellow-200 text-black px-0.5 rounded', $shouldEscape);
            }
        }

        return $text;
    }

    /**
     * 各抽出ソースの状態を取得する（UI用）
     *
     * @return string available|processing|missing|error
     */
    public function getSourceStatus(string $source): string
    {
        if (! $this->file) {
            return 'missing';
        }

        // モックデータの場合
        if ($this->isMockFile()) {
            if (empty($this->mockData)) {
                return 'missing';
            }

            return match ($source) {
                'vlm' => $this->mockData['mock_vlm_status'] ?? (($this->mockData['mock_vlm_text'] ?? null) ? 'completed' : 'missing'),
                'ocr' => $this->mockData['mock_ocr_status'] ?? (($this->mockData['mock_ocr_text'] ?? null) ? 'completed' : 'missing'),
                'tika' => $this->mockData['mock_tika_status'] ?? (($this->mockData['mock_tika_text'] ?? null) ? 'completed' : 'missing'),
                'structured' => ! empty($this->mockData['mock_vlm_structured_data']) ? 'completed' : 'missing',
                default => 'missing',
            };
        }

        // 本番データの場合
        return match ($source) {
            'vlm' => $this->getVlmStatus(),
            'ocr' => $this->getOcrStatus(),
            'tika' => $this->getTikaStatus(),
            'structured' => ! empty($this->file->vlm_structured_data) ? 'completed' : 'missing',
            default => 'missing',
        };
    }

    /**
     * VLMソースの状態を詳細に判定
     */
    private function getVlmStatus(): string
    {
        // VLMテキストが存在するか確認（空文字列やnullでない）
        if (! empty($this->file->vlm_markdown)) {
            return 'completed';
        }

        // VLM処理時間が記録されていれば処理は実行されたが結果がない = missing
        if ($this->file->vlm_processing_time_ms !== null) {
            return 'missing';
        }

        // 処理確定日時があり、VLM信頼度がnullなら処理中または未処理
        if ($this->file->processing_finalized_at) {
            // 最終化済みでVLMがないなら、VLM処理はスキップされた = missing
            return 'missing';
        }

        // vlm_confidence が null = まだ処理されていない = processing
        if ($this->file->vlm_confidence === null && ! $this->file->processing_finalized_at) {
            return 'processing';
        }

        // その他の場合はmissing
        return 'missing';
    }

    /**
     * OCRソースの状態を判定
     */
    private function getOcrStatus(): string
    {
        // OCR処理日時があり、実際にテキストが抽出されているか確認
        if ($this->file->ocr_processed_at) {
            $ocrText = $this->file->getOcrTikaFormattedText('ocr');

            return ! empty($ocrText) ? 'completed' : 'missing';
        }

        // 処理確定済みだがOCR日時がない場合はmissing
        if ($this->file->processing_finalized_at) {
            return 'missing';
        }

        return 'processing';
    }

    /**
     * Tikaソースの状態を判定
     */
    private function getTikaStatus(): string
    {
        // Tika処理日時があり、実際にテキストが抽出されているか確認
        if ($this->file->tika_processed_at) {
            $tikaText = $this->file->getOcrTikaFormattedText('tika');

            return ! empty($tikaText) ? 'completed' : 'missing';
        }

        // 処理確定済みだがTika日時がない場合はmissing
        if ($this->file->processing_finalized_at) {
            return 'missing';
        }

        // Tikaは基本的に常に処理されるはずだが、まだの場合はprocessing
        return 'processing';
    }

    /**
     * 現在表示中のテキストに検索キーワードが含まれているか
     */
    #[\Livewire\Attributes\Computed]
    public function hasKeywordHit(): bool
    {
        if (empty($this->searchKeyword)) {
            return false;
        }

        // ハイライトなし（生テキスト）を取得してチェック
        $text = $this->getPreviewText(false);
        if (! $text) {
            return false;
        }

        $keywords = SearchHelper::extractKeywords($this->searchKeyword);

        return SearchHelper::hasHit($text, $keywords);
    }

    public function toggleExpand(): void
    {
        $this->isExpanded = ! $this->isExpanded;
    }

    /**
     * プレビューテキストを取得（computed property）
     */
    #[\Livewire\Attributes\Computed]
    public function previewText(): ?string
    {
        return $this->getPreviewText(true);
    }

    /**
     * テキストが長すぎて展開ボタンが必要か判定
     */
    #[\Livewire\Attributes\Computed]
    public function canExpand(): bool
    {
        $plainText = $this->getPreviewText(false);

        return $plainText && mb_strlen($plainText) > 10000;
    }

    /**
     * ファイルがプレビュー可能か判定
     */
    #[\Livewire\Attributes\Computed]
    public function showPreview(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    /**
     * ファイルが画像か判定
     */
    #[\Livewire\Attributes\Computed]
    public function isImage(): bool
    {
        if (! $this->file) {
            return false;
        }
        $mime = $this->file->original_mime_type ?? $this->file->mime ?? '';

        return str_starts_with($mime, 'image/');
    }

    /**
     * ファイルがPDFか判定
     */
    #[\Livewire\Attributes\Computed]
    public function isPdf(): bool
    {
        if (! $this->file) {
            return false;
        }
        $mime = $this->file->original_mime_type ?? $this->file->mime ?? '';

        return $mime === 'application/pdf';
    }

    /**
     * プレビュー用のURLを取得
     */
    #[\Livewire\Attributes\Computed]
    public function previewUrl(): ?string
    {
        if (! $this->file) {
            return null;
        }

        // プレビュー不可のファイルはnullを返す
        if (! $this->showPreview()) {
            return null;
        }

        // モックデータの場合
        if ($this->isMockFile()) {
            if ($this->isImage()) {
                return 'https://via.placeholder.com/600x400/4CAF50/FFFFFF?text='.
                    urlencode($this->file->original_filename ?? 'Image');
            } elseif ($this->isPdf()) {
                return '#pdf-preview';
            }

            return null;
        }

        // 実ファイルの場合
        if (! $this->file->path) {
            return null;
        }

        // Storage::disk('public')->url() を使用してURLを生成
        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->file->path);
    }

    public function render()
    {
        return \view('livewire.attached-file.file-inspector');
    }
}
