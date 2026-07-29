<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domains\Administration\Models\User;
use App\Domains\Conversations\Models\Conversation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * البذرة تُشغَّل مرتين في كل جهاز تطوير: مرة عند التهيئة، ومرة بعد كل سحب
 * يجلب جداول جديدة. سقوطها في الثانية يعني أن من هاجر قاعدته يخسر بذور
 * الموجة الجديدة كلها بسبب مستخدمٍ موجود من الأولى.
 */
class SeedingIsRepeatableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeding_twice_neither_fails_nor_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::query()->count();
        $conversations = Conversation::query()->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::query()->count());

        // والحركة التجريبية لا تتضاعف: رقمٌ مضاعَف يُقرأ قياسًا لا خطأ زرع.
        $this->assertSame($conversations, Conversation::query()->count());
    }
}
