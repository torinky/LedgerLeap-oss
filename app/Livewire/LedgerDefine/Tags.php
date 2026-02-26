<?php

namespace App\Livewire\LedgerDefine;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;

class Tags extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public $ledgerDefineId;

    public $tags = [];

    public $newTag = '';

    public function mount($ledgerDefineId, $tags = null)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        // 親から渡された場合はそれを使用、なければクエリ実行
        $this->tags = $tags ?? Tag::where('ledger_define_id', $this->ledgerDefineId)->get();
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
