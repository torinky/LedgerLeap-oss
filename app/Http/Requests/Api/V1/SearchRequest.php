<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'ledger_define_id' => ['nullable', 'integer', 'exists:ledger_defines,id'],
            'creator_id' => ['nullable', 'integer', 'exists:users,id'], // 追加
            'exclude_q' => ['nullable', 'string', 'max:255'],
            'exclude_tags' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'string', Rule::in(['search', 'count'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
            // 日付フィルタの追加
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            // フィルタパラメータの追加
            'filter.creator_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter.created_from' => ['nullable', 'date'],
            'filter.created_to' => ['nullable', 'date', 'after_or_equal:filter.created_from'],
            'filter.created_between' => ['nullable', 'string'],
            'filter.q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
