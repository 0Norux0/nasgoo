<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PersonalizationFeedback;
use App\Models\PersonalizationPreference;
use App\Services\Personalization\PersonalizationManager;
use App\Services\Personalization\RecentlyViewedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase 11B.3 §21 §23 — customer-facing privacy controls.
 *
 * Routes (see routes/web.php):
 *   GET  /account/personalization       show settings + explanation
 *   POST /account/personalization       update preferences
 *   POST /personalization/recently-viewed/clear
 *   POST /personalization/feedback      { product_id, feedback_type }
 *   POST /personalization/reset         reset all personalization data
 */
class PersonalizationController extends Controller
{
    /**
     * Settings page (dev §22 explainability).
     */
    public function settings(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $prefs = PersonalizationPreference::forUser($user);
        return Inertia::render('Account/Personalization', [
            'preferences' => $prefs,
            'flags' => [
                'personalization_enabled' =>
                    (bool) config('marketplace_personalization.features.enabled', true),
                'feedback_controls_enabled' =>
                    (bool) config('marketplace_personalization.features.feedback_controls', true),
            ],
        ]);
    }

    /**
     * Update preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'behavioral_personalization_enabled' => 'required|boolean',
            'guest_merge_enabled'                => 'required|boolean',
            'behavior_tracking_enabled'          => 'required|boolean',
        ]);

        PersonalizationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        // Wipe any cached personalized homepage for this user
        app(PersonalizationManager::class)->invalidate($user, null);

        return back()->with('flash.success', __('personalization.settings_updated'));
    }

    /**
     * Clear the caller's recently-viewed history. Guards against
     * cross-user clearing by using $request->user() (auth) or the
     * SESSION id (guest) — never a request parameter.
     */
    public function clearRecentlyViewed(Request $request): RedirectResponse
    {
        $user       = $request->user();
        $sessionKey = $user ? null : $request->session()->getId();

        app(RecentlyViewedService::class)->clear($user, $sessionKey);
        app(PersonalizationManager::class)->invalidate($user, $sessionKey);

        return back()->with('flash.success', __('personalization.recently_viewed_cleared'));
    }

    /**
     * Record a feedback event ("Not Interested" / "Hide product").
     * Applies only to the caller — the product remains visible to everyone
     * else. Vendor cannot see individual customer feedback per dev §23.
     */
    public function feedback(Request $request): RedirectResponse
    {
        if (! config('marketplace_personalization.features.feedback_controls', true)) {
            abort(403, 'Feedback controls disabled');
        }
        $data = $request->validate([
            'product_id'    => 'nullable|integer|exists:products,id',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'feedback_type' => 'required|in:not_interested,hide_product,show_fewer_like',
        ]);

        $user       = $request->user();
        $sessionKey = $user ? null : $request->session()->getId();
        $expiresAt  = now()->addDays(
            (int) config('marketplace_personalization.retention.feedback_expiry_days', 90)
        );

        PersonalizationFeedback::create([
            'user_id'       => $user?->id,
            'session_key'   => $user ? null : $sessionKey,
            'feedback_type' => $data['feedback_type'],
            'product_id'    => $data['product_id'] ?? null,
            'category_id'   => $data['category_id'] ?? null,
            'expires_at'    => $expiresAt,
        ]);

        app(PersonalizationManager::class)->invalidate($user, $sessionKey);
        return back()->with('flash.success', __('personalization.feedback_recorded'));
    }

    /**
     * Reset ALL personalization data for the caller (views + feedback +
     * preferences reverted to defaults).
     */
    public function reset(Request $request): RedirectResponse
    {
        $user       = $request->user();
        $sessionKey = $user ? null : $request->session()->getId();

        // Clear views
        app(RecentlyViewedService::class)->clear($user, $sessionKey);

        // Clear feedback (auth: user_id; guest: session_key)
        $q = PersonalizationFeedback::query();
        if ($user) $q->where('user_id', $user->id);
        elseif ($sessionKey) $q->where('session_key', $sessionKey);
        else return back();
        $q->delete();

        if ($user) {
            PersonalizationPreference::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'behavioral_personalization_enabled' => true,
                    'guest_merge_enabled'                => true,
                    'behavior_tracking_enabled'          => true,
                    'last_reset_at'                      => now(),
                ]
            );
        }

        app(PersonalizationManager::class)->invalidate($user, $sessionKey);
        return back()->with('flash.success', __('personalization.reset_complete'));
    }
}
