<?php

namespace App\Http\Requests\Ledger;

use App\Models\ColumnDefine;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
        $columnDefines = ColumnDefine::typeLabels();

        $rules = [
            // 他のルールを追加する場合はここに記述
        ];

        foreach ($columnDefines as $columnId => $columnName) {
            $rules["content.$columnId"] = 'nullable';
        }

        return $rules;
    }

    public function content()
    {
        $content = $this->input('content');
        //id順になっているのでorderで並び替える
        ksort($content);

        return collect($content);
    }

    public function ledger_define_id()
    {
        return $this->input('ledger_define_id');
    }
}
