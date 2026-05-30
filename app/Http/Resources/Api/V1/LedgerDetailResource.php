<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LedgerDetailResource",
 *     type="object",
 *     title="Ledger Detail Resource",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(
 *         property="define",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="日報"),
 *         @OA\Property(property="description", type="string", example="日報入力用の台帳です"),
 *         @OA\Property(property="workflow_enabled", type="boolean", example=true)
 *     ),
 *     @OA\Property(property="content", type="object"),
 *     @OA\Property(property="content_by_column_id", type="object"),
 *     @OA\Property(
 *         property="column_definitions",
 *         type="array",
 *
 *         @OA\Items(
 *             type="object",
 *
 *             @OA\Property(property="id", type="integer", example=0),
 *             @OA\Property(property="name", type="string", example="件名"),
 *             @OA\Property(property="type", type="string", example="text"),
 *             @OA\Property(property="required", type="boolean", example=true),
 *             @OA\Property(property="order", type="integer", example=1)
 *         )
 *     ),
 *     @OA\Property(property="content_attached", type="string", nullable=true),
 *     @OA\Property(
 *         property="folder",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=5),
 *         @OA\Property(property="name", type="string", example="Project A"),
 *         @OA\Property(property="path", type="string", example="/Root/Project A")
 *     ),
 *     @OA\Property(
 *         property="workflow",
 *         type="object",
 *         @OA\Property(property="status", type="string", example="draft"),
 *         @OA\Property(property="status_label", type="string", example="作成中"),
 *         @OA\Property(property="editable", type="boolean", example=true),
 *         @OA\Property(property="returns_to_draft_on_save", type="boolean", example=false),
 *         @OA\Property(property="latest_comment", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LedgerDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $folder = $this->define->folder;
        $folderPath = null;
        if ($folder && $folder->relationLoaded('ancestors')) {
            $path = $folder->ancestors->pluck('name')->push($folder->name)->implode('/');
            $folderPath = '/'.$path;
        }

        $rawContent = is_array($this->content) ? $this->content : [];
        $parsedContent = [];
        $columnDefinitions = [];

        if ($this->define && $this->define->column_define) {
            $columns = collect($this->define->column_define)->keyBy('id');

            foreach ($rawContent as $columnId => $value) {
                $column = $columns->get($columnId);
                $columnName = $column?->name ?? 'unknown_column_'.$columnId;
                $parsedContent[$columnName] = $value;
            }

            $columnDefinitions = collect($this->define->column_define)
                ->map(fn ($column) => [
                    'id' => $column->id,
                    'name' => $column->name,
                    'type' => $column->type,
                    'required' => (bool) ($column->required ?? false),
                    'order' => $column->order,
                ])
                ->values()
                ->all();
        }

        $status = $this->status;
        $returnsToDraftOnSave = in_array($status, [
            WorkflowStatus::PENDING_INSPECTION,
            WorkflowStatus::PENDING_APPROVAL,
        ], true);

        return [
            'id' => $this->id,
            'define' => [
                'id' => $this->define->id,
                'name' => $this->define->title,
                'description' => $this->define->create_description,
                'workflow_enabled' => (bool) $this->define->workflow_enabled,
            ],
            'content' => $parsedContent,
            'content_by_column_id' => $rawContent,
            'column_definitions' => $columnDefinitions,
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
            'workflow' => [
                'status' => $status?->value,
                'status_label' => $status?->label(),
                'editable' => ! $this->isLocked(),
                'returns_to_draft_on_save' => $returnsToDraftOnSave,
                'latest_comment' => $this->latestDiff?->comments,
            ],
            'version' => $this->version,
            'updated_at' => $this->updated_at,
        ];
    }
}
