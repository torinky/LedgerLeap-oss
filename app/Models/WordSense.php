<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordSense extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    // wordidとsynsetの複合キーを設定
    protected $table = 'sense';
    protected $primaryKey = ['wordid', 'synset'];

    public function wordForm()
    {
        return $this->belongsTo(WordForm::class, 'wordid', 'wordid');
    }


    public function synset()
    {
        return $this->belongsTo(WordSynset::class, 'synset', 'synset');
    }

}
