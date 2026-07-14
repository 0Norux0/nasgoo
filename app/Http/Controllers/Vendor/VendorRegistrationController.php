<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorRegistrationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // If they already applied, send them to their dashboard
        if ($user->vendor()->exists()) {
            return redirect('/vendor');
        }

        return Inertia::render('Vendor/Apply', [
            'packages' => VendorPackage::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'price_minor', 'currency',
                       'billing_cycle', 'max_products', 'allow_video', 'allow_3d',
                       'allow_services', 'allow_dropshipping', 'default_admin_commission_percent']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user->vendor()->exists()) {
            return redirect('/vendor');
        }

        $data = $request->validate([
            // Identity
            'business_name'          => ['required', 'string', 'max:255'],
            'business_email'         => ['required', 'email', 'max:255'],
            'business_phone'         => ['nullable', 'string', 'max:40'],
            'business_type'          => ['required', Rule::in(['individual', 'company'])],
            'description'            => ['nullable', 'string', 'max:2000'],

            // Owner
            'owner_name'             => ['nullable', 'string', 'max:255'],
            'owner_email'            => ['nullable', 'email', 'max:255'],
            'owner_phone'            => ['nullable', 'string', 'max:40'],

            // Location
            'country'                => ['required', 'string', 'size:2'],
            'city'                   => ['nullable', 'string', 'max:120'],
            'address'                => ['nullable', 'string', 'max:1000'],

            // Legal
            'commercial_license_no'  => ['nullable', 'string', 'max:120'],
            'tax_id'                 => ['nullable', 'string', 'max:120'],
            'civil_id'               => ['nullable', 'string', 'max:120'],

            // Payout
            'payout_method'          => ['nullable', 'string', 'max:60'],
            'payout_details'         => ['nullable', 'array'],

            // Package selection
            'vendor_package_id'      => ['required', 'integer', Rule::exists('vendor_packages', 'id')->where('is_active', true)],

            // Files (validated separately below)
            'logo'                   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner'                 => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'license_document'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'id_document'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            // T&C
            'agree_terms'            => ['accepted'],
        ]);

        $vendor = Vendor::create([
            'user_id'              => $user->id,
            'business_name'        => $data['business_name'],
            'business_email'       => $data['business_email'],
            'business_phone'       => $data['business_phone'] ?? null,
            'business_type'        => $data['business_type'],
            'description'          => $data['description'] ?? null,
            'owner_name'           => $data['owner_name'] ?? $user->name,
            'owner_email'          => $data['owner_email'] ?? $user->email,
            'owner_phone'          => $data['owner_phone'] ?? null,
            'country'              => strtoupper($data['country']),
            'city'                 => $data['city'] ?? null,
            'address'              => $data['address'] ?? null,
            'commercial_license_no'=> $data['commercial_license_no'] ?? null,
            'tax_id'               => $data['tax_id'] ?? null,
            'civil_id'             => $data['civil_id'] ?? null,
            'payout_method'        => $data['payout_method'] ?? null,
            'payout_details'       => $data['payout_details'] ?? null,
            'status'               => Vendor::STATUS_PENDING,
        ]);

        // Persist requested-package as a 'pending' subscription (admin upgrades on approval)
        \App\Models\VendorSubscription::create([
            'vendor_id'         => $vendor->id,
            'vendor_package_id' => $data['vendor_package_id'],
            'starts_at'         => now(),
            'status'            => \App\Models\VendorSubscription::STATUS_PENDING,
            'amount_paid_minor' => 0,
            'currency'          => 'KWD',
        ]);

        // Phase 10 v10.7 — file uploads route by kind to the correct disk.
        // Pre-v10.7 all 4 fields went to config('filesystems.default') = 'local'.
        // That worked for license/ID (private, also reachable via the
        // 'vendors' disk which shares the same root) but BROKE logo/banner
        // because the public-URL preview logic reads from the 'public' disk
        // — files weren't there, so the admin saw "File not found" on every
        // image. v10.7:
        //   logo, banner            → vendor_public_disk  (default 'public')
        //   license_document, id_document → vendor_private_disk (default 'vendors')
        $publicDisk  = (string) config('marketplace.vendor_public_disk',  'public');
        $privateDisk = (string) config('marketplace.vendor_private_disk', 'vendors');
        foreach ([
            'logo'             => $publicDisk,
            'banner'           => $publicDisk,
            'license_document' => $privateDisk,
            'id_document'      => $privateDisk,
        ] as $field => $disk) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store("vendors/{$vendor->id}", $disk);
                $column = $field . '_path';
                $vendor->{$column} = $path;
            }
        }
        $vendor->save();

        $audit->log(
            action: 'vendor.application_submitted',
            subject: $vendor,
            after: ['status' => $vendor->status, 'business_name' => $vendor->business_name],
        );

        return redirect('/vendor')->with('success', 'Application submitted. Our team will review it shortly.');
    }
}
