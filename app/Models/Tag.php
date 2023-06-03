<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder_id', 'ledger_define_id', 'creator_id', 'modifier_id', 'name'
    ];


    public function define()
    {
        return $this->hasOne(Ledger::class, 'ledger_define_id');
    }

    public function folder()
    {
        return $this->hasOne(Folder::class, 'folder_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

}
