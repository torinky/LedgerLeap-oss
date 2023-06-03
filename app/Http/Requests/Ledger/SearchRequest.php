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
        $text = mb_convert_kana($text, 'askV', 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $words = explode(' ', $text);
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
            'keyword' => 'max:140'
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
     *
     * @return array
     */
    public function keywords(): array
    {
        return $this->keywords;
    }

    /**
     * Get the tags array.
     *
     * @return array
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

        if (!is_null($ledgerDefineId)) {
            $this->isFolderRequest = true;
            return LedgerDefine::find($ledgerDefineId)->folder_id;
        }

        return $this->input('folderId') ?? $this->route('folderId') ?? 1;
    }

    public $isLedgerDefineRequest = false;

    public function ledgerDefineId()
    {
        $ledgerDefineId = $this->input('defineId') ?? $this->route('ledgerDefineId') ?? null;
        if (!is_null($ledgerDefineId)) {
            $this->isLedgerDefineRequest = true;
            return $ledgerDefineId;
        }
    }
}
