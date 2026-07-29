<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * صورة الشاشة تُقرأ من المسار الذي تُنشر عليه.
 *
 * رُصد على جهاز المالك: القرص **الخاص** كان يحمل `serve => true` وبلا `url`،
 * فيسجّل لارافل `GET /storage/{path}` له هو — وهو نفس المسار الذي تُنشر عليه
 * صور الشاشات من القرص العام. فحين يغيب الرابط الرمزي `public/storage` يقرأ
 * لارافل من `storage/app/private` ويردّ ٤٠٣ لأن الخاص يشترط توقيعًا.
 *
 * والصورة تُرفع وتُحفظ وتظهر مكسورة، ولا شيء يقول لماذا — وهذا أسوأ من رفضٍ
 * صريح وقت الرفع.
 */
class ScreenImageIsReachableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_public_file_is_served_without_a_signature(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('screens/1/shot.png', 'binary');

        $this->get('/storage/screens/1/shot.png')->assertOk();
    }

    #[Test]
    public function the_private_disk_is_not_reachable_over_http(): void
    {
        // جذرُ الخاص ليس للنشر: تقديمُه على مسارٍ عام يجعل كل ملفٍ فيه رهنَ
        // صحّة توقيعٍ بدل أن يكون خارج المتناول أصلًا.
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
        $this->assertTrue((bool) config('filesystems.disks.public.serve'));
    }

    #[Test]
    public function an_uploaded_screen_image_lands_where_its_url_points(): void
    {
        Storage::fake('public');

        $stored = UploadedFile::fake()
            ->image('screen.png')
            ->store('screens/9', 'public');

        $this->assertIsString($stored);
        Storage::disk('public')->assertExists($stored);

        // نفس المسار الذي يبنيه `KnowledgeScreen::imageUrl()`.
        $this->get('/storage/'.$stored)->assertOk();
    }
}
