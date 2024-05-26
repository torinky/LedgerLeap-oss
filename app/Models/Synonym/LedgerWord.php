<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    // キーワードと同義語の関係を定義
    public function synonyms()
    {
        return $this->belongsToMany(LedgerWord::class, 'ledger_word_synonym', 'ledger_word_id', 'synonym_id');
    }
}
