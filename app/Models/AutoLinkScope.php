<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class AutoLinkScope extends MorphPivot
{
    protected $table = 'auto_link_scopes';

    public $timestamps = false;

    /**
     * 親モデルのタイムスタンプを更新するリレーション
     *
     * @var string[]
     */
    protected $touches = ['autoLink'];

    public function autoLink()
    {
        return $this->belongsTo(AutoLink::class);
    }
}
