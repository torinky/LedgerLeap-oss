<?php

namespace App\Http\Requests\Ledger;

use App\Models\LedgerDefine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SearchRequest extends FormRequest
{
    private $tags = [];

    private $keywords = [];

    /**
     * Perform additional processing on the request.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $text = $this->keyword();
        if (empty($text)) {
            $this->tags = [];
            $this->keywords = [];

            return;
        }
        if (is_array($text)) {
            $words = $text;
        } else {
            $text = mb_convert_kana($text, 'askV', 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);

            $words = explode(' ', $text);
            $words = array_filter($words, 'strlen');
        }
        foreach ($words as $word) {
            if (Str::startsWith($word, '#')) {
                $this->tags[] = $word;
            } else {
                $this->keywords[] = $word;
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'keyword' => 'max:140',
        ];
    }

    /**
     * @return string
     */
    public function keyword()
    {
        return $this->input('keyword');
        //        return implode(' ',$this->keywords);
    }

    /**
     * Get the keywords array.
     */
    public function keywords(): array
    {
        return $this->keywords;
    }

    /**
     * Get the tags array.
     */
    public function tags(): array
    {
        return $this->tags;
    }

    public $isFolderRequest = false;

    public function folderId()
    {
        //        dd($this->route('folderId'));
        $ledgerDefineId = $this->ledgerDefineId();

        if (! is_null($ledgerDefineId)) {
            $this->isFolderRequest = true;

            return [LedgerDefine::find($ledgerDefineId)->folder_id];
        }

        $result = $this->input('f') ?? $this->input('folderId') ?? $this->route('folderId');
        if (is_null($result)) { // null の場合は空配列を返す
            return [];
        }
        if (is_string($result)) {
            $result = [$result];
        }

        return $result;
    }

    public function currentFolderId()
    {

        return $this->input('cf') ?? $this->input('currentFolderId') ?? $this->route('folderId') ?? 1;
    }

    public $isLedgerDefineRequest = false;

    public function ledgerDefineId()
    {
        $ledgerDefineId = $this->input('defineId') ?? $this->route('ledgerDefineId') ?? null;
        if (! is_null($ledgerDefineId)) {
            $this->isLedgerDefineRequest = true;

            return $ledgerDefineId;
        }

        return null;
    }

    public function filter()
    {
        $filter = $this->query('filter');
        if (! is_array($filter)) {
            return [];
        }

        return $filter;
    }
}
