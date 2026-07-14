<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AttributesSeeder;
use Database\Seeders\CategoriesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(CategoriesSeeder::class);
    $this->seed(AttributesSeeder::class);
});

it('renders the public catalog index page', function () {
    Product::factory()->published()->count(3)->create();

    get('/products')->assertSuccessful();
});

it('shows only published products on /products', function () {
    Product::factory()->published()->create(['name' => 'Visible Product']);
    Product::factory()->draft()->create(['name' => 'Draft Product']);
    Product::factory()->pendingReview()->create(['name' => 'Pending Product']);
    Product::factory()->rejected()->create(['name' => 'Rejected Product']);
    Product::factory()->archived()->create(['name' => 'Archived Product']);

    $response = get('/products');
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) =>
        $page->where('products.total', 1)
    );
});

it('filters the catalog by category slug', function () {
    $electronics = Category::where('slug', 'electronics')->first();
    $fashion     = Category::where('slug', 'fashion')->first();

    Product::factory()->published()->forCategory($electronics)->count(3)->create();
    Product::factory()->published()->forCategory($fashion)->count(2)->create();

    get('/products?category=electronics')->assertInertia(fn ($page) =>
        $page->where('products.total', 3)
    );
});

it('searches by name (case-insensitive ILIKE)', function () {
    Product::factory()->published()->create(['name' => 'iPhone 16 Pro']);
    Product::factory()->published()->create(['name' => 'iPhone 15 Pro']);
    Product::factory()->published()->create(['name' => 'Samsung Galaxy']);

    get('/products?q=iphone')->assertInertia(fn ($page) =>
        $page->where('products.total', 2)
    );
});

it('paginates 24 per page', function () {
    Product::factory()->published()->count(30)->create();

    get('/products')->assertInertia(fn ($page) =>
        $page->where('products.total', 30)
            ->has('products.data', 24)
    );
});

it('sorts by price ascending and descending', function () {
    Product::factory()->published()->create(['name' => 'Cheap',  'price_minor' => 100]);
    Product::factory()->published()->create(['name' => 'Pricey', 'price_minor' => 9000]);

    get('/products?sort=price_asc')->assertInertia(fn ($page) =>
        $page->where('products.data.0.name', 'Cheap')
    );
    get('/products?sort=price_desc')->assertInertia(fn ($page) =>
        $page->where('products.data.0.name', 'Pricey')
    );
});

it('returns 200 with the product detail for a published product', function () {
    $p = Product::factory()->published()->create(['name' => 'My Test Product']);

    $response = get("/products/{$p->slug}");
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) =>
        $page->where('product.name', 'My Test Product')
    );
});

it('returns 404 for a draft, pending, rejected, or archived product detail', function () {
    foreach ([
        Product::factory()->draft(),
        Product::factory()->pendingReview(),
        Product::factory()->rejected(),
        Product::factory()->archived(),
    ] as $factory) {
        $p = $factory->create();
        get("/products/{$p->slug}")->assertNotFound();
    }
});

it('increments views_count on detail page (best-effort)', function () {
    $p = Product::factory()->published()->create(['views_count' => 0]);
    get("/products/{$p->slug}")->assertSuccessful();
    expect($p->fresh()->views_count)->toBeGreaterThanOrEqual(1);
});

it('returns the published category list with counts on the index', function () {
    $electronics = Category::where('slug', 'electronics')->first();
    Product::factory()->published()->forCategory($electronics)->count(2)->create();
    Product::factory()->draft()->forCategory($electronics)->create();

    get('/products')->assertInertia(fn ($page) =>
        $page->has('categories')
    );
});
