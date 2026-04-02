<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HandlesFormGroups;
use App\Livewire\Traits\HandlesFormInitialization;
use App\Livewire\Traits\HandlesPrefillLinks;
use App\Livewire\Traits\HandlesValidationUX;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Policies\LedgerDefinePolicy;
use App\Services\LedgerService;
use App\Services\NumberingService;
use App\Services\WorkflowService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Throwable;

/**
 * @method syncInput(string $name, array|mixed[] $files)
 */
class CreateColumn extends BaseLivewireComponent
{
    use HandlesFormGroups, HandlesFormInitialization, HandlesPrefillLinks, HandlesValidationUX;
    use InitializesTenantContext, Toast, WithFileUploads;

    public array $content = []; // 初期値を空配列に

    public array $prefillParams = [];

    public array $filePondInitialFiles = [];

    // labelColor, showPrefillModal, generatedPrefillURL は trait へ移動

    public mixed $ledgerDefineRecord;

    public int $ledgerDefineId;

    public ?int $ledgerId = null;

    public ?Ledger $ledgerRecord = null;

    public array $contentAttached = [];

    private array $newAttachedFiles = [];

    public $backgroundImages = [];

    public $progress = 0;

    public $requredColumnIds = [];

    // --- 推奨担当者IDを保持する一時プロパティ ---
    public ?int $initialInspectorId = null;

    // --- コメントモーダル制御用 (新規追加) ---
    public bool $showInspectionCommentModal = false; // 点検依頼用コメントモーダル

    public string $inspectionComment = '';         // 点検依頼コメント

    public ?int $tempSelectedInspectorId = null;  // 担当者選択モーダルで一時的に保持するID

    /**
     * @var mixed|null
     */
    public $totalRequireColumnCount = 0;

    // validationErrors, errorsByGroup, errorsByField, previousErrorCount, fixedFields は trait へ移動

    // --- 担当者選択モーダル制御用 ---
    public bool $showAssigneeModal = false;

    public string $assigneeModalRoleType = 'inspector'; // モーダルに渡す roleType
    //    public ?int $assigneeModalSelectedUserId = null; // モーダルで選択されたIDを一時保持 (任意)
    // --------------------------------

    // --- selectedUserId はモーダルで選択された結果を受け取る ---
    // 親コンポーネント側で選択状態を保持する必要がなくなる場合もある
    // public ?int $selectedUserId = null; // ← モーダルから受け取るので不要になるかも
    // selectedInspectorId は WorkflowAssigneeSelect とバインドするため維持する (初期値 null)

    protected WorkflowService $workflowService; // WorkflowService をインジェクト

    protected NumberingService $numberingService; // NumberingService をインジェクト

    protected LedgerService $ledgerService; // LedgerService をインジェクト

    /**
     * Bladeの wire:submit="store" から呼び出される
     */
    public function store(): void
    {
        $this->saveDirectly();
    }

    // WorkflowService をインジェクト
    public function boot(
        WorkflowService $workflowService,
        NumberingService $numberingService,
        LedgerService $ledgerService
    ): void {
        $this->workflowService = $workflowService;
        $this->numberingService = $numberingService;
        $this->ledgerService = $ledgerService;
    }

    // mount は Create と Modify で異なるので、各クラスで実装 or 親で共通化
    public function mount(int $ledgerDefineId, LedgerDefinePolicy $ledgerDefinePolicy, array $prefillParams = []): void
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->prefillParams = $prefillParams;
        $this->ledgerDefineRecord = LedgerDefine::findOrFail($this->ledgerDefineId);

        // 権限チェック: ユーザーがこの台帳を作成できるか確認
        if (! $ledgerDefinePolicy->ledgerCreate(Auth::user(), $this->ledgerDefineRecord)) {
            throw new ModelNotFoundException(
                'Ledger definition not found or user does not have permission to create.'
            );
        }

        // tenantIdを設定
        $this->tenantId = $this->ledgerDefineRecord->tenant_id;

