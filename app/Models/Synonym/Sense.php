<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Sense extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    protected $table = 'sense';
    public $timestamps = false;

    protected $primaryKey = ['wordid', 'synset'];

    protected $fillable = ['synset', 'wordid', 'lang', 'rank', 'lexid', 'freq', 'src'];

    public function synset()
    {
        return $this->belongsTo(Synset::class, 'synset', 'synset');
    }

    public function word()
    {
        return $this->belongsTo(Word::class, 'wordid', 'wordid');
    }

    /*    public function word()
        {
            return $this->belongsTo(Word::class, 'wordid', 'wordid')->with('word');
        }*/

}
