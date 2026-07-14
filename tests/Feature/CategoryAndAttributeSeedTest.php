<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Database\Seeders\AttributesSeeder;
use Database\Seeders\CategoriesSeeder;

beforeEach(function () {
    $this->seed(CategoriesSeeder::class);
    $this->seed(AttributesSeeder::class);
});

it('seeds 5 top-level categories with translations', function () {
    expect(Category::whereNull('parent_id')->count())->toBe(5);

    $electronics = Category::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    expect($electronics->name_translations)->toHaveKey('ar');
    expect($electronics->name_translations['ar'])->toBe('إلكترونيات');
    expect($electronics->name_translations['ur'])->toBe('الیکٹرانکس');
});

it('seeds children with proper depth and path', function () {
    $parent = Category::where('slug', 'electronics')->first();
    expect($parent->depth)->toBe(0);
    expect($parent->path)->toBe('electronics');

    $children = $parent->children;
    expect($children)->toHaveCount(3);
    $first = $children->first();
    expect($first->depth)->toBe(1);
    expect($first->path)->toStartWith('electronics/');
});

it('seeds 4 attributes with values', function () {
    expect(Attribute::count())->toBe(4);
    expect(Attribute::where('slug', 'color')->first()->is_variation)->toBeTrue();
    expect(Attribute::where('slug', 'brand')->first()->is_variation)->toBeFalse();

    $color = Attribute::where('slug', 'color')->first();
    expect($color->values)->toHaveCount(5);
    expect($color->values->where('slug', 'red')->first()->color_hex)->toBe('#EF4444');
});

it('returns translated category name for active locale', function () {
    $cat = Category::where('slug', 'electronics')->first();
    app()->setLocale('ar');
    expect($cat->translatedName())->toBe('إلكترونيات');
    app()->setLocale('en');
    expect($cat->translatedName())->toBe('Electronics');
});

it('falls back to canonical name when translation missing', function () {
    $cat = Category::factory()->create(['name' => 'Misc', 'name_translations' => null]);
    app()->setLocale('ar');
    expect($cat->translatedName())->toBe('Misc');
});

it('AttributeValue translatedValue falls back to canonical value', function () {
    $attr = Attribute::factory()->create();
    $val = AttributeValue::factory()->create(['attribute_id' => $attr->id, 'value' => 'Bronze', 'value_translations' => null]);
    expect($val->translatedValue('ar'))->toBe('Bronze');
});
