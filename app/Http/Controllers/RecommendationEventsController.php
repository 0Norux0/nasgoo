<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecommendationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 11B.2 §21 — recommendation analytics ingestion.
 *
 * Receives impression / click / add_to_cart events from the storefront
 * frontend. Privacy:
 *
 *   - session_token: SHA-256 hash of the Laravel session ID (NEVER raw)
 *   - user_id: nullable; only set for authenticated users so conversion
 *     attribution can join later (per dev §21). NEVER displayed in admin
 *     reports; reports aggregate across all users.
 *   - no IP, no UA, no email, no name, no order ID
 *
 * Rate limiting is enforced at the route level via Laravel's `throttle`
 * middleware to mitigate analytics spam (dev §33).
 */
class RecommendationEventsController extends Controller
{
    public function record(Request $request): JsonResponse
    {
        // Master feature flag — if analytics disabled, drop silently with 204
        if (! (bool) config('marketplace_recommendations.features.analytics', true)) {
            return response()->json(['ok' => true], 204);
        }

        $validator = Validator::make($request->all(), [
            'event_type' => ['required', 'in:' . implode(',', RecommendationEvent::TYPES)],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'recommended_product_id' => ['required', 'integer', 'exists:products,id'],
            'recommendation_type' => ['required', 'in:similar,fbt,also_bought,similar_service'],
            'locale' => ['nullable', 'string', 'max:8'],
            'device_category' => ['nullable', 'in:mobile,tablet,desktop'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            RecommendationEvent::create([
                'event_type'             => $data['event_type'],
                'product_id'             => $data['product_id'],
                'recommended_product_id' => $data['recommended_product_id'],
                'recommendation_type'    => $data['recommendation_type'],
                'locale'                 => $data['locale'] ?? app()->getLocale(),
                'device_category'        => $data['device_category'] ?? null,
                // Hash the Laravel session ID, never store raw
                'session_token'          => RecommendationEvent::hashSession($request->session()->getId()),
                'user_id'                => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            // Analytics failures must NEVER affect customer experience
            \Log::warning('v11B.2 analytics event drop', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
