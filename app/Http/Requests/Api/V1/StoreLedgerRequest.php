<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Controller側でFolderPolicyを使って認可するため、ここではtrueを返す
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'ledger_define_id' => 'required|integer|exists:ledger_defines,id',
            'folder_id' => 'required|integer|exists:folders,id',
            'content' => 'required|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ];
    }
}