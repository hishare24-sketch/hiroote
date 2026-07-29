<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** @var class-string<Project> */
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'api_base_url' => 'https://api.'.fake()->domainWord().'.test/v1',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * المشروع الافتراضي الذي تنتمي إليه البيانات حين لا يحدَّد غيره.
     *
     * `firstOrCreate` لا ذاكرة ساكنة: `RefreshDatabase` يتراجع عن كل معاملة بين
     * اختبار وآخر، فمعرّفٌ محفوظ في متغير ساكن يشير إلى صفٍّ لم يعد موجودًا.
     */
    public static function default(): Project
    {
        return Project::query()->firstOrCreate(
            ['slug' => 'hi-share'],
            [
                'name' => 'Hi-Share',
                'description' => 'المشروع الأول في مركز التحكم.',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
