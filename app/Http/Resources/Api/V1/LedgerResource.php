<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
