<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $connection = 'wordnet';
    protected $table = 'word';
    // wordidを主キーとして設定
    protected $primaryKey = 'wordid';

    public function senses()
    {
        return $this->hasMany(Sense::class, 'wordid', 'wordid');
    }

}
