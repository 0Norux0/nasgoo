<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;

class AttributesSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'slug' => 'color', 'name' => 'Color', 'type' => 'select',
                'name_translations' => ['ar' => 'اللون', 'ur' => 'رنگ'],
                'is_filterable' => true, 'is_variation' => true, 'position' => 1,
                'values' => [
                    ['slug' => 'red',    'value' => 'Red',    'color_hex' => '#EF4444', 'value_translations' => ['ar' => 'أحمر',  'ur' => 'سرخ']],
                    ['slug' => 'blue',   'value' => 'Blue',   'color_hex' => '#3B82F6', 'value_translations' => ['ar' => 'أزرق',  'ur' => 'نیلا']],
                    ['slug' => 'green',  'value' => 'Green',  'color_hex' => '#10B981', 'value_translations' => ['ar' => 'أخضر',  'ur' => 'سبز']],
                    ['slug' => 'black',  'value' => 'Black',  'color_hex' => '#0F172A', 'value_translations' => ['ar' => 'أسود',  'ur' => 'سیاہ']],
                    ['slug' => 'white',  'value' => 'White',  'color_hex' => '#F1F5F9', 'value_translations' => ['ar' => 'أبيض',  'ur' => 'سفید']],
                ],
            ],
            [
                'slug' => 'size', 'name' => 'Size', 'type' => 'select',
                'name_translations' => ['ar' => 'المقاس', 'ur' => 'سائز'],
                'is_filterable' => true, 'is_variation' => true, 'position' => 2,
                'values' => [
                    ['slug' => 'xs', 'value' => 'XS'],
                    ['slug' => 's',  'value' => 'S'],
                    ['slug' => 'm',  'value' => 'M'],
                    ['slug' => 'l',  'value' => 'L'],
                    ['slug' => 'xl', 'value' => 'XL'],
                ],
            ],
            [
                'slug' => 'brand', 'name' => 'Brand', 'type' => 'select',
                'name_translations' => ['ar' => 'العلامة التجارية', 'ur' => 'برانڈ'],
                'is_filterable' => true, 'is_variation' => false, 'position' => 3,
                'values' => [], // vendors add brand values themselves over time
            ],
            [
                'slug' => 'material', 'name' => 'Material', 'type' => 'select',
                'name_translations' => ['ar' => 'الخامة', 'ur' => 'مادہ'],
                'is_filterable' => true, 'is_variation' => false, 'position' => 4,
                'values' => [
                    ['slug' => 'cotton',   'value' => 'Cotton'],
                    ['slug' => 'leather',  'value' => 'Leather'],
                    ['slug' => 'metal',    'value' => 'Metal'],
                    ['slug' => 'plastic',  'value' => 'Plastic'],
                ],
            ],
        ];

        foreach ($attributes as $attrData) {
            $values = $attrData['values'] ?? [];
            unset($attrData['values']);

            /** @var Attribute $attribute */
            $attribute = Attribute::updateOrCreate(['slug' => $attrData['slug']], $attrData);

            foreach ($values as $i => $valData) {
                AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'slug' => $valData['slug']],
                    array_merge($valData, ['position' => $i]),
                );
            }
        }

        $this->command?->info(
            'Seeded ' . Attribute::count() . ' attributes with ' . AttributeValue::count() . ' values.'
        );
    }
}
