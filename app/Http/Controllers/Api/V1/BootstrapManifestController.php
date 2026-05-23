<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveBootstrapManifestRequest;
use App\Http\Resources\Api\V1\BootstrapManifestResource;
use App\Services\Ai\BootstrapManifestService;
use OpenApi\Annotations as OA;

class BootstrapManifestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/ai/bootstrap-manifest",
     *     summary="Resolve an initial bootstrap manifest",
     *     description="Returns a minimal client-facing bootstrap bundle for the requested client and role.",
     *     tags={"AI Bootstrap"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="client_type", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="role_profile", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="model_profile", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", default="ja")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Bootstrap manifest resolved",
     *
     *         @OA\JsonContent(ref="#/components/schemas/BootstrapManifestResource")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function show(
        ResolveBootstrapManifestRequest $request,
        BootstrapManifestService $bootstrapManifestService,
    ): BootstrapManifestResource {
        return $this->resolveResource($request, $bootstrapManifestService);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ai/bootstrap-manifest/resolve",
     *     summary="Resolve an initial bootstrap manifest",
     *     description="POST variant of the bootstrap manifest resolution endpoint for structured clients.",
     *     tags={"AI Bootstrap"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"client_type", "role_profile"},
     *
     *             @OA\Property(property="client_type", type="string"),
     *             @OA\Property(property="role_profile", type="string"),
     *             @OA\Property(property="model_profile", type="string", default="general-local"),
     *             @OA\Property(property="language", type="string", default="ja")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Bootstrap manifest resolved",
     *
     *         @OA\JsonContent(ref="#/components/schemas/BootstrapManifestResource")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function resolve(
        ResolveBootstrapManifestRequest $request,
        BootstrapManifestService $bootstrapManifestService,
    ): BootstrapManifestResource {
        return $this->resolveResource($request, $bootstrapManifestService);
    }

    private function resolveResource(
        ResolveBootstrapManifestRequest $request,
        BootstrapManifestService $bootstrapManifestService,
    ): BootstrapManifestResource {
        return new BootstrapManifestResource(
            $bootstrapManifestService->resolve($request->validated())
        );
    }
}
