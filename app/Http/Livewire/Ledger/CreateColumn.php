<?php

namespace App\Http\Livewire\Ledger;

use App\Http\Requests\Ledger\StoreRequest;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class CreateColumn extends Component
{
    use WithFileUploads;

    public $content;
    public $ledgerDefineRecord;
    public int $ledgerDefineId;
    public $ledgerRecord;
    public $ledgerId;

    public function mount(request $request)
    {
        //new record create
        $this->ledgerDefineId = (int)$request->route('ledgerDefineId');
        $this->ledgerDefineRecord = LedgerDefine::where('ledger_defines.id', $this->ledgerDefineId)->first();
        $this->ledgerRecord = null;
        foreach ($this->ledgerDefineRecord->column_define as $cKey => $column) {
            if ($column->type == 'files' || $column->type == 'chk') {
                $this->content[$column->id] = [];

            } else {
                $this->content[$column->id] = '';

            }
        }
//        dd($this->ledgerDefineRecord);
    }

    public function render(): View
    {
        return view('livewire.ledger.create-column');
    }

    public function store(StoreRequest $request)
    {
        foreach ($this->ledgerDefineRecord->column_define as $cKey => $column) {
            if ($column->type == 'files') {
                $filenames = $this->storeFile($column->id);
                $this->content[$column->id] = $filenames;
            }
        }

        $this->ledgerRecord = Ledger::create([
            'content' => $this->content,
            'ledger_define_id' => $this->ledgerDefineRecord->id,
            'creator_id' => Auth::user()->id,
            'modifier_id' => Auth::user()->id,
        ]);

        return redirect()->route('ledger.show', ['ledgerId' => $this->ledgerRecord->id])
            ->with('status', __('ledger record stored successfully !'));
    }


    /**
     * @param int|string $id
     * @return array
     */
    public function storeFile(int|string $id): array
    {
        $allowedMimeTypes = ['image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/svg+xml'];
        $filenames = [];
        if (!isset($this->content[$id])) {
            return $filenames;
        }
        foreach ($this->content[$id] as $file) {
            $fileHashName = $file->store('public/Ledger/Attachments');
            $filenames[$file->getClientOriginalName()] = $fileHashName;

            $contentType = $file->getClientMimeType();
            if (!in_array($contentType, $allowedMimeTypes)) {
                // Create a thumbnail of the image using Intervention Image Library
                $imageManager = new ImageManager();
                $img = Image::make($file->getRealPath());
                $image = $imageManager->make($img)->resize(null, 200, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $image->save(storage_path('app/public/Ledger/thumbs/' . basename($fileHashName)));
            }
        }
        return $filenames;
    }

    /**
     * 断続的にファイルアップロードした際に以前のアップロードとマージする
     * https://github.com/livewire/livewire/issues/1230
     * @param string $name
     * @param string $tmpPath
     * @return void
     */

    public function finishUpload($name, $tmpPath, $isMultiple)
    {
        $this->cleanupOldUploads();

        $files = collect($tmpPath)->map(function ($i) {
            return TemporaryUploadedFile::createFromLivewire($i);
        })->toArray();
        $this->emitSelf('upload:finished', $name, collect($files)->map->getFilename()->toArray());

        //        $files = array_merge($this->getPropertyValue($name), $files);
        $presentValue = $this->getPropertyValue($name);
        if (!empty($presentValue)) {
            $files = array_merge($presentValue, $files);
        }

        $this->syncInput($name, $files);
    }


}
