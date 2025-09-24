<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
