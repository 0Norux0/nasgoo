<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VendorServiceProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $providers = ServiceProvider::where('vendor_id', $vendor->id)
            ->withCount('services')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Vendor/Providers/Index', [
            'providers' => $providers->through(fn ($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'slug'           => $p->slug,
                'specialization' => $p->specialization,
                'email'          => $p->email,
                'phone'          => $p->phone,
                'is_active'      => (bool) $p->is_active,
                'services_count' => $p->services_count,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'email'          => ['nullable', 'email', 'max:200'],
            'phone'          => ['nullable', 'string', 'max:32'],
            'bio'            => ['nullable', 'string', 'max:2000'],
            'specialization' => ['nullable', 'string', 'max:200'],
            'qualification'  => ['nullable', 'string', 'max:500'],
            'is_active'      => ['boolean'],
        ]);

        $slugBase = Str::slug($data['name']);
        $slug = $slugBase;
        $n = 1;
        while (ServiceProvider::where('vendor_id', $vendor->id)->where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $n++;
        }

        ServiceProvider::create([
            'vendor_id'      => $vendor->id,
            'name'           => $data['name'],
            'slug'           => $slug,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'bio'            => $data['bio'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'qualification'  => $data['qualification'] ?? null,
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', "Provider '{$data['name']}' created.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $provider = ServiceProvider::where('vendor_id', $vendor->id)->findOrFail($id);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'email'          => ['nullable', 'email', 'max:200'],
            'phone'          => ['nullable', 'string', 'max:32'],
            'bio'            => ['nullable', 'string', 'max:2000'],
            'specialization' => ['nullable', 'string', 'max:200'],
            'qualification'  => ['nullable', 'string', 'max:500'],
            'is_active'      => ['boolean'],
        ]);

        $provider->update($data);

        return back()->with('success', "Provider '{$provider->name}' updated.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $provider = ServiceProvider::where('vendor_id', $vendor->id)->findOrFail($id);

        // Soft delete via is_active toggle — preserves historical bookings.
        $provider->update(['is_active' => false]);

        return back()->with('success', "Provider '{$provider->name}' deactivated.");
    }
}
