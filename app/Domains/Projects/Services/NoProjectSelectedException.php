<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use RuntimeException;

/**
 * يُرمى حين تُطلب بيانات مشروع بلا مشروع نشط.
 *
 * حالة برمجية لا حالة مستخدم: كل مسار تشغيلي يمرّ بـ `project` middleware.
 */
final class NoProjectSelectedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('لا يوجد مشروع نشط في الجلسة.');
    }
}
