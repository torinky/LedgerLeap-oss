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

    public function senses()
    {
        return $this->hasMany(Sense::class, 'wordid', 'wordid');
    }
}
