<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Word extends Model
{
    protected $connection = 'wordnet';

    protected $table = 'word';

    // wordidを主キーとして設定
    protected $primaryKey = 'wordid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['wordid', 'lang', 'lemma', 'pron', 'pos'];

    public function variants()
    {
        return $this->hasMany(Variant::class, 'wordid', 'wordid');
    }

    public function senses()
    {
        return $this->hasMany(Sense::class, 'wordid', 'wordid');
    }

    public function synonyms()
    {
        return $this->senses()
            ->join('sense as s2', 'sense.synset', '=', 's2.synset')
            ->join('word as w2', 's2.wordid', '=', 'w2.wordid')
            ->where('w2.wordid', '!=', $this->wordid);
    }
}
