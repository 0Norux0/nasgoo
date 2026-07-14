<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorIntelligence\VendorIntelligenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 11B.4 §24 §33 — vendor intelligence controller.
 *
 * CRITICAL: every action reads $request->user()->vendor to derive the
 * vendor identity. Never trusts a request-body vendor_id. Vendor A can
 * NEVER see or modify vendor B's alerts. This is the same pattern used
 * in v11B.3.2 VendorSettingsController.
 */
class VendorIntelligenceController extends Controller
{
    public function __construct(
        private readonly VendorIntelligenceManager $manager,
    ) {}

    /**
     * JSON endpoint: full dashboard payload for the authenticated vendor.
     * Consumed by the vendor dashboard React component.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->requireVendor($request);

        // Phase 11B.4 v11B.4.2 Defect 4 fix — feature-flag enforcement.
        // When disabled, return a well-formed JSON payload with a
        // top-level `enabled: false` flag so the React panel can render
        // a disabled state instead of guessing from a 503.
        if (! $this->manager->isEnabled()) {
            return response()->json([
                'enabled' => false,
                'reason' => 'feature_disabled',
            ]);
        }

        $locale = app()->getLocale();
        $payload = $this->manager->dashboardFor($vendor, $locale);
        $payload['enabled'] = true;
        return response()->json($payload);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $vendor = $this->requireVendor($request);

        // v11B.4.2 Defect 4 fix — mutating actions must reject when disabled.
        if (! $this->manager->isEnabled()) {
            abort(403, __('vendor_intelligence.feature_disabled'));
        }

        $data = $request->validate([
            'suggestion_type' => 'required|string|max:64',
            'entity_type'     => 'required|string|max:32',
            'entity_id'       => 'nullable|integer',
        ]);

        $this->manager->dismissSuggestion(
            $vendor,
            $data['suggestion_type'],
            $data['entity_type'],
            $data['entity_id'] ?? null
        );

        return back()->with('flash.success', __('vendor_intelligence.suggestion_dismissed'));
    }

    public function snooze(Request $request): RedirectResponse
    {
        $vendor = $this->requireVendor($request);

        // v11B.4.2 Defect 4 fix — mutating actions must reject when disabled.
        if (! $this->manager->isEnabled()) {
            abort(403, __('vendor_intelligence.feature_disabled'));
        }

        $data = $request->validate([
            'suggestion_type' => 'required|string|max:64',
            'entity_type'     => 'required|string|max:32',
            'entity_id'       => 'nullable|integer',
            'days'            => 'nullable|integer|min:1|max:90',
        ]);

        $this->manager->snoozeSuggestion(
            $vendor,
            $data['suggestion_type'],
            $data['entity_type'],
            $data['entity_id'] ?? null,
            $data['days'] ?? null
        );

        return back()->with('flash.success', __('vendor_intelligence.suggestion_snoozed'));
    }

    private function requireVendor(Request $request): \App\Models\Vendor
    {
        $user = $request->user();
        abort_unless($user, 401);
        $vendor = $user->vendor;
        abort_unless($vendor, 403, __('vendor.settings.not_a_vendor'));

        // v11B.4.2 Defect 1 fix — defense in depth. Route middleware
        // 'vendor:approved' already blocks pending/rejected/suspended,
        // but recheck at controller level so any middleware
        // misconfiguration doesn't silently expose data.
        abort_unless($vendor->status === \App\Models\Vendor::STATUS_APPROVED, 403,
            __('vendor_intelligence.vendor_not_approved'));

        return $vendor;
    }
}
