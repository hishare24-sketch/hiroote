<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Support\Http\RequestId;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Tap that stamps `request_id` onto every record, including those written from
 * queued jobs and console commands where no middleware ran (وثيقة 05 §5).
 */
final class AddRequestContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            if (isset($record->extra['request_id'])) {
                return $record;
            }

            return $record->with(extra: [...$record->extra, 'request_id' => RequestId::current()]);
        });
    }
}
