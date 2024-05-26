<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

class Synset extends Model
{
    public $incrementing = false;
    protected $connection = 'wordnet';
    protected $table = 'synset';
    public $timestamps = false;

    protected $fillable = ['synset', 'pos', 'name', 'src'];

    public function senses()
    {
        return $this->hasMany(Sense::class, 'synset', 'synset');
    }

    public function ancestors1()
    {
        return $this->hasMany(Ancestor::class, 'synset1', 'synset');
    }

    public function ancestors2()
    {
        return $this->hasMany(Ancestor::class, 'synset2', 'synset');
    }

    public function synonyms()
    {
        return $this->hasMany(Synlink::class, 'synset', 'synset1');
    }

    public function synlinks1()
    {
        return $this->hasMany(Synlink::class, 'synset1', 'synset');
    }

    public function synlinks2()
    {
        return $this->hasMany(Synlink::class, 'synset2', 'synset');
    }

    public function synsetDefs()
    {
        return $this->hasMany(SynsetDef::class, 'synset', 'synset');
    }

    public function synsetExes()
    {
        return $this->hasMany(SynsetEx::class, 'synset', 'synset');
    }

    public function xlinks()
    {
        return $this->hasMany(Xlink::class, 'synset', 'synset');
    }
}
