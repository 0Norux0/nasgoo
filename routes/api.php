<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| REST v1 API. Phase 0 ships only the auth-test endpoint. The full surface
| (catalog, cart, checkout, vendor, orders) is built progressively from
| Phase 3 onward. The same REST layer powers the future mobile app.
*/

Route::prefix('v1')->group(function () {

    // Authenticated user (Sanctum)
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Phase 0 placeholder
    Route::get('/ping', fn () => response()->json([
        'status'  => 'ok',
        'service' => 'marketplace-api',
        'version' => 'v1',
        'phase'   => 0,
    ]));
});
