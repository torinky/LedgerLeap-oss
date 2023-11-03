<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordSynset extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    protected $table = 'synset';
    protected $primaryKey = 'synset';

    public function wordSenses()
    {
        return $this->hasMany(WordSense::class, 'synset', 'synset');
    }

    public function synsetDefinition()
    {
        return $this->belongsTo(SynsetDefinition::class, 'synset', 'synset');
    }
}
