<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class JsonApiExceptionHandler extends ExceptionHandler
{
    /**
     * Register exception handling callbacks.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleJsonApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    protected function handleJsonApiException(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'errors' => collect($e->errors())->flatMap(function ($messages, $field) {
                    return collect($messages)->map(function ($message) use ($field) {
                        return [
                            'status' => '422',
                            'title' => 'Validation Error',
                            'detail' => $message,
                            'source' => ['pointer' => '/data/attributes/' . str_replace('.', '/', $field)],
                        ];
                    });
                })->values()->all(),
            ], 422, ['Content-Type' => 'application/vnd.api+json']);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '404',
                        'title' => 'Not Found',
                        'detail' => 'The requested resource was not found.',
                    ],
                ],
            ], 404, ['Content-Type' => 'application/vnd.api+json']);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '404',
                        'title' => 'Not Found',
                        'detail' => 'The requested URL was not found.',
                    ],
                ],
            ], 404, ['Content-Type' => 'application/vnd.api+json']);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '401',
                        'title' => 'Unauthorized',
                        'detail' => 'Authentication is required to access this resource.',
                    ],
                ],
            ], 401, ['Content-Type' => 'application/vnd.api+json']);
        }

        return response()->json([
            'errors' => [
                [
                    'status' => (string) ($e->getCode() ?: 500),
                    'title' => 'Internal Server Error',
                    'detail' => app()->environment('local') ? $e->getMessage() : 'An unexpected error occurred.',
                ],
            ],
        ], 500, ['Content-Type' => 'application/vnd.api+json']);
    }
}