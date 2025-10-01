<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LedgerResource",
 *     type="object",
 *     title="Ledger Resource",
 *
 *     @OA\Property(property="id", type="integer", description="Ledger ID", example=1),
 *     @OA\Property(
 *         property="define",
 *         type="object",
 *         description="The definition of this ledger",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Sample Ledger Title"),
 *         @OA\Property(property="description", type="string", example="This is a sample ledger.")
 *     ),
 *     @OA\Property(property="content", type="object", description="Content of the ledger, with column names as keys"),
 *     @OA\Property(property="content_attached", type="string", description="Text content extracted from attached files", nullable=true),
 *     @OA\Property(
 *         property="folder",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Project A"),
 *         @OA\Property(property="path", type="string", example="/Root/Project A")
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *
 *         @OA\Items(
 *             type="object",
 *
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="tag1")
 *         )
 *     ),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // whenLoaded を使わずに、リレーションがロードされていることを前提とする
        $folder = $this->define->folder;
        $folderPath = null;
        if ($folder && $folder->relationLoaded('ancestors')) {
            $path = $folder->ancestors->pluck('name')->push($folder->name)->implode('/');
            $folderPath = '/'.$path;
        }

        $parsedContent = [];
        if ($this->define && $this->define->relationLoaded('column_define')) {
            $columns = $this->define->column_define->keyBy('id');
            if (is_array($this->content)) {
                foreach ($this->content as $columnId => $value) {
                    $columnName = $columns->get($columnId)->name ?? 'unknown_column_'.$columnId;
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
            'content_attached' => $this->when(! empty($this->content_attached), $this->content_attached),
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
