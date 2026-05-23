<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Ai\BootstrapManifestService;
use App\Services\Ai\ClientSkillBootstrapService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveBootstrapManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'language' => $this->input('language', 'ja'),
            'model_profile' => $this->input('model_profile', 'general-local'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_type' => ['required', 'string', Rule::in(ClientSkillBootstrapService::SUPPORTED_CLIENTS)],
            'language' => ['required', 'string', 'max:16'],
            'role_profile' => ['required', 'string', Rule::in(array_keys(BootstrapManifestService::ROLE_PROFILES))],
            'model_profile' => ['required', 'string', Rule::in(array_keys(BootstrapManifestService::MODEL_PROFILES))],
        ];
    }
}
