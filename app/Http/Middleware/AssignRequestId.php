<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every request with a correlation id and echoes it back on the
 * response so a user-reported error can be traced to its logs (وثيقة 05 §5).
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        RequestId::reset();

        // An inbound id is honoured only if it is a plausible identifier;
        // otherwise a caller could inject arbitrary text into every log line.
        $inbound = $request->header(self::HEADER);
        $id = is_string($inbound) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1
            ? $inbound
            : (string) Str::ulid();

        RequestId::set($id);
        $request->headers->set(self::HEADER, $id);

        Log::shareContext([
            'request_id' => $id,
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
