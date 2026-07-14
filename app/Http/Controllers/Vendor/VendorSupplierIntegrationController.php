<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SupplierIntegration;
use App\Models\SupplierPlatform;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 6 — vendor manages their own supplier integrations.
 *
 * Vendors can only see their own integrations (scoped by vendor_id at the
 * query layer). Credentials are stored encrypted via the model cast; the UI
 * only shows masked values, never the raw secret after submission.
 */
class VendorSupplierIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $integrations = $vendor->supplierIntegrations()
            ->with('platform:id,name,slug,integration_type')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SupplierIntegration $i) => [
                'id'                => $i->id,
                'name'              => $i->name,
                'platform'          => $i->platform?->name,
                'integration_type'  => $i->integration_type,
                'is_active'         => $i->is_active,
                'last_synced_at'    => $i->last_synced_at?->toDateTimeString(),
                'last_sync_status'  => $i->last_sync_status,
                'masked_credentials' => $i->maskedCredentials(),
            ]);

        return Inertia::render('Vendor/Supplier/Integrations/Index', [
            'integrations' => $integrations,
            'platforms' => SupplierPlatform::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'integration_type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');

        $data = $request->validate([
            'supplier_platform_id' => 'required|exists:supplier_platforms,id',
            'name'                 => 'required|string|max:120',
            'integration_type'     => 'required|in:manual,csv,api,feed',
            'feed_url'             => 'nullable|url|max:1024',
            'is_active'            => 'nullable|boolean',
            // Optional credential bag; will be encrypted at rest by the model cast.
            'credentials'          => 'nullable|array|max:10',
            'credentials.*'        => 'nullable|string|max:500',
        ]);

        $integration = $vendor->supplierIntegrations()->create([
            'supplier_platform_id' => $data['supplier_platform_id'],
            'name'                 => $data['name'],
            'integration_type'     => $data['integration_type'],
            'feed_url'             => $data['feed_url'] ?? null,
            'is_active'            => (bool) ($data['is_active'] ?? true),
            'credentials'          => $data['credentials'] ?? null,
        ]);

        return redirect()->route('vendor.supplier-integrations.index')
            ->with('success', "Integration \"{$integration->name}\" created.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $integration = $vendor->supplierIntegrations()->findOrFail($id);

        $data = $request->validate([
            'name'             => 'sometimes|required|string|max:120',
            'integration_type' => 'sometimes|required|in:manual,csv,api,feed',
            'feed_url'         => 'nullable|url|max:1024',
            'is_active'        => 'nullable|boolean',
            'credentials'      => 'nullable|array|max:10',
            'credentials.*'    => 'nullable|string|max:500',
        ]);

        $integration->update($data);

        return back()->with('success', 'Integration updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('vendor');
        $integration = $vendor->supplierIntegrations()->findOrFail($id);
        $name = $integration->name;
        $integration->delete();

        return redirect()->route('vendor.supplier-integrations.index')
            ->with('success', "Integration \"{$name}\" removed.");
    }
}
