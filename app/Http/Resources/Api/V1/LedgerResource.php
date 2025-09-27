<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LedgerResource",
 *     type="object",
 *     title="Ledger Resource",
 *     @OA\Property(property="id", type="integer", description="Ledger ID", example=1),
 *     @OA\Property(property="title", type="string", description="Title of the ledger", example="Sample Ledger Title"),
 *     @OA\Property(property="content", type="object", description="Content of the ledger, with column IDs as keys", example={"1": "Content for column 1"}),
 *     @OA\Property(property="content_attached", type="string", description="Text content extracted from attached files", nullable=true),
 *     @OA\Property(
 *         property="folder",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Project A")
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="tag1")
 *         )
 *     ),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LedgerResource extends JsonResource
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
            'title' => $this->whenLoaded('define', $this->define->title),
            'content' => $this->content,
            'content_attached' => $this->when(!empty($this->content_attached), $this->content_attached),
            'folder' => $this->whenLoaded('define', fn () => [
                'id' => $this->define->folder->id,
                'name' => $this->define->folder->name,
            ]),
            'tags' => $this->whenLoaded('define', fn () => $this->define->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'updated_at' => $this->updated_at,
        ];
    }
}
