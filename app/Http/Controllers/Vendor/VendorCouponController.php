<?php
declare(strict_types=1);
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorCouponController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        return Inertia::render('Vendor/Coupons/Index', [
            'coupons' => Coupon::where('vendor_id', $vendor->id)->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vendor/Coupons/Edit', ['coupon' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        $data = $this->validateInput($request);
        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = $request->user()->id;
        Coupon::create($data);
        return redirect('/vendor/coupons')->with('success', 'Coupon created.');
    }

    public function edit(Request $request, Coupon $coupon): Response
    {
        $this->authorizeOwn($request, $coupon);
        return Inertia::render('Vendor/Coupons/Edit', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorizeOwn($request, $coupon);
        $coupon->update($this->validateInput($request));
        return redirect('/vendor/coupons')->with('success', 'Coupon updated.');
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->authorizeOwn($request, $coupon);
        $coupon->delete();
        return redirect('/vendor/coupons')->with('success', 'Coupon deleted.');
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:50'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:percentage,fixed_amount'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'max_discount_minor' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
    }

    private function authorizeOwn(Request $request, Coupon $coupon): void
    {
        abort_unless($coupon->vendor_id === $request->user()->vendor?->id, 403);
    }
}
