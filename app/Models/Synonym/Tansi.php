<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TansiV110
 *
 * @property $WORD
 * @property $pronunciation1
 * @property $pronunciation2
 * @property $category1
 * @property $category2
 * @property $CANDIDATES
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Tansi extends Model
{
    protected $connection = 'tansi';

    protected $table = 'TANSI_V110';

    // wordidを主キーとして設定
    protected $primaryKey = 'WORD';

    protected $perPage = 20;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['WORD', 'pronunciation1', 'pronunciation2', 'category1', 'category2', 'CANDIDATES'];
}
