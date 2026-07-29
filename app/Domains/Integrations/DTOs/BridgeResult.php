<?php

declare(strict_types=1);

namespace App\Domains\Integrations\DTOs;

/**
 * نتيجة نداء واحد إلى مشروع خارجي.
 *
 * الإخفاق قيمةٌ تُعاد لا استثناءٌ يُرمى: شاشةٌ تعرض ستّ نقاط من موازين يجب أن
 * تعرض ما وصل منها وتقول ما أخفق، لا أن تسقط كلها لأن واحدة تعطّلت.
 */
final readonly class BridgeResult
{
    /** @param array<array-key, mixed>|null $data JSON قد يعود كائنًا أو قائمة */
    private function __construct(
        public bool $ok,
        public ?array $data,
        public ?string $error,
        public int $milliseconds,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function success(array $data, int $milliseconds): self
    {
        return new self(true, $data, null, $milliseconds);
    }

    public static function failure(string $error, int $milliseconds = 0): self
    {
        return new self(false, null, $error, $milliseconds);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'ok' => $this->ok,
            'data' => $this->data,
            'error' => $this->error,
            'ms' => $this->milliseconds,
        ];
    }
}
