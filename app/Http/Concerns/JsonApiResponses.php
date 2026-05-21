<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

trait JsonApiResponses
{
    protected string $jsonApiVersion = '1.0';

    /**
     * Return a JSON:API meta response (used for delete, auth messages, etc.).
     */
    protected function jsonApiMeta(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'jsonapi' => ['version' => $this->jsonApiVersion],
            'meta' => ['message' => $message],
        ], $status, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}