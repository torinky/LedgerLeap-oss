<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TansiRequest extends FormRequest
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
            'WORD' => 'string',
            'pronunciation1' => 'string',
            'pronunciation2' => 'string',
            'category1' => 'string',
            'category2' => 'string',
            'CANDIDATES' => 'string',
        ];
    }
}
