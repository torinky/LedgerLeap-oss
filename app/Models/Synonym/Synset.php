<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Synset extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    protected $table = 'synset';
    protected $primaryKey = 'synset';

    public function senses()
    {
        return $this->hasMany(Sense::class, 'synset', 'synset');
    }

}
