<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | جسر التكامل مع Hi-Share — وثيقة 02 §5
    |--------------------------------------------------------------------------
    */

    'hishare' => [
        'base_url' => env('HISHARE_BASE_URL', ''),
        'service_token' => env('HISHARE_SERVICE_TOKEN'),
        'hmac_secret' => env('HISHARE_HMAC_SECRET'),
        'timeout' => (int) env('HISHARE_TIMEOUT', 10),
        'retry_times' => (int) env('HISHARE_RETRY_TIMES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | الفحص الذاتي للمزودين — وثيقة التصميم §9 (الافتراضي ساعة واحدة)
    |--------------------------------------------------------------------------
    */

    'health_check' => [
        'interval_minutes' => (int) env('AI_HEALTH_CHECK_INTERVAL_MINUTES', 60),
        'timeout_seconds' => (int) env('AI_HEALTH_CHECK_TIMEOUT', 15),

        // عدد مرات الفشل المتتالية قبل اعتبار المزود متعطلًا وتفعيل التحويل.
        'failure_threshold' => (int) env('AI_HEALTH_FAILURE_THRESHOLD', 2),
    ],
];
