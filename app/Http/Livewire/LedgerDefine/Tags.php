<?php

namespace App\Http\Livewire\LedgerDefine;

use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Tags extends Component
{
    public $ledgerDefineId;
    public $tags = [];
    protected $rules = [
        'tags' => 'required',
    ];
    public $newTag = '';

    public function mount()
    {
        $this->tags = Tag::where('ledger_define_id', $this->ledgerDefineId)->pluck('name', 'id')->toArray();
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
            $this->tags[$tag->id] = $this->newTag;
            $this->newTag = '';
        }
    }

    public function removeTag($tagId)
    {
        Tag::find($tagId)->delete();
        unset($this->tags[$tagId]);
    }

    public function render()
    {
        return view('livewire.ledger-define.tag');
    }
}