        $this->initColumns();
        $this->initBackgroundImages();
        $this->initRequireColumns();
        $this->initializeDateDefaults();
        $this->applyPrefillParams();
        $this->updateProgress();
        $this->loadRecommendedPersonnel();
        $this->initializeGroups();
    }

    public function render(): View
    {
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if (! $column->isHidden()) {
                $this->updateContentStatusLabel($column);
            }
        }

        return view('livewire.ledger.create-column', [
            'groupedColumns' => $this->getGroupedColumns(),
        ]);
    }

    protected function getGroupedColumns(): \Illuminate\Support\Collection
    {
        return collect($this->ledgerDefineRecord->column_define)
            ->reject(fn ($column) => $column->isHidden())
            ->groupBy(fn ($column) => $column->group ?? __('ledger.form.group_default'))
            ->sortBy(fn ($columnsInGroup) => $columnsInGroup->first()->order ?? PHP_INT_MAX);
    }

    public function updated($propertyName): void
    {
        $propertyPath = explode('.', $propertyName);
        if (count($propertyPath) < 2) {
            return;
        }
        $columnId = (int) $propertyPath[1];
        $column = $this->getColumnById($columnId);

        if (! $column) {
            return;
        }

        try {
            // 前の状態を確認 (Issue #24)
            $wasInvalid = isset($this->errorsByField[$propertyName]);

            $this->validateOnly($propertyName);

            // 成功した場合: 前がエラーなら「修正成功」としてマーク
            if ($wasInvalid) {
                $this->fixedFields[$propertyName] = true;
                $this->dispatch('field-fixed', field: $propertyName);
            } else {
                // エラーがなく、もともと成功していた場合はマークを外す（再入力時など）
                unset($this->fixedFields[$propertyName]);
            }

            $this->updateProgress();
            $this->updateContentStatusLabel($column, true);

            // バリデーション成功時: エラーバッグから該当フィールドが消えているはず
            $this->updateValidationState();
        } catch (ValidationException $e) {
            // エラーが発生した場合は成功マークを外す
            unset($this->fixedFields[$propertyName]);

            // 状態を更新 (Issue #25 改良: エラー発生時は例外から取得した最新エラーを反映)
            $this->updateValidationState($e->errors());

            // テスト環境では例外を投げ直して Livewire の assertHasErrors が機能するようにする
            if (app()->runningUnitTests()) {
                throw $e;
            }

            $this->error(__('ledger.validation.failed'), $e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'error', title: __('ledger.validation.failed'));
            }
            $this->labelColor[$columnId] = 'error';
        }
    }

    public function updateProgress(): void
    {
        if (! isset($this->requredColumnIds)) {
            $this->initRequireColumns();
        }
        $rawCount = collect($this->content)->filter(function ($value, $key) {
            if (! is_array(($value))) {
                $value = trim($value);
            } else {
                $value = array_filter($value, 'strlen');
            }

            return ! empty($value) && in_array($key, $this->requredColumnIds);
        })->count();

        if ($this->totalRequireColumnCount > 0) {
            $this->progress = ($rawCount / $this->totalRequireColumnCount) * 100;
        } else {
            $this->progress = 100; // 必須項目がない場合は100%とする
        }
    }

    /**
     * ワークフロー無効時の直接保存処理 (LedgerDiff 作成を追加)
     */
    public function saveDirectly(): void
    {
        // ワークフローが無効であることを再確認
        if ($this->ledgerDefineRecord?->workflow_enabled) {
            $this->error('Workflow is enabled for this definition.');

            return;
        }

        // バリデーション
        try {
            $contentRules = array_filter(
                $this->rules(),
                fn ($key) => str_starts_with($key, 'content.'),
                ARRAY_FILTER_USE_KEY
            );
            $this->validate($contentRules);
        } catch (ValidationException $e) {
            $this->updateValidationState($e->errors());
            throw $e;
        }
        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            // LedgerService に処理を委譲
            $ledger = $this->ledgerService->saveDirectly(
                $this->ledgerId,
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );

            $this->ledgerId = $ledger->id;
            $this->ledgerRecord = $ledger;

            $message = $this->ledgerId ? __('ledger.updated.success') : __('ledger.stored.success');

            $this->addAttachedFileRecordIfNecessary(); // ファイルレコード追加
            $this->success(
                $message,
                redirectTo: route('ledger.show', [
                    'tenant' => $this->tenantId,
                    'ledgerId' => $this->ledgerId,
                    'refresh' => 'true',
                ])
            );

            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'success', title: $message);
            }
        } catch (Throwable $e) {
            Log::error('Direct save failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));

            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'error', title: __('messages.error.generic'));
            }

            return;
        }
    }

    /**
     * @throws Exception
     */
    public function storeFile(TemporaryUploadedFile $file, $columnId = 0): object
    {
        $hashedBasename = Str::random(40).'.'.$file->getClientOriginalExtension();
        $fullPath = AttachedFilePathHelper::getAttachmentPath($this->ledgerDefineId, $hashedBasename);

        Storage::disk('public')->put($fullPath, file_get_contents($file->getRealPath()));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $storedFilePath = Storage::disk('public')->path($fullPath);
        $mimeType = finfo_file($finfo, $storedFilePath);
        finfo_close($finfo);
        Log::info('File MIME Type detected by finfo_file: '.$mimeType);
        Log::info('Stored File Path: '.$storedFilePath);
        Log::info('File Real Path (Temporary): '.$file->getRealPath());

        $result = (object) [
            'originalName' => $file->getClientOriginalName(),
            'hashedBaseName' => $hashedBasename,
            'hashedName' => $fullPath,
            //            'meta' => null,
        ];

        $this->newAttachedFiles[] = [
            'filename' => $file->getClientOriginalName(),
            'hashedbasename' => $hashedBasename,
            'path' => $fullPath,
            'mime' => $mimeType,
            'size' => Storage::disk('public')->size($fullPath),
            //            'file_type' => $result->meta->mime ?? $file->getClientMimeType(),
            'status' => AttachedFileStatus::UPLOADED->value,
            //            'contain_content' => ! empty($result->meta->content),
            'contain_content' => false,
            'optimized' => false,
            'column_id' => $columnId,
        ];

        return $result;
    }

    /**
     * 断続的にファイルアップロードした際に以前のアップロードとマージする
     * https://github.com/livewire/livewire/issues/1230
     */
    public function finishUpload(string $name, string $tmpPath, $isMultiple): void
    {
        $files = collect($tmpPath)->map(fn ($i) => TemporaryUploadedFile::createFromLivewire($i))->toArray();
        $this->dispatch('upload:finished', $name, collect($files)->map->getFilename()->toArray())->self();

        //        $files = array_merge($this->getPropertyValue($name), $files);
        $presentValue = $this->getPropertyValue($name);
        if (! empty($presentValue)) {
            $files = array_merge($presentValue, $files);
        }

        $this->syncInput($name, $files);
    }

    public function addAttachedFileRecord(): void
    {
        if (empty($this->newAttachedFiles)) {
            return;
        }
        foreach ($this->newAttachedFiles as $newAttachedFile) {
            Log::info('[CreateColumn@addAttachedFileRecord] newAttachedFile before create:', $newAttachedFile);
            $newAttachedFile = AttachedFile::create(array_merge($newAttachedFile, [
                'ledger_id' => $this->ledgerRecord->id,
                'ledger_define_id' => $this->ledgerDefineRecord->id,
                'creator_id' => Auth::id(),
                'modifier_id' => Auth::id(),
            ]));
            Bus::batch([
                new ProcessAttachedFile($newAttachedFile),
            ])->dispatch();
        }
    }

    /**
     * バリデーションルールを取得します。
     */
    protected function rules(): array
    {
        return $this->ledgerDefineRecord?->getValidationRules($this->ledgerId) ?? [];
    }

    protected function validationAttributes(): array
    {
        return $this->ledgerDefineRecord?->getValidationAttributes() ?? [];
    }

    protected function messages(): array
    {
        return [
            'content.*.in_options' => __('validation.in'),
            'content.*.at_least_one_checked' => __('validation.filled'),
        ];
    }

    // 下書き保存
    public function saveDraft(): void
    {
        try {
            $contentRules = array_filter(
                $this->rules(),
                fn ($key) => str_starts_with($key, 'content.'),
                ARRAY_FILTER_USE_KEY
            );
            $this->validate($contentRules); // content のみバリデーション
        } catch (ValidationException $e) {
            $this->updateValidationState();
            throw $e;
        }
        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            // WorkflowService を呼び出し、戻り値を受け取る
            $result = $this->workflowService->saveDraft(
                $this->ledgerId, // 新規なら null
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );

            // 戻り値から ID とレコードを更新
            $this->ledgerId = $result['ledger']->id;
            $this->ledgerRecord = $result['ledger']; // ledgerRecord も更新

            $this->addAttachedFileRecordIfNecessary();
            $this->success(__('ledger.draft_saved'));

            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'success', title: __('ledger.draft_saved'));
            }
        } catch (\Exception $e) {
            Log::error('Draft save failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));

            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'error', title: __('messages.error.generic'));
            }
        }
    }

    // --- 下書き保存メソッド (requestInspection から呼ばれる内部用) ---
    protected function saveDraftInternal(): void
    {
        $this->processFilesForSave(); // ファイル処理は先に行う

        // バリデーションは requestInspection で実施済みなので不要
        $userId = Auth::id();
        $this->processFilesForSave();

        try {
            $result = $this->workflowService->saveDraft(
                $this->ledgerId, // null のはず
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );
            $this->ledgerId = $result['ledger']->id;
            $this->ledgerRecord = $result['ledger'];
            $this->addAttachedFileRecordIfNecessary(); // ファイルレコード追加は呼び出し元で行う
        } catch (\Exception $e) {
            Log::error('Draft save internal failed: '.$e->getMessage());
            // エラーは呼び出し元に伝播させる
            throw $e;
        }
    }

    /**
     * ファイル処理の共通化 (store から移動)
     */
    protected function processFilesForSave(): void
    {
        $this->newAttachedFiles = []; // 初期化
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type === 'files') {
                $storedFiles = [];
                // content 内のアップロード済みファイルを取得 (TemporaryUploadedFile)
                $uploadedFiles = $this->content[$column->id] ?? [];
                // array であることを確認
                if (! is_array($uploadedFiles)) {
                    $uploadedFiles = [];
                }

                $validUploads = array_filter($uploadedFiles, fn ($file) => $file instanceof TemporaryUploadedFile);

                foreach ($validUploads as $uploadedFile) {
                    $stored = $this->storeFile($uploadedFile, $column->id);
                    if ($stored) {
                        $storedFiles[] = $stored;
                    }
                }

                // ModifyColumn の場合、既存ファイルとのマージ処理が必要
                if ($this instanceof ModifyColumn) {
                    $this->mergeFilesForSave($column, $storedFiles); // ModifyColumn の mergeFilesForSave を呼び出す
                } else { // CreateColumn の場合
                    $this->mergeFilesForSave($column, $storedFiles);
                }
            }
        }
        // Normalize は Service 側で行うか、ここで実行
        $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
        $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);
    }

    // 点検者の選択肢を取得
    public function getInspectorOptions(): array
    {
        $options = [];
        // 推奨ユーザー
        if ($this->ledgerDefineRecord?->recommendedInspector) {
            $inspector = $this->ledgerDefineRecord->recommendedInspector;
            // 配列形式に 'id' と 'name' を含める
            $options[$inspector->id] = [
                'id' => $inspector->id,
                'name' => $inspector->name.' ('.__('ledger.workflow.recommended_user').')',
            ];
        }
        // 推奨ロール
        if ($this->ledgerDefineRecord?->recommendedInspectorRole) {
            // ロールからユーザーを取得
            $roleUsers = $this->ledgerDefineRecord->recommendedInspectorRole->users()->orderBy('name')->get();
            foreach ($roleUsers as $user) {
                // 重複を避ける
                if (! isset($options[$user->id])) {
                    $options[$user->id] = [
                        'id' => $user->id,
                        'name' => $user->name.' ('.__('ledger.workflow.recommended_role').')',
                    ];
                }
            }
        }
        // その他の全ユーザー (重複を除く)
        $allUsers = User::orderBy('name')->get();
        foreach ($allUsers as $user) {
            if (! isset($options[$user->id])) {
                $options[$user->id] = ['id' => $user->id, 'name' => $user->name];
            }
        }

        // MaryUI Select の options 形式 (id と name を持つ配列) に変換
        return array_values($options); // キーをリセットして配列として返す
    }

    // ファイルマージ（Create用デフォルト）
    protected function mergeFilesForSave(object $column, array $storedFiles): void
    {
        $filenames = [];
        $fileContents = [];
        foreach ($storedFiles as $stored) {
            $filenames[$stored->hashedBaseName] = $stored->originalName;
            $fileContents[$stored->hashedBaseName] = null;
        }
        $this->content[$column->id] = $filenames;
        $this->contentAttached[$column->id] = $fileContents;
    }

    // ファイルレコード追加（共通化）
    protected function addAttachedFileRecordIfNecessary(): void
    {
        if ($this->ledgerId && ! empty($this->newAttachedFiles)) {
            $this->addAttachedFileRecord(); // 既存メソッド呼び出し
        }
    }

    // --- loadRecommendedPersonnel メソッドで selectedUserId に初期値をセット ---
    protected function loadRecommendedPersonnel(): void
    {
        // 外部から推奨者情報を読み込むロジックをここに記述（必要に応じて）
    }

    // --- 担当者選択モーダルを開くメソッド (実績ベースで初期値を決定) ---
    public function openAssigneeModal(string $roleType): void
    {
        if (is_null($this->ledgerId)) {
            Log::error('Cannot open assignee modal without a saved ledger.');
            $this->error(__('ledger.workflow.save_first_before_assigning'));

            return;
        }

        $this->assigneeModalRoleType = $roleType;

        // --- 実績ベースで初期選択ユーザーIDを決定 ---
        $initialUserId = null;
        if ($roleType === 'inspector') {
            // WorkflowAssigneeSelect のロジックを一部流用して最も頻度の高いユーザーを取得
            // (本来は Service/Repository に切り出すべきロジック)
            $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerDefineId, 'inspector', 1);
            if (! empty($frequentUsers)) {
                $initialUserId = $frequentUsers[0]['id'];
            }
        }
        // TODO: 承認者用の初期値決定ロジック
        // ------------------------------------

        $this->resetValidation();
        $this->showAssigneeModal = true;

        $this->dispatch(
            'open-assignee-modal',
            ledgerDefineId: $this->ledgerDefineId,
            folderId: $this->ledgerDefineRecord->folder_id,
            roleType: $roleType,
            ledgerId: $this->ledgerId, // <<<--- 必ず値が入っているはず
            initialUserId: $initialUserId // <<<--- 実績ベースの初期選択ID
        );
    }

    // --- モーダルから担当者が選択されたときのイベントリスナー ---
    #[On('assignee-selected')]
    public function handleAssigneeSelected(int $userId, string $roleType): void
    {
        //        dd("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");
        // Log::debug("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");
        // ここで $userId を使って WorkflowService のメソッドを呼び出す
        if ($roleType === 'inspector') {
            //            $this->requestInspectionInternal($userId); // 内部メソッド呼び出し
            $this->tempSelectedInspectorId = $userId; // 一時的にIDを保持
            // ★ 次にコメント入力モーダルを開く
            $this->openInspectionCommentModal();
        }
        // モーダルは子コンポーネント側で閉じられる想定
        $this->showAssigneeModal = false; // 念のため親でも閉じる
    }

    // --- 点検依頼コメント入力モーダルを開くメソッド (新規追加) ---
    public function openInspectionCommentModal(): void
    {
        if (is_null($this->tempSelectedInspectorId) || is_null($this->ledgerId)) {
            Log::error('Cannot open inspection comment modal: Inspector ID or Ledger ID is missing.');
            $this->error(__('messages.error.generic'));

            return;
        }
        $this->inspectionComment = ''; // コメントをリセット
        $this->resetValidation('inspectionComment');
        // $this->showInspectionCommentModal = true; // WorkflowCommentModal を直接制御しない

        // WorkflowCommentModal を開くイベントを発行
        $this->dispatch(
            'open-workflow-comment-modal',
            title: __('ledger.workflow.request_inspection_comment_title'), // 新しい翻訳キー
            actionLabel: __('ledger.workflow.send_inspection_request'),    // 新しい翻訳キー
            actionClass: 'btn-primary',
            actionType: 'request_inspection_with_comment', // 新しいアクションタイプ
            ledgerId: $this->ledgerId,
            initialComment: '' // 初期コメントはなし
        );
    }

    // --- コメントモーダルからコメント付きで実行するイベントリスナー (新規追加) ---
    #[On('workflow-action-with-comment')]
    public function handleRequestInspectionWithComment(string $actionType, int $ledgerId, ?string $comment): void
    {
        //        dd($actionType,$ledgerId,$comment);
        if ($actionType !== 'request_inspection_with_comment'
            || $ledgerId !== $this->ledgerId || is_null($this->tempSelectedInspectorId)
            // Ledger ID が一致しない、または担当者IDが未設定の場合は処理しない
        ) {
            Log::error(
                "Invalid request_inspection_with_comment action: ActionType: {$actionType}, ".
                "Ledger ID: {$ledgerId}, Selected Inspector ID: {$this->tempSelectedInspectorId}"
            );

            return; // 対象外のアクションや情報不足
        }
        // 担当者IDとコメントを使って点検依頼処理を実行
        if (is_null($this->ledgerId)) {
            Log::error('Cannot request inspection with comment: Ledger ID is missing after draft save.');
            $this->error(__('ledger.workflow.save_first_before_assigning')); // 保存されていないエラーメッセージ
            $this->tempSelectedInspectorId = null; // 担当者IDをリセット
            $this->inspectionComment = '';       // リセット

            return;
        }

        $selectedAssigneeId = $this->tempSelectedInspectorId;
        $this->inspectionComment = $comment ?? ''; // モーダルから受け取ったコメント

        // Content のバリデーションは requestInspection ですでに完了している
        // 担当者IDのバリデーション
        if (! User::find($selectedAssigneeId)) {
            $this->error(__('ledger.workflow.invalid_assignee'));
            $this->tempSelectedInspectorId = null; // 担当者IDをリセット

            return;
        }

        $requesterId = Auth::id();

        try {
            // WorkflowService に担当者IDとコメントを渡す
            // requestInspection メソッドにコメント引数を追加する必要がある
            $result = $this->workflowService->requestInspection(
                $this->ledgerId,
                $requesterId,
                $selectedAssigneeId,
                $this->inspectionComment
                // ★ コメントを渡す
            );

            $this->addAttachedFileRecordIfNecessary(); // これは saveDraft 内で呼ばれるべきか？
            $this->success(
                __('ledger.workflow.inspection_requested_message'),
                redirectTo: route('ledger.show', [
                    'tenant' => $this->tenantId,
                    'ledgerId' => $this->ledgerId,
                ])
            );

            if (app()->runningUnitTests()) {
                $this->dispatch(
                    'mary-toast',
                    type: 'success',
                    title: __('ledger.workflow.inspection_requested_message')
                );
            }
        } catch (\Exception $e) {
            Log::error('Inspection request with comment failed: '.$e->getMessage());
            $this->error(__('ledger.workflow.inspection_request_failed')); // 点検依頼失敗のエラーメッセージ

            if (app()->runningUnitTests()) {
                $this->dispatch('mary-toast', type: 'error', title: __('ledger.workflow.inspection_request_failed'));
            }
        } finally {
            $this->tempSelectedInspectorId = null; // 使用後リセット
            $this->inspectionComment = '';       // 使用後リセット
            // $this->showInspectionCommentModal = false; // WorkflowCommentModal側で閉じる
        }
    }

    // --- 点検依頼ボタンのアクション (下書き保存 -> 担当者選択モーダル表示) ---
    public function requestInspection(): void
    {
        // 1. Content のバリデーション
        try {
            $contentRules = array_filter(
                $this->rules(),
                fn ($key) => str_starts_with($key, 'content.'),
                ARRAY_FILTER_USE_KEY
            );
            $this->validate($contentRules);
        } catch (ValidationException $e) {
            $this->updateValidationState();
            throw $e;
        }

        // 2. 下書き保存を実行 (ファイル処理含む)
        try {
            $userId = Auth::id();
            $this->processFilesForSave();
            $result = $this->workflowService->saveDraft(
                $this->ledgerId,
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );
            $this->ledgerId = $result['ledger']->id;
            $this->ledgerRecord = $result['ledger'];
            $this->addAttachedFileRecordIfNecessary();
            Log::info("Draft saved successfully before opening assignee modal. Ledger ID: {$this->ledgerId}");

            // 3. 下書き保存成功後に担当者選択モーダルを開く
            $this->openAssigneeModal('inspector');
        } catch (\Exception $e) {
            Log::error('Draft save failed before inspection request: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    protected function getInitialApproverId(): ?int
    {
        // TODO: 承認者の推奨ロジック
        return null;
    }

    /**
     * ★ このメソッドをクラスの末尾などに追加してください
     *
     * FilePond で新規アップロードファイルが削除されたときにフロントエンドから呼び出される
     *
     * @param  int  $columnId  削除されたファイルが属するカラムのID
     */
    public function handleNewFileRemoval(int $columnId): void
    {
        // このメソッドが呼ばれる時点で、Livewireの`removeUpload`によって
        // `content`プロパティから該当ファイルは削除されています。
        // そのため、現在の`content`プロパティを元にUIを更新します。

        $column = collect($this->ledgerDefineRecord->column_define)->firstWhere('id', $columnId);
        if ($column) {
            // ラベルの色を強制的に再評価・更新します
            $this->updateContentStatusLabel($column, true);
            // プログレスバーも更新します
            $this->updateProgress();
        }
    }
}
