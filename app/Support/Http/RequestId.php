<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Support\Str;

/**
 * The correlation id that ties an HTTP request to its logs, audit entries and
 * error responses (وثيقة 03 §7، وثيقة 05 §5).
 *
 * Held in a static rather than the container so queued jobs and console
 * commands — which have no request instance — can still stamp a value.
 */
final class RequestId
{
    private static ?string $id = null;

    public static function current(): string
    {
        return self::$id ??= (string) Str::ulid();
    }

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function reset(): void
    {
        self::$id = null;
    }
}
