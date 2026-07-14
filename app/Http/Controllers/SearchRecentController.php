<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Search\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11B.1 §11 — DELETE /search/recent.
 *
 * Authenticated user clears their own recent-search history. The middleware
 * stack on the route (`auth`) ensures unauthenticated requests are
 * rejected before reaching this controller. The service layer enforces
 * user-scoping so a user CANNOT clear another user's history even if they
 * somehow reached this handler with a forged user identifier.
 */
class SearchRecentController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $analytics,
    ) {
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            // Defense-in-depth: route middleware should have caught this.
            return response()->json(['deleted' => 0, 'error' => 'unauthenticated'], 401);
        }

        $locale = $request->query('locale');
        if ($locale !== null && ! in_array($locale, config('marketplace.supported_locales', ['en']), true)) {
            return response()->json(['deleted' => 0, 'error' => 'unsupported_locale'], 422);
        }

        $deleted = $this->analytics->clearRecentForUser($user, $locale);

        return response()->json(['deleted' => $deleted]);
    }
}
