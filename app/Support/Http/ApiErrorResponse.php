<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * The one JSON error shape used by every API response: code, message, details,
 * request_id (وثيقة 03 §7).
 */
final class ApiErrorResponse
{
    public static function fromThrowable(Throwable $e, bool $debug): JsonResponse
    {
        [$status, $code, $message, $details] = self::classify($e);

        $body = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => RequestId::current(),
            ],
        ];

        // Internal failures never leak their message to the client; the
        // request_id above is what links the caller's report to the real cause.
        if ($debug && $status >= 500) {
            $body['error']['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ];
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @return array{int, string, string, array<string, mixed>}
     */
    private static function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                422,
                'validation_failed',
                'البيانات المرسلة غير صالحة.',
                ['fields' => $e->errors()],
            ],
            $e instanceof AuthenticationException => [
                401,
                'unauthenticated',
                'الجلسة غير مصادق عليها.',
                [],
            ],
            $e instanceof AuthorizationException => [
                403,
                'forbidden',
                'لا تملك صلاحية تنفيذ هذا الإجراء.',
                [],
            ],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                404,
                'not_found',
                'المورد المطلوب غير موجود.',
                [],
            ],
            $e instanceof TooManyRequestsHttpException => [
                429,
                'rate_limited',
                'تجاوزت الحد المسموح من الطلبات.',
                [],
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                'http_error',
                'تعذر إتمام الطلب.',
                [],
            ],
            default => [
                500,
                'internal_error',
                'حدث خطأ غير متوقع. تم تسجيل الحادث.',
                [],
            ],
        };
    }
}
