<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WordForm extends Model
{
    protected $connection = 'wordnet';
    protected $table = 'word';
    // wordidを主キーとして設定
    protected $primaryKey = 'wordid';

    public function wordSenses()
    {
        return $this->hasMany(WordSense::class, 'wordid', 'wordid');
    }

}
