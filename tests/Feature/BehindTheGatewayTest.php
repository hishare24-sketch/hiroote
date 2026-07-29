<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * خلف البوّابة يبقى الزائر معروفًا، ويبقى الاتصال آمنًا.
 *
 * بلا ثقةٍ بالبوّابة يُسجَّل كل زائر بعنوانها — فيخنق زائرٌ واحد نشط الجميعَ،
 * ويسمّي سجلُّ التدقيق البوّابةَ فاعلًا في كل قيد. ويظنّ لارافل الاتصال غير
 * آمن فيبني روابط `http` داخل صفحةٍ على `https`.
 */
class BehindTheGatewayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_visitors_own_address_survives_the_gateway(): void
    {
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.5',            // حاوية البوّابة
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',  // الزائر نفسه
        ]);

        $this->assertSame('203.0.113.9', $this->through($request)->ip());
    }

    #[Test]
    public function https_at_the_gateway_is_https_in_the_app(): void
    {
        $request = Request::create('http://hiroote.test/', 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $this->assertTrue($this->through($request)->isSecure());
    }

    #[Test]
    public function a_public_address_cannot_claim_to_be_the_gateway(): void
    {
        // لو انكشف منفذ التطبيق يومًا، ترويسةٌ منتحَلة من الإنترنت تجعل كل
        // زائرٍ يختار العنوان الذي يُسجَّل به — فيتجاوز الخنق وينتحل في التدقيق.
        $request = Request::create('/', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.200',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.1',
        ]);

        $this->assertSame('203.0.113.200', $this->through($request)->ip());
    }

    private function through(Request $request): Request
    {
        $this->app->handle($request);

        return $request;
    }
}
