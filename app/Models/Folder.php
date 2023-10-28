<?php

namespace App\Models;

use Fureev\Trees\{NestedSetTrait};
use Illuminate\Database\Eloquent\Model;

//class Folder extends Model implements TreeConfigurable
class Folder extends Model
{
    use NestedSetTrait;

    protected $fillable = [
        'title',
    ];

    public function ledgerDefines()
    {
        return $this->hasMany(LedgerDefine::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function tag()
    {
        return $this->hasMany(Tag::class, 'ledger_define_id');
    }

}
