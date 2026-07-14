<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Phase 11B.3 v11B.3.2 §20 — vendor Settings page.
 *
 * ROOT CAUSE OF PRE-v11B.3.2 404:
 *   VendorSidebar (added in v11B.3.1) linked to `/vendor/settings` but no
 *   route, no controller, and no React page existed. The Settings sidebar
 *   row hit 404.
 *
 * v11B.3.2 FIX:
 *   Real controller + route + Inertia page. Landing view surfaces the
 *   vendor's store profile fields the dev already has (business_name,
 *   business_email, contact info, description). Sections that require
 *   more infrastructure (payout details, documents) render as clear
 *   placeholders per dev §20 "create a safe landing page with available
 *   settings and clear placeholders for future sections".
 */
class VendorSettingsController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $vendor = $user->vendor;
        abort_unless($vendor, 403, __('vendor.settings.not_a_vendor'));

        return Inertia::render('Vendor/Settings', [
            'vendor' => [
                'id'             => $vendor->id,
                'business_name'  => $vendor->business_name,
                'business_email' => $vendor->business_email,
                'business_type'  => $vendor->business_type,
                'country'        => $vendor->country,
                'status'         => $vendor->status,
                'description'    => $vendor->description ?? '',
                'phone'          => $vendor->phone ?? '',
                'address'        => $vendor->address ?? '',
                'logo_url'       => $vendor->logo_url ?? null,
                'website'        => $vendor->website ?? '',
            ],
            'features' => [
                'payouts_configured'   => !empty($vendor->bank_account_number ?? null),
                'documents_uploaded'   => (bool) ($vendor->documents_verified ?? false),
                'notifications_ready'  => true,  // basic email notifications always available
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $vendor = $user->vendor;
        abort_unless($vendor, 403);

        // Only editable fields — anything sensitive (status, package_id,
        // documents_verified) is admin-managed, not vendor-editable.
        $data = $request->validate([
            'business_name'  => 'required|string|min:2|max:120',
            'business_email' => 'required|email|max:120',
            'description'    => 'nullable|string|max:2000',
            'phone'          => 'nullable|string|max:40',
            'address'        => 'nullable|string|max:500',
            'website'        => 'nullable|url|max:500',
        ]);

        // Reject unsafe URLs
        if (!empty($data['website'])) {
            $normalized = strtolower(trim($data['website']));
            if (str_starts_with($normalized, 'javascript:')
                || str_starts_with($normalized, 'data:')
                || str_starts_with($normalized, 'vbscript:')) {
                return back()->withErrors(['website' => __('vendor.settings.unsafe_url')]);
            }
        }

        $vendor->update($data);

        return back()->with('flash.success', __('vendor.settings.saved'));
    }
}
