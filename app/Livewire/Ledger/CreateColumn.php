<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Rules\UniqueAutoNumber;
use App\Rules\UniqueColumnValue;
use App\Services\NumberingService;
use App\Services\WorkflowService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * @method syncInput(string $name, array|mixed[] $files)
 */
class CreateColumn extends Component
{
    use Toast, WithFileUploads;

    public array $content = []; // 初期値を空配列に

    public array $labelColor = [];

    public mixed $ledgerDefineRecord;

    public int $ledgerDefineId;

    public ?int $ledgerId = null;

    public ?Ledger $ledgerRecord = null;

    private array $contentAttached = [];

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

    // WorkflowService をインジェクト
    public function boot(WorkflowService $workflowService, NumberingService $numberingService): void
    {
        $this->workflowService = $workflowService;
        $this->numberingService = $numberingService;
    }

    // mount は Create と Modify で異なるので、各クラスで実装 or 親で共通化
    public function mount(int $ledgerDefineId): void
    {
        // Create 用の mount ロジック
        $this->ledgerDefineId = $ledgerDefineId;
        $this->ledgerDefineRecord = LedgerDefine::findOrFail($this->ledgerDefineId);
        $this->initColumns(); // メソッド名を変更
        $this->initBackgroundImages();
        $this->initRequireColumns();
        $this->updateProgress(); // 初期進捗を計算
        $this->loadRecommendedPersonnel(); // 推奨担当者を読み込む
    }

    // カラム初期化処理 (Create / Modify 共通化)
    protected function initColumns(): void
    {
        foreach ($this->ledgerDefineRecord->column_define ?? [] as $column) {
            $defaultValue = match ($column->type) {
                'files', 'chk' => [],
                'auto_number' => $this->numberingService->getNextNumber($column, $this->ledgerDefineId),
                default => '',
            };
            // content がまだセットされていない場合のみデフォルト値を設定
            if (! isset($this->content[$column->id])) {
                $this->content[$column->id] = $this->ledgerRecord?->content[$column->id] ?? $defaultValue;
            }

            // labelColor の初期設定
            if (! isset($this->labelColor[$column->id])) {
                $currentValue = $this->content[$column->id] ?? $defaultValue;
                if (! empty($currentValue)) {
                    $this->labelColor[$column->id] = 'success';
                } elseif ($column->required) {
                    $this->labelColor[$column->id] = 'warning';
                } else {
                    $this->labelColor[$column->id] = 'muted';
                }
            }
        }
        // DBからの復元時に存在しないキーを埋める (Modify用)
        if ($this->ledgerRecord ?? $this->content) {
            $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
        }
    }

    public function initRequireColumns(): void
    {
        $columns = collect($this->ledgerDefineRecord->column_define);
        $this->totalRequireColumnCount = $columns->filter(function ($column) {
            return $column->required;
        })->count();

        $this->requredColumnIds = $columns->filter(function ($column) {
            return $column->required;
        })->pluck('id')->toArray();
    }

    public function initBackgroundImages(): void
    {
        $this->backgroundImages = collect($this->ledgerDefineRecord->column_define)->pluck('file', 'id')
            ->map(function ($value) {
                if (empty($value->path)) {
                    return null;
                }

                return asset('storage/'.$value->path);
            })->toArray();
        //        dd($this->columnFile,$backgroundImages);
        //        $this->dispatch('applyBackgroundImages', $this->backgroundImages);
    }

    public function render(): View
    {
        return view('livewire.ledger.create-column');
    }

