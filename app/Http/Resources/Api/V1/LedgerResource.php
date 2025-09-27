<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // whenLoaded を使わずに、リレーションがロードされていることを前提とする
        $folder = $this->define->folder;
        $folderPath = null;
        if ($folder && $folder->relationLoaded('ancestors')) {
            $path = $folder->ancestors->pluck('name')->push($folder->name)->implode('/');
            $folderPath = '/' . $path;
        }

        $parsedContent = [];
        if ($this->define && $this->define->relationLoaded('column_define')) {
            $columns = $this->define->column_define->keyBy('id');
            if (is_array($this->content)) {
                foreach ($this->content as $columnId => $value) {
                    $columnName = $columns->get($columnId)->name ?? 'unknown_column_' . $columnId;
                    $parsedContent[$columnName] = $value;
                }
            }
        }

        return [
            'id' => $this->id,
            'define' => [
                'id' => $this->define->id,
                'name' => $this->define->title,
                'description' => $this->define->create_description,
            ],
            'content' => $parsedContent,
            'content_attached' => $this->when(!empty($this->content_attached), $this->content_attached),
            'folder' => $folder ? [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folderPath,
            ] : null,
            'tags' => $this->define->relationLoaded('tags') ? $this->define->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ]) : [],
            'updated_at' => $this->updated_at,
        ];
    }
}
