<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="UpdateLedgerRequest",
 *     type="object",
 *     title="Update Ledger Request Body",
 *     required={"content_patch"},
 *
 *     @OA\Property(
 *         property="content_patch",
 *         type="object",
 *         description="An object where keys are column definition IDs and values are only the fields to update."
 *     ),
 *     @OA\Property(
 *         property="comment",
 *         type="string",
 *         description="Optional comment describing why the update was made.",
 *         nullable=true,
 *         example="差し戻し指摘を反映"
 *     )
 * )
 */
class UpdateLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'content_patch' => 'required|array|min:1',
            'comment' => 'nullable|string|max:2000',
            'tag_operation' => 'prohibited',
            'tag_values' => 'prohibited',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_operation.prohibited' => 'Tag updates are not supported by the REST update API yet.',
            'tag_values.prohibited' => 'Tag updates are not supported by the REST update API yet.',
        ];
    }
}
