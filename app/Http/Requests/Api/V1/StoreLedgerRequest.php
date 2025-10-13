<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="StoreLedgerRequest",
 *     type="object",
 *     title="Store Ledger Request Body",
 *     required={"ledger_define_id", "folder_id", "content"},
 *
 *     @OA\Property(property="ledger_define_id", type="integer", description="The ID of the ledger definition.", example=1),
 *     @OA\Property(property="folder_id", type="integer", description="The ID of the folder to store the ledger in.", example=5),
 *     @OA\Property(
 *         property="content",
 *         type="object",
 *         description="An object where keys are column definition IDs and values are the content."
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         description="An array of tag names.",
 *
 *         @OA\Items(type="string"),
 *         example={"New Project", "Important"}
 *     )
 * )
 */
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
