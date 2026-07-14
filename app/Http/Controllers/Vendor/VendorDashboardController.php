<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commission\CommissionResolver;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class VendorDashboardController extends Controller
{
    public function index(Request $request, CommissionResolver $resolver): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $vendor->load(['activeSubscription.package', 'commissionRules']);

        $package = $vendor->currentPackage();
        $rule    = $resolver->resolve($vendor);

        // Profile completion: count how many of the "nice to have" optional fields are filled
        $optional = ['logo_path', 'banner_path', 'description', 'license_document_path', 'id_document_path', 'tax_id', 'commercial_license_no'];
        $filled   = array_sum(array_map(fn ($f) => filled($vendor->{$f}) ? 1 : 0, $optional));
        $completion = (int) round(($filled / count($optional)) * 100);

        return Inertia::render('Vendor/Dashboard', [
            'vendor'   => [
                'id'            => $vendor->id,
                'business_name' => $vendor->business_name,
                'slug'          => $vendor->slug,
                'status'        => $vendor->status,
                'rejection_reason' => $vendor->rejection_reason,
                'logo_path'     => $vendor->logo_path,
                'created_at'    => $vendor->created_at?->toDateTimeString(),
            ],
            'package'  => $package ? [
                'name'            => $package->name,
                'slug'            => $package->slug,
                'billing_cycle'   => $package->billing_cycle,
                'analytics_level' => $package->analytics_level,
                'features'        => $package->featureFlags(),
                'limits'          => [
                    'max_products' => $package->max_products,
                    'max_services' => $package->max_services,
                    'max_images_per_product' => $package->max_images_per_product,
                ],
            ] : null,
            'subscription' => $vendor->activeSubscription ? [
                'status'   => $vendor->activeSubscription->status,
                'starts_at'=> $vendor->activeSubscription->starts_at?->toDateString(),
                'ends_at'  => $vendor->activeSubscription->ends_at?->toDateString(),
            ] : null,
            'commission' => $rule ? [
                'scope'           => $rule->scope,
                'commission_type' => $rule->commission_type,
                'percent_value'   => $rule->percent_value,
                'fixed_value_minor' => $rule->fixed_value_minor,
            ] : null,
            'profile_completion' => $completion,
        ]);
    }

    public function showProfile(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        return Inertia::render('Vendor/Profile', [
            'vendor' => [
                'id'             => $vendor->id,
                'business_name'  => $vendor->business_name,
                'business_email' => $vendor->business_email,
                'business_phone' => $vendor->business_phone,
                'description'    => $vendor->description,
                'country'        => $vendor->country,
                'city'           => $vendor->city,
                'address'        => $vendor->address,
                'logo_path'      => $vendor->logo_path,
                'banner_path'    => $vendor->banner_path,
                'payout_method'  => $vendor->payout_method,
            ],
        ]);
    }

    public function updateProfile(Request $request, AuditLogger $audit): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $data = $request->validate([
            'business_name'  => ['required', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'country'        => ['required', 'string', 'size:2'],
            'city'           => ['nullable', 'string', 'max:120'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'payout_method'  => ['nullable', 'string', 'max:60'],
            'payout_details' => ['nullable', 'array'],
            'logo'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $before = [
            'business_name' => $vendor->business_name,
            'payout_method' => $vendor->payout_method,
        ];

        // Sensitive fields handled separately (payout_details + uploaded docs are audit-logged)
        $payoutChanged = $request->input('payout_details') !== null
            && $request->input('payout_details') !== $vendor->payout_details;

        $vendor->fill(Arr::except($data, ['logo', 'banner']));

        $disk = config('filesystems.default');
        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store("vendors/{$vendor->id}", $disk);
                $vendor->{"{$field}_path"} = $path;
                $audit->log("vendor.{$field}_updated", $vendor);
            }
        }

        $vendor->save();

        if ($payoutChanged) {
            $audit->log('vendor.payout_details_updated', $vendor);
        }

        $audit->log('vendor.profile_updated', $vendor, $before, [
            'business_name' => $vendor->business_name,
            'payout_method' => $vendor->payout_method,
        ]);

        return back()->with('success', 'Profile updated.');
    }
}
