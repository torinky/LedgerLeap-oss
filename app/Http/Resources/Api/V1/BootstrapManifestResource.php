<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="BootstrapManifestResource",
 *     type="object",
 *     title="Bootstrap Manifest Resource",
 *
 *     @OA\Property(property="client_type", type="string", example="copilot"),
 *     @OA\Property(property="language", type="string", example="ja"),
 *     @OA\Property(
 *         property="role_profile",
 *         type="object",
 *         @OA\Property(property="id", type="string", example="operator"),
 *         @OA\Property(property="label", type="string", example="実務担当者")
 *     ),
 *     @OA\Property(
 *         property="model_profile",
 *         type="object",
 *         @OA\Property(property="id", type="string", example="small-local"),
 *         @OA\Property(property="label", type="string", example="small-local"),
 *         @OA\Property(property="text_budget", type="string", example="compact"),
 *         @OA\Property(property="schema_budget", type="string", example="minimal"),
 *         @OA\Property(property="guidance", type="array", @OA\Items(type="string"))
 *     ),
 *     @OA\Property(
 *         property="recommended_capabilities",
 *         type="array",
 *
 *         @OA\Items(
 *             type="object",
 *
 *             @OA\Property(property="id", type="string", example="ledger-search"),
 *             @OA\Property(property="summary", type="string"),
 *             @OA\Property(property="primary_user_goals", type="array", @OA\Items(type="string")),
 *             @OA\Property(property="required_guides", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Property(property="resources", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="prompts", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="files", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="placement_instructions", type="object"),
 *     @OA\Property(property="warnings", type="array", @OA\Items(type="string"))
 * )
 */
class BootstrapManifestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'client_type' => $this['client_type'],
            'language' => $this['language'],
            'role_profile' => $this['role_profile'],
            'model_profile' => $this['model_profile'],
            'recommended_capabilities' => $this['recommended_capabilities'],
            'resources' => $this['resources'],
            'prompts' => $this['prompts'],
            'files' => $this['files'],
            'placement_instructions' => $this['placement_instructions'],
            'warnings' => $this['warnings'],
        ];
    }
}
