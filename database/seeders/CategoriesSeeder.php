<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            [
                'name' => 'Electronics',
                'name_translations' => ['ar' => 'إلكترونيات', 'ur' => 'الیکٹرانکس'],
                'children' => [
                    ['name' => 'Phones',      'name_translations' => ['ar' => 'هواتف',           'ur' => 'فون']],
                    ['name' => 'Laptops',     'name_translations' => ['ar' => 'حواسيب محمولة',   'ur' => 'لیپ ٹاپ']],
                    ['name' => 'Accessories', 'name_translations' => ['ar' => 'إكسسوارات',       'ur' => 'لوازمات']],
                ],
            ],
            [
                'name' => 'Fashion',
                'name_translations' => ['ar' => 'أزياء', 'ur' => 'فیشن'],
                'children' => [
                    ['name' => 'Men',       'name_translations' => ['ar' => 'رجال',  'ur' => 'مرد']],
                    ['name' => 'Women',     'name_translations' => ['ar' => 'نساء',  'ur' => 'خواتین']],
                    ['name' => 'Kids',      'name_translations' => ['ar' => 'أطفال', 'ur' => 'بچے']],
                ],
            ],
            [
                'name' => 'Home & Living',
                'name_translations' => ['ar' => 'المنزل', 'ur' => 'گھر اور زندگی'],
                'children' => [
                    ['name' => 'Kitchen',   'name_translations' => ['ar' => 'المطبخ', 'ur' => 'باورچی خانہ']],
                    ['name' => 'Furniture', 'name_translations' => ['ar' => 'أثاث',   'ur' => 'فرنیچر']],
                ],
            ],
            [
                'name' => 'Beauty',
                'name_translations' => ['ar' => 'الجمال', 'ur' => 'خوبصورتی'],
            ],
            [
                'name' => 'Sports',
                'name_translations' => ['ar' => 'رياضة', 'ur' => 'کھیل'],
            ],
        ];

        $pos = 0;
        foreach ($tree as $top) {
            $children = $top['children'] ?? [];
            unset($top['children']);

            /** @var Category $parent */
            $parent = Category::updateOrCreate(
                ['slug' => \Str::slug($top['name'])],
                array_merge($top, ['position' => $pos++, 'is_active' => true]),
            );

            $childPos = 0;
            foreach ($children as $childData) {
                Category::updateOrCreate(
                    ['slug' => \Str::slug($parent->slug . '-' . $childData['name'])],
                    array_merge($childData, [
                        'parent_id' => $parent->id,
                        'position'  => $childPos++,
                        'is_active' => true,
                    ]),
                );
            }
        }

        $this->command?->info('Seeded ' . Category::count() . ' categories (5 top-level + children).');
    }
}
