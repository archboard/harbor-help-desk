<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\BuildOpenApiSpec;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    /**
     * The OpenAPI 3.1 description of this API, for any OpenAPI viewer or client generator.
     */
    public function __invoke(BuildOpenApiSpec $buildOpenApiSpec): JsonResponse
    {
        return response()->json(['servers' => [['url' => url('/api/v1')]]] + $buildOpenApiSpec->handle());
    }
}
