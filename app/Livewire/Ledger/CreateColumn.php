<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Http\Requests\Ledger\StoreRequest;
use App\Jobs\Ledger\AttachedFileScanJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
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

    /**
     * @var mixed|null
     */
    public $totalRequireColumnCount = 0;

    // --- ステップ1で追加 ---
    public ?int $selectedInspectorId = null; // 選択された点検者の User ID

    protected WorkflowService $workflowService; // WorkflowService をインジェクト

    // --- ここまで ---

    // WorkflowService をインジェクト
    public function boot(WorkflowService $workflowService): void
    {
        $this->workflowService = $workflowService;
    }

    // mount は Create と Modify で異なるので、各クラスで実装 or 親で共通化
    public function mount(Request $request): void
    {
        // Create 用の mount ロジック
        $this->ledgerDefineId = (int) $request->route('ledgerDefineId');
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
     * @throws Exception
     */
    /*    public function store(StoreRequest $request)
        {
            try {
                $this->validate();

                //        dd($this->content, $this->contentAttached);

                foreach ($this->ledgerDefineRecord->column_define as $column) {

                    if ($column->type === 'files') {

                        $filenames = [];
                        $fileContents = [];
                        foreach ($this->content[$column->id] as $uploadedFile) {
                            $stored = $this->storeFile($uploadedFile, $column->id);
                            $filenames[$stored->hashedBaseName] = $stored->originalName;
                            $fileContents[$stored->hashedBaseName] = null;
                        }
                        //                $filenames = $this->storeFile($column->id);
                        //dd($filenames,$fileContents);
                        $this->content[$column->id] = $filenames;
                        $this->contentAttached[$column->id] = $fileContents;
                    }
                }
                $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
                $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);
                //dd($this->content);
                //        dd($this->content, $this->contentAttached);
                //        createに数字キーの配列を渡すとModelのメソッドに渡るまでの間にキーの値が消えるため呼び出し元で歯抜けがないように配列のキーを作っておく必要がある
                //        数字キーによるソートもcreateに渡すまでの間に済ませておく
                $this->ledgerRecord = Ledger::create([
                    'content' => $this->content,
                    'content_attached' => $this->contentAttached,
                    'ledger_define_id' => $this->ledgerDefineRecord->id,
                    'creator_id' => Auth::user()->id,
                    'modifier_id' => Auth::user()->id,
                ]);
                //dd($this->content);
                $this->addAttachedFileRecord();

                $this->dispatch('ledgerStored', $this->ledgerRecord->id);
                $this->success(
                    __('ledger.stored.success'),
                    redirectTo: route('ledger.show', ['ledgerId' => $this->ledgerRecord->id])
                );

                return;
            } catch (Exception $e) {
                $this->addError('storedFailed', __('ledger.stored.failed'));
                //TODO: 例外処理
                error_log($e->getMessage());
            }

        }*/

    /**
     * @throws Exception
     */
    public function storeFile(TemporaryUploadedFile $file, $columnId = 0): object
    {

        $fileHashName = $file->store('public/Ledger/Attachments');
        //        $filenames[$file->getClientOriginalName()] = $fileHashName;

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
            'mime' => $file->getClientMimeType(),
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
                new AttachedFileScanJob($newAttachedFile->id),
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
                $rules[] = 'string';
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

            // カラムごとのバリデーションルールを配列に追加
            $validationRules[$columnName] = $rules;
        }

        // 点検者 ID のバリデーション (点検依頼時に必要)
        // requestInspection メソッド側で validateOnly する方が良いかも
        $rules['selectedInspectorId'] = ['nullable', 'integer', 'exists:users,id'];

        return $validationRules;
    }

    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $attributes["content.{$column->id}"] = $column->name;
        }

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
                $this->ledgerDefineRecord->column_define,
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

    // 点検依頼
    public function requestInspection(): void
    {
        // 点検者選択と content のバリデーション
        $this->validate(array_merge(
            array_filter($this->rules(), fn ($key) => str_starts_with($key, 'content.'), ARRAY_FILTER_USE_KEY),
            ['selectedInspectorId' => ['required', 'integer', 'exists:users,id']] // 点検者は必須
        ));

        $userId = Auth::id();
        $this->processFilesForSave(); // ファイル処理

        try {
            // WorkflowService を呼び出す
            $result = $this->workflowService->requestInspection(
                $this->ledgerId, // 下書き保存されていれば ID が入る
//                $this->ledgerDefineId,
                $this->content,
                $this->ledgerDefineRecord->column_define,
                $userId,
                $this->selectedInspectorId
            );

            // 戻り値から ID を更新
            $this->ledgerId = $result['ledger']->id;

            $this->addAttachedFileRecordIfNecessary();
            $this->success(__('ledger.workflow.inspection_requested_message'));
            // 詳細画面にリダイレクト
            $this->redirectRoute('ledger.show', ['ledgerId' => $this->ledgerId]);

        } catch (\Exception $e) {
            Log::error('Inspection request failed: '.$e->getMessage());
            $this->error(__('messages.error.generic'));
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

    // 推奨担当者を読み込むメソッド
    protected function loadRecommendedPersonnel(): void
    {
        if ($this->ledgerDefineRecord?->recommended_inspector_id) {
            $this->selectedInspectorId = $this->ledgerDefineRecord->recommended_inspector_id;
        }
        // ToDo: 推奨ロールの場合の処理
        // ToDo: 承認者の読み込み (ステップ2以降)
        // if($this->ledgerDefineRecord?->recommended_approver_id) {
        //     $this->selectedApproverId = $this->ledgerDefineRecord->recommended_approver_id;
        // }
    }
}
