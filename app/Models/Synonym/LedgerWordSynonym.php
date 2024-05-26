<?php

namespace App\Models\Synonym;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerWordSynonym extends Model
{
    use HasFactory;

    protected $fillable = [
        'ledger_word_id',
        'synonym_id',
        'user_id',
    ];

    // キーワード/同義語との関連を定義
    public function word()
    {
        return $this->belongsTo(LedgerWord::class, 'ledger_word_id');
    }

    public function synonym()
    {
        return $this->belongsTo(LedgerWord::class, 'synonym_id');
    }

    // ユーザーとの関連を定義
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 再帰的に同義語を取得します。
     */
    public function getAllSynonyms(array &$checked = []): array
    {
        if (in_array($this->name, $checked)) {
            return [];
        }

        $checked[] = $this->name;

        $synonyms = $this->synonyms()->with('synonym')->get()->pluck('synonym.name')->toArray();
        $synonymsOf = $this->synonymOf()->with('word')->get()->pluck('word.name')->toArray();

        $allSynonyms = array_merge($synonyms, $synonymsOf);

        foreach ($allSynonyms as $synonymName) {
            $synonymWord = LedgerWord::where('name', $synonymName)->first();
            if ($synonymWord) {
                $allSynonyms = array_merge($allSynonyms, $synonymWord->getAllSynonyms($checked));
            }
        }

        return array_unique($allSynonyms);
    }

    /**
     * 同義語としてのキーワードを取得します。
     */
    public function synonymOf()
    {
        return $this->hasMany(LedgerWordSynonym::class, 'synonym_id');
    }
}
