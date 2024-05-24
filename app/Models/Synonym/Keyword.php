<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    protected $table = 'synset';
    protected $primaryKey = 'synset';

    protected $fillable = [
        'pos',
        'name',
        'src',
    ];

    public function synonyms()
    {
        return $this->hasMany(Synonym::class, 'synset', 'synset1');
    }
}
