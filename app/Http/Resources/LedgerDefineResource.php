<?php

namespace App\Http\Resources;

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
            'name' => $this->title, // `name`として`title`を返す
            'description' => $this->description,
            'columns' => $this->column_define->map(function ($column) {
                return [
                    'id' => $column->id,
                    'name' => $column->name,
                    'type' => $column->type,
                    'options' => $column->options,
                ];
            }),
        ];
    }
}
