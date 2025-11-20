<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Standardized API response trait for consistent error handling
 *
 * Usage: Add "use ApiResponse;" to any controller
 *
 * Methods:
 * - successResponse($data, $message, $statusCode): Standard success response
 * - errorResponse($message, $statusCode, $errors): Standard error response
 * - validationErrorResponse($validator): Validation error response
 * - notFoundResponse($message): 404 response
 * - unauthorizedResponse($message): 403 response
 * - handleException($exception, $context): Centralized exception handling
 */
trait ApiResponse
{
    /**
     * Return a success JSON response
     */
    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response
     */
    protected function errorResponse(string $message, int $statusCode = 500, $errors = null, array $context = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Log error with context
        if ($statusCode >= 500) {
            Log::error($message, array_merge(['status_code' => $statusCode], $context));
        } elseif ($statusCode >= 400) {
            Log::warning($message, array_merge(['status_code' => $statusCode], $context));
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response
     */
    protected function validationErrorResponse($validator, string $message = 'Validation failed'): JsonResponse
    {
        $errors = is_object($validator) ? $validator->errors() : $validator;

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * Return a not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized access'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return a bad request response
     */
    protected function badRequestResponse(string $message = 'Bad request', $errors = null): JsonResponse
    {
        return $this->errorResponse($message, 400, $errors);
    }

    /**
     * Centralized exception handling
     *
     * @param \Exception $exception The exception to handle
     * @param array $context Additional context for logging
     * @param string $defaultMessage Default message if no specific handler matches
     * @return JsonResponse
     */
    protected function handleException(\Exception $exception, array $context = [], string $defaultMessage = 'An error occurred'): JsonResponse
    {
        // Add exception details to context
        $context = array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        // Handle specific exception types
        if ($exception instanceof ValidationException) {
            return $this->validationErrorResponse($exception->errors(), $exception->getMessage());
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return $this->notFoundResponse($exception->getMessage() ?: 'Resource not found');
        }

        if ($exception instanceof UnauthorizedHttpException) {
            return $this->unauthorizedResponse($exception->getMessage() ?: 'Unauthorized access');
        }

        // Database exceptions
        if ($exception instanceof \Illuminate\Database\QueryException) {
            Log::error('Database query error', array_merge($context, [
                'sql' => $exception->getSql() ?? null,
                'error' => $exception->getMessage()
            ]));

            return $this->errorResponse(
                'Database error occurred',
                500,
                config('app.debug') ? $exception->getMessage() : null,
                $context
            );
        }

        // Default exception handling
        Log::error($defaultMessage, array_merge($context, [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]));

        return $this->errorResponse(
            $defaultMessage,
            $exception->getCode() >= 100 && $exception->getCode() < 600 ? $exception->getCode() : 500,
            config('app.debug') ? $exception->getMessage() : null,
            $context
        );
    }

    /**
     * Return a created response (201)
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return an accepted response (202)
     */
    protected function acceptedResponse(string $message = 'Request accepted for processing'): JsonResponse
    {
        return $this->successResponse(null, $message, 202);
    }

    /**
     * Return a no content response (204)
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
