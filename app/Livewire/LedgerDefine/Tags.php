<?php

namespace App\Livewire\LedgerDefine;

use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Livewire\Traits\InitializesTenantContext;

class Tags extends Component
{
    use InitializesTenantContext;

    public $ledgerDefineId;

    public $tags = [];

    public $newTag = '';

    public function mount($ledgerDefineId)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->tags = Tag::where('ledger_define_id', $this->ledgerDefineId)->get();
    }

    public function addTag()
    {
        if ($this->newTag !== '') {
            $tagText = mb_convert_kana($this->newTag, 'askV', 'UTF-8');

            $tag = Tag::create([
                'folder_id' => 0,
                'ledger_define_id' => $this->ledgerDefineId,
                'name' => $tagText,
                'creator_id' => Auth::user()->id,
                'modifier_id' => Auth::user()->id,
            ]);
            $this->tags = Tag::where('ledger_define_id', $this->ledgerDefineId)->get();
            $this->newTag = '';
        }
    }

    public function removeTag($tagId)
    {
        Tag::find($tagId)->delete();
        $this->tags = $this->tags->filter(function ($item) use ($tagId) {
            return $item->id != $tagId;
        });
    }

    public function render()
    {
        return view('livewire.ledger-define.tag');
    }
}
