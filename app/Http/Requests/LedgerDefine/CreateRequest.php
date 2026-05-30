<?php

namespace App\Http\Requests\LedgerDefine;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }

    public function column_define()
    {
        $columnDefine = $this->input('column_define') ?? [];
        if (empty($columnDefine)) {
            return [];
        }
        //        第1階層は強制的に配列にする（保存されるjsonがオブジェクトになってしまわないようにする対策）
        $columnDefine = array_values($columnDefine);

        return $columnDefine;

    }

    public function title()
    {
        $title = $this->input('title');

        return $title;
    }

    public function folderId()
    {
        return $this->input('folder_id') ?? $this->route('folderId') ?? 1;
    }
}