    public function updated($propertyName): void
    {
        $propertyPath = explode('.', $propertyName);
        //        dd($propertyName);
        if (count($propertyPath) < 2) {
            return;
        }
        $columnId = $propertyPath[1];

        try {
            $this->validateOnly($propertyName);

            $column = $this->ledgerDefineRecord->column_define[$columnId];
            $this->updateProgress();

            $this->labelColor[$columnId] = 'muted';
            if ($column->required) {
                $this->labelColor[$columnId] = 'warning';
            }
            $tmpColumnValue = $this->content[$columnId];

            if (empty($tmpColumnValue) && $this->getErrorBag()->hasAny($propertyName)) {
                $this->labelColor[$columnId] = 'error';
            } else {
                if (is_array($tmpColumnValue)) {
                    $this->labelColor[$columnId] = (count($tmpColumnValue) > 0) ? 'success' : 'muted';
                } else {
                    $tmpColumnValue = trim($tmpColumnValue);
                    $this->labelColor[$columnId] = ($tmpColumnValue !== '' ? 'success' : 'muted');
                }
            }
        } catch (ValidationException $e) {
            error_log('ValidationException occurred: '.$e->getMessage());
            $this->error(__('ledger.validation.failed'), $e->getMessage());
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

        if ($rawCount > 0) {
            $this->progress = $rawCount / $this->totalRequireColumnCount * 100;
        }
    }

    /**
     * ワークフロー無効時の直接保存処理 (LedgerDiff 作成を追加)
     */
    public function saveDirectly(): void
    {
        // ワークフローが無効であることを再確認
        if ($this->ledgerDefineRecord?->workflow_enabled) { // プロパティ名を修正 isWorkflowEnabled -> workflow_enabled
            $this->error('Workflow is enabled for this definition.');

            return;
        }
        // 承認済みロックチェック (ModifyColumn でオーバーライドされるためここでは不要かも)
        // if ($this->ledgerRecord?->isLocked()) { ... }

        // バリデーション
        $this->validate(array_filter($this->rules(), fn ($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));
        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            // トランザクション開始
            DB::beginTransaction();

            $ledgerData = [
                'ledger_define_id' => $this->ledgerDefineId,
                'content' => $this->content,
                'content_attached' => $this->contentAttached,
                'modifier_id' => $userId,
                'status' => WorkflowStatus::NONE, // <<<--- NONE ステータス
                // version は Ledger::create / update でよしなにされるはず
            ];

            if ($this->ledgerId && $this->ledgerRecord) { // 更新の場合
                // 承認済みチェック (念のため)
                if ($this->ledgerRecord->isLocked()) {
                    throw new \Exception(__('ledger.workflow.cannot_edit_approved'));
                }
                $ledgerData['version'] = $this->ledgerRecord->version + 1;
                $this->ledgerRecord->update($ledgerData);
                $ledger = $this->ledgerRecord->refresh(); // 更新後のデータを再取得
                $message = __('ledger.updated.success');
            } else { // 新規作成の場合
                $ledgerData['creator_id'] = $userId;
                $ledgerData['version'] = 1; // 新規作成時のバージョン
                $ledger = Ledger::create($ledgerData);
                $this->ledgerId = $ledger->id; // ID をセット
                $this->ledgerRecord = $ledger; // レコードをセット
                $message = __('ledger.stored.success');
            }

            // --- LedgerDiff 作成処理を追加 ---
            $columnDefine = $ledger->define->column_define; // Ledger から Define を取得
            $ledgerVersion = $ledger->version;

            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => $ledger->content, // 保存された内容
                'content_attached' => $ledger->content_attached, // 保存された内容
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $userId, // 今回の操作者
                'status' => WorkflowStatus::NONE, // <<<--- NONE ステータス
                'version' => $ledgerVersion,
                // 他のワークフローカラムは NULL
                'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
                'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
                'completed_inspector_role_ids' => [],
                'completed_approver_role_ids' => [],

            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger の latest_diff_id を更新
            $ledger->update(['latest_diff_id' => $ledgerDiff->id]);
            // --- ここまで追加 ---

            DB::commit(); // トランザクション確定

            $this->addAttachedFileRecordIfNecessary(); // ファイルレコード追加はトランザクションの外でも良いかも？
            $this->success($message, redirectTo: route('ledger.show', ['ledgerId' => $this->ledgerId]));
        } catch (Throwable $e) { // Throwable をキャッチ
            DB::rollBack(); // エラー時ロールバック
            Log::error('Direct save failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    /**
     * @throws Exception
     */
    public function storeFile(TemporaryUploadedFile $file, $columnId = 0): object
    {

        $fileHashName = $file->store('public/Ledger/Attachments');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $storedFilePath = Storage::path($fileHashName);
        $mimeType = finfo_file($finfo, $storedFilePath);
        finfo_close($finfo);
        Log::info('File MIME Type detected by finfo_file: ' . $mimeType);
        Log::info('Stored File Path: ' . $storedFilePath);
        Log::info('File Real Path (Temporary): ' . $file->getRealPath());

        $result = (object) [
            'originalName' => $file->getClientOriginalName(),
            'hashedBaseName' => basename($fileHashName),
            'hashedName' => $fileHashName,
            //            'meta' => null,
        ];

        //        画像ファイルの場合はサムネイルを作る
        //        $contentType = $file->getClientMimeType();
        //        $allowedMimeTypes = ['image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/svg+xml'];
        //        if (in_array($contentType, $allowedMimeTypes)) {
        //        dd($file->getClientMimeType(),$file->getRealPath());
        if (Str::endsWith($file->getRealPath(), ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.svg'])) {
            // Create a thumbnail of the image using Intervention Image Library
            $imageManager = new ImageManager;
            $img = Image::make($file->getRealPath());
            $image = $imageManager->make($img)->resize(null, 200, function ($constraint) {
                $constraint->aspectRatio();
            });
            $image->save(storage_path('app/public/Ledger/thumbs/'.basename($fileHashName)));
        }

        //        ファイルからメタ情報、テキストを抽出する
        /*        $tikaClient = Client::make('tika', 9998);
                $result->meta = $tikaClient->getMetadata($file->getRealPath());
                if (empty($result->meta->content)) {
                    $result->meta->content = $tikaClient->getText($file->getRealPath());
                }*/
        //        dd($result);
        //        return $filenames;ß

        $this->newAttachedFiles[] = [
            'filename' => $file->getClientOriginalName(),
            'hashedbasename' => basename($fileHashName),
            'path' => $fileHashName,
            'mime' => $mimeType,
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
        $this->cleanupOldUploads();

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
            $newAttachedFile = AttachedFile::create(array_merge($newAttachedFile, [
                'ledger_id' => $this->ledgerRecord->id,
                'ledger_define_id' => $this->ledgerDefineRecord->id,
                'creator_id' => Auth::user()->id,
                'modifier_id' => Auth::user()->id,
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
        $validationRules = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $columnId = $column->id;
            $columnName = 'content.'.$columnId;
            $columnType = $column->type;

            $rules = [];

            // カラムの種類に基づいた共通のバリデーションルールを追加
            if ($columnType === 'text' || $columnType === 'textarea') {
                $rules[] = 'string';
            } elseif ($columnType === 'number') {
                $rules[] = 'numeric';
                if (isset($column->min)) {
                    $rules[] = 'min:'.$column->min;
                }
                if (isset($column->max)) {
                    $rules[] = 'max:'.$column->max;
                }
                if (isset($column->step)) {
                    // 小数点以下の桁数を考慮した倍数チェック
                    $rules[] = 'multiple_of:'.$column->step;
                }
            } elseif ($columnType === 'YMD') {
                $rules[] = 'date_format:Y-m-d';
            } elseif ($column->type === 'chk' && $column->useOptions && ! empty($column->options)) {
                // チェックボックスのバリデーションルールを定義
                $rules["content.{$column->id}"] = ['in_options', $column->options];

                // 必須項目で少なくとも1つの選択肢をチェックするルールを追加
                if ($column->required) {
                    array_unshift($rules["content.{$column->id}"], 'at_least_one_checked');
                }
            } elseif ($columnType === 'select') {
                $rules[] = Rule::in($column->options);
            }

            // 必要に応じて追加のバリデーションルールを追加
            if ($column->required & $column->type !== 'chk') {
                $rules[] = 'required';
            }

            if ($column->unique) {
                if ($column->type === 'auto_number') {
                    $rules[] = new UniqueAutoNumber($this->ledgerDefineId, $column, $this->ledgerId);
                } else {
                    $rules[] = new UniqueColumnValue($this->ledgerDefineId, $columnId, $this->ledgerId);
                }
            }

            // カラムごとのバリデーションルールを配列に追加
            $validationRules[$columnName] = $rules;
        }

        // selectedUserId のルール
        //        $validationRules['selectedUserId'] = ['nullable', 'integer', 'exists:users,id'];

        return $validationRules;
    }

    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $attributes["content.{$column->id}"] = $column->name;
        }
        // selectedUserId の属性名
        //        $attributes['selectedUserId'] = __('ledger.workflow.next_inspector');

        return $attributes;
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
        $this->validate(array_filter($this->rules(), fn ($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY)); // content のみバリデーション
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
        } catch (\Exception $e) {
            Log::error('Draft save failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

 

    // --- 下書き保存メソッド (requestInspection から呼ばれる内部用) ---
    protected function saveDraftInternal(): void
    {
        $this->processFilesForSave(); // ファイル処理は先に行う

        // バリデーションは requestInspection で実施済みなので不要
        // $this->validate(array_filter($this->rules(), fn($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));
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
            // $this->success(__('ledger.draft_saved')); // 成功メッセージは最終的なアクションで出す
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
                    $this->mergeContentFiles($column, $storedFiles); // 既存メソッド呼び出し
                } else { // CreateColumn の場合
                    $filenames = [];
                    $fileContents = [];
                    foreach ($storedFiles as $stored) {
                        $filenames[$stored->hashedBaseName] = $stored->originalName;
                        $fileContents[$stored->hashedBaseName] = null;
                    }
                    $this->content[$column->id] = $filenames;
                    $this->contentAttached[$column->id] = $fileContents;
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
            $options[$inspector->id] = ['id' => $inspector->id, 'name' => $inspector->name.' ('.__('ledger.workflow.recommended_user').')'];
        }
        // 推奨ロール
        if ($this->ledgerDefineRecord?->recommendedInspectorRole) {
            // ロールからユーザーを取得
            $roleUsers = $this->ledgerDefineRecord->recommendedInspectorRole->users()->orderBy('name')->get();
            foreach ($roleUsers as $user) {
                // 重複を避ける
                if (! isset($options[$user->id])) {
                    $options[$user->id] = ['id' => $user->id, 'name' => $user->name.' ('.__('ledger.workflow.recommended_role').')'];
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
                Log::debug("Initial inspector ID based on frequency: {$initialUserId}");
            } else {
                Log::debug('No frequent inspector found.');
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
        Log::debug("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");
        // ここで $userId を使って WorkflowService のメソッドを呼び出す
        if ($roleType === 'inspector') {
//            $this->requestInspectionInternal($userId); // 内部メソッド呼び出し
            $this->tempSelectedInspectorId = $userId; // 一時的にIDを保持
            // ★ 次にコメント入力モーダルを開く
            $this->openInspectionCommentModal();
        } elseif ($roleType === 'approver') {
            // TODO: 承認申請処理 (Show.php や PendingList.php で実装)
            // $this->requestApprovalInternal($userId);
        }
        // モーダルは子コンポーネント側で閉じられる想定
        $this->showAssigneeModal = false; // 念のため親でも閉じる
    }
    // --- 点検依頼コメント入力モーダルを開くメソッド (新規追加) ---
    public function openInspectionCommentModal(): void
    {
        if (is_null($this->tempSelectedInspectorId) || is_null($this->ledgerId)) {
            Log::error("Cannot open inspection comment modal: Inspector ID or Ledger ID is missing.");
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
//            dd("Invalid request_inspection_with_comment action: ActionType: {$actionType}, Ledger ID: {$ledgerId}, Selected Inspector ID: {$this->tempSelectedInspectorId}");
            Log::error("Invalid request_inspection_with_comment action: ActionType: {$actionType}, Ledger ID: {$ledgerId}, Selected Inspector ID: {$this->tempSelectedInspectorId}");
            return; // 対象外のアクションや情報不足
        }
        // 担当者IDとコメントを使って点検依頼処理を実行
        if (is_null($this->ledgerId)) {
            Log::error("Cannot request inspection with comment: Ledger ID is missing after draft save.");
            $this->error(__('ledger.workflow.save_first_before_assigning')); // 保存されていないエラーメッセージ
            $this->tempSelectedInspectorId = null; // 担当者IDをリセット
            $this->inspectionComment = '';       // リセット
            return;
        }

        $selectedAssigneeId = $this->tempSelectedInspectorId;
        $this->inspectionComment = $comment ?? ''; // モーダルから受け取ったコメント

        // Content のバリデーションは requestInspection ですでに完了している
        // 担当者IDのバリデーション
        if (!User::find($selectedAssigneeId)) {
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
                redirectTo: route('ledger.show', ['ledgerId' => $this->ledgerId])
            );
        } catch (\Exception $e) {
            Log::error('Inspection request with comment failed: ' . $e->getMessage());
            $this->error(__('ledger.workflow.inspection_request_failed')); // 点検依頼失敗のエラーメッセージ
        } finally {
            $this->tempSelectedInspectorId = null; // 使用後リセット
            $this->inspectionComment = '';       // 使用後リセット
            // $this->showInspectionCommentModal = false; // WorkflowCommentModal側で閉じる
        }
    }

    // --- 点検依頼の実行ロジック (内部メソッド化) ---
/*    protected function requestInspectionInternal(int $assigneeId): void
    {
        // Content のバリデーション
        $this->validate(array_filter($this->rules(), fn ($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));
        // 担当者IDのバリデーション (念のため)
        if (! User::find($assigneeId)) {
            $this->error(__('ledger.workflow.invalid_assignee')); // エラーメッセージ

            return;
        }

        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        // 下書き保存チェック＆実行
        if (is_null($this->ledgerId)) {
            try {
                $this->saveDraftInternal();
                if (is_null($this->ledgerId)) {
                    throw new \RuntimeException('Failed to get Ledger ID.');
                }
            } catch (\Exception $e) {
                Log::error('SaveDraft failed: ' . $e->getMessage(), [
                    'ledger_id' => $this->ledgerId,
                    'user_id' => Auth::id(),
                    'error' => $e
                ]);
                $this->error(__('messages.error.generic'));
                return;
            }
        }

        try {
            // WorkflowService に $assigneeId を渡す
            $result = $this->workflowService->requestInspection(
                $this->ledgerId,
                $userId,
                $assigneeId
            );
            $this->addAttachedFileRecordIfNecessary();
            $this->success(
                __('ledger.workflow.inspection_requested_message'),
                redirectTo: route('ledger.show', ['ledgerId' => $this->ledgerId])
            );
        } catch (\Exception $e) {
            Log::error('Inspection request failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }*/

    // --- 点検依頼ボタンのアクション (下書き保存 -> モーダル表示) ---
/*    public function requestInspection(): void
    {
        // まず Content のバリデーションのみ実行
        $this->validate(array_filter($this->rules(), fn ($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));

        // 下書き保存を実行 (ファイル処理含む)
        try {
            $userId = Auth::id();
            $this->processFilesForSave(); // ファイル処理を先に行う
            $result = $this->workflowService->saveDraft(
                $this->ledgerId, // 既存ID or null
                $this->ledgerDefineId,
                $this->content,
                $this->contentAttached,
                $userId
            );
            $this->ledgerId = $result['ledger']->id;     // 必ず ID がセットされる
            $this->ledgerRecord = $result['ledger']; // レコードも更新
            $this->addAttachedFileRecordIfNecessary(); // ファイルレコード追加
            Log::info("Draft saved successfully before opening assignee modal. Ledger ID: {$this->ledgerId}");

            // 下書き保存成功後にモーダルを開く
            $this->openAssigneeModal('inspector');
        } catch (\Exception $e) {
            Log::error('Draft save failed before inspection request: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }*/
    // --- 点検依頼ボタンのアクション (下書き保存 -> 担当者選択モーダル表示) ---
    public function requestInspection(): void
    {
        // 1. Content のバリデーション
        $this->validate(array_filter($this->rules(), fn($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY));

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
            Log::error('Draft save failed before inspection request: ' . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    protected function getInitialApproverId(): ?int
    {
        // TODO: 承認者の推奨ロジック
        return null;
    }
}
