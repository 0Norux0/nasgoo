<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('renders the welcome page', function () {
    $response = get('/');

    $response->assertOk();
});

it('exposes a health endpoint', function () {
    get('/up')->assertOk();
    get('/health')->assertOk()->assertJson(['status' => 'ok']);
});

it('exposes the api ping endpoint', function () {
    get('/api/v1/ping')
        ->assertOk()
        ->assertJson([
            'status'  => 'ok',
            'service' => 'marketplace-api',
            'version' => 'v1',
        ]);
});
