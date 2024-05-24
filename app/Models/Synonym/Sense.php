<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Sense extends Model
{
    public $incrementing = false;

    protected $connection = 'wordnet';

    // wordidとsynsetの複合キーを設定
    protected $table = 'sense';

    protected $primaryKey = ['wordid', 'synset'];

    public function word()
    {
        return $this->belongsTo(Word::class, 'wordid', 'wordid')->with('word');
    }

    public function synset()
    {
        return $this->belongsTo(Keyword::class, 'synset', 'synset');
    }
}
