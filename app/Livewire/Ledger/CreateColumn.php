<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Http\Requests\Ledger\StoreRequest;
use App\Jobs\Ledger\AttachedFileScanJob;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
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

    public array $content;

    public array $labelColor;

    public mixed $ledgerDefineRecord;

    public int $ledgerDefineId;

    public mixed $ledgerRecord;

    public string $ledgerId;

    private array $contentAttached = [];

    private array $newAttachedFiles = [];

    public $backgroundImages = [];

    public $progress = 0;

    public $requredColumnIds = [];

    /**
     * @var mixed|null
     */
    public $totalRequireColumnCount = 0;

    public function mount(request $request): void
    {
        //new record create
        $this->ledgerDefineId = (int)$request->route('ledgerDefineId');
        $this->ledgerDefineRecord = LedgerDefine::where('ledger_defines.id', $this->ledgerDefineId)->first();
        $this->ledgerRecord = null;

        $this->initRequireColumns();

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type === 'files' || $column->type === 'chk') {
                $this->content[$column->id] = [];
            } else {
                $this->content[$column->id] = '';
            }
            if ($column->required) {
                $this->labelColor[$column->id] = 'warning';
            } else {
                $this->labelColor[$column->id] = 'muted';
            }
        }

        $this->initBackgroundImages();
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

                return asset('storage/' . $value->path);
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
            error_log('ValidationException occurred: ' . $e->getMessage());
            $this->error(__('ledger.validation.failed'), $e->getMessage());
        }
    }

    public function updateProgress(): void
    {
        if (!isset($this->requredColumnIds)) {
            $this->initRequireColumns();
        }

        $this->progress = collect($this->content)->filter(function ($value, $key) {
                if (!is_array(($value))) {
                    $value = trim($value);
                } else {
                    $value = array_filter($value, 'strlen');
                }

                return !empty($value) && in_array($key, $this->requredColumnIds);
            })->count() / $this->totalRequireColumnCount * 100;
    }

    /**
     * @throws Exception
     */
    public function store(StoreRequest $request)
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

            /*            return redirect()->route('ledger.show', ['ledgerId' => $this->ledgerRecord->id])
                            ->with('status', __('ledger.stored.success'));*/
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

    }

    /**
     * @throws Exception
     */
    public function storeFile(TemporaryUploadedFile $file, $columnId = 0): object
    {

        $fileHashName = $file->store('public/Ledger/Attachments');
        //        $filenames[$file->getClientOriginalName()] = $fileHashName;

        $result = (object)[
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
            $image->save(storage_path('app/public/Ledger/thumbs/' . basename($fileHashName)));
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

        $files = collect($tmpPath)->map(fn($i) => TemporaryUploadedFile::createFromLivewire($i))->toArray();
        $this->dispatch('upload:finished', $name, collect($files)->map->getFilename()->toArray())->self();

        //        $files = array_merge($this->getPropertyValue($name), $files);
        $presentValue = $this->getPropertyValue($name);
        if (!empty($presentValue)) {
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
            $columnName = 'content.' . $columnId;
            $columnType = $column->type;

            $rules = [];

            // カラムの種類に基づいた共通のバリデーションルールを追加
            if ($columnType === 'text' || $columnType === 'textarea') {
                $rules[] = 'string';
            } elseif ($columnType === 'number') {
                $rules[] = 'string';
            } elseif ($columnType === 'YMD') {
                $rules[] = 'date_format:Y-m-d';
            } elseif ($column->type === 'chk' && $column->useOptions && !empty($column->options)) {
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
}
