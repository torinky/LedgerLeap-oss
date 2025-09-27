<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LedgerDefineResource",
 *     type="object",
 *     title="Ledger Define Resource",
 *     @OA\Property(property="id", type="integer", description="Ledger Define ID", example=1),
 *     @OA\Property(property="name", type="string", description="Name of the ledger definition", example="Meeting Minutes"),
 *     @OA\Property(property="description", type="string", description="Description of the ledger definition", example="Record meeting minutes."),
 *     @OA\Property(
 *         property="columns",
 *         type="array",
 *         description="Columns of the ledger definition",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", description="Column ID", example=1),
 *             @OA\Property(property="name", type="string", description="Column name", example="Meeting Name"),
 *             @OA\Property(property="type", type="string", description="Column type (e.g., text, date, select)", example="text"),
 *             @OA\Property(property="options", type="array", description="Options for select type columns", @OA\Items(type="string"), nullable=true, example={"Regular", "Extraordinary"})
 *         )
 *     )
 * )
 */
class LedgerDefineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title,
            'description' => $this->create_description,
            'columns' => collect($this->column_define)->map(function ($column) {
                return [
                    'id' => $column->id,
                    'name' => $column->name,
                    'type' => $column->type,
                    'options' => $column->options ?? null,
                ];
            })->values(),
        ];
    }
}
