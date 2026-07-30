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

    #[Test]
    public function asset_urls_are_https_when_the_gateway_terminated_tls(): void
    {
        /*
         * هذا هو ما سقط في الإنتاج فعلًا ٢٠٢٦-٠٧-٣٠، ولم يكشفه اختبارٌ.
         *
         * ظنّ لارافل الاتصال `http` فبنى روابط الأصول بـ`http` داخل صفحةٍ على
         * `https`، فحجبها المتصفح (Mixed Content) — فلا JS ولا CSS، **وشاشة
         * بيضاء بلا خطأ في أي سجلّ خادم**: كل الطلبات ٢٠٠، والصفحة تصل، والأصول
         * تصل، ولا يُرسم شيء. وهو أخفى ما يمكن أن يقع.
         */
        $request = Request::create('http://hiroote.com/login', 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_HOST' => 'hiroote.com',
        ]);

        $this->app->handle($request);

        $this->assertStringStartsWith('https://', asset('build/app.js'));
        $this->assertStringStartsWith('https://', url('/login'));
    }

    #[Test]
    public function nginx_does_not_rewrite_the_client_address(): void
    {
        /*
         * حارسٌ على الإعداد لا على الكود، لأن العطل كان بينهما.
         *
         * `real_ip_header` في nginx يستبدل `REMOTE_ADDR` بعنوانٍ **عامّ**، ولارافل
         * يثق بالنطاقات الخاصة وحدها — فيسقط عن الثقة كل طلبٍ، وتُتجاهل
         * `X-Forwarded-Proto`. حارسان بُنيا لسببين صحيحين يُبطل أحدهما الآخر،
         * ولا يظهر أثرهما إلا في متصفّحٍ على HTTPS حقيقي.
         *
         * فاستخراج عنوان الزائر في **موضع واحد**: لارافل بعد أن يثق بالبوّابة.
         */
        // التعليقات تُطرح: الملف يشرح النهي عن هذا التوجيه، فحارسٌ يقرأ النثر
        // يسقط على الشرح نفسه ويصير عبئًا لا حراسة.
        $directives = implode("\n", array_filter(
            explode("\n", (string) file_get_contents(base_path('docker/production/nginx.conf'))),
            static fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
        ));

        $this->assertStringNotContainsString(
            'real_ip_header',
            $directives,
            'أُعيد real_ip_header إلى nginx — يستبدل REMOTE_ADDR بعنوانٍ عامّ فيُبطل '
            .'حدَّ الثقة في bootstrap/app.php، فتُبنى روابط الأصول بـhttp داخل صفحة https '
            .'ويحجبها المتصفح: شاشة بيضاء بلا خطأ في أي سجلّ.',
        );
    }

    private function through(Request $request): Request
    {
        $this->app->handle($request);

        return $request;
    }
}
