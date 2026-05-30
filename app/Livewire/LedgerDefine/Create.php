<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasFolderTree;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\LedgerDefine;
use App\Services\ConfidentialityLevelService;
use Mary\Traits\Toast;

class Create extends BaseLivewireComponent
{
    use HasFolderTree, InitializesTenantContext, Toast;

    public $ledgerDefineRecord;

    public $title;

    public $parentFolderId;

    public string $confidentialityLevel = 'public';

    public array $confidentialityScopes = [];

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'parentFolderId' => ['required', 'integer', 'exists:folders,id'],
            'confidentialityLevel' => ['required', 'string', 'in:public,internal,confidential,secret'],
            'confidentialityScopes' => ['nullable', 'array'],
            'confidentialityScopes.*' => ['string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => __('ledger.define.title'),
            'parentFolderId' => __('ledger.folder.containing'),
            'confidentialityLevel' => __('ledger.confidentiality.level.label'),
            'confidentialityScopes' => __('ledger.confidentiality.scope.label'),
        ];
    }

    public function render()
    {
        return view('livewire.ledger-define.create', [
            'confidentialityLevelOptions' => ConfidentialityLevelService::selectOptions(),
            'confidentialityScopeOptions' => ConfidentialityLevelService::allScopes(),
        ])->layout('layouts.app', ['title' => 'SETTING | DocumentCabinet']);
    }

    public function mount(CreateRequest $request)
    {
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineRecord = $ledgerDefine;

        $this->title = $request->title;
        $this->parentFolderId = $request->folderId();
        $this->confidentialityLevel = 'public';
        $this->confidentialityScopes = [];
        $this->initializeFolderTree($this->parentFolderId);
    }

    public function store()
    {
        $this->validate();

        $this->ledgerDefineRecord->title = $this->title;
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->creator_id = auth()->id();
        $this->ledgerDefineRecord->column_define = [];
        $this->ledgerDefineRecord->confidentiality_level = $this->confidentialityLevel;
        $this->ledgerDefineRecord->confidentiality_scopes = ConfidentialityLevelService::parseScopeChoices(
            $this->confidentialityScopes
        );
        $this->ledgerDefineRecord->save();

        $this->success(__('ledger.has_been_created'),
            redirectTo: route('ledgerDefine.edit', [
                'ledgerDefineId' => $this->ledgerDefineRecord->id,
                'fromCreate' => true,
            ])
        );
    }
}
