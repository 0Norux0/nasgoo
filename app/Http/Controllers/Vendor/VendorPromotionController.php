<?php
declare(strict_types=1);
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VendorPromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        $promotions = Promotion::where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(20);
        return Inertia::render('Vendor/Promotions/Index', ['promotions' => $promotions]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vendor/Promotions/Edit', [
            'promotion' => null,
            'types' => ['flash_sale', 'limited_time', 'product_specific', 'vendor'],
            'discountTypes' => ['percentage', 'fixed_amount'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        $data = $this->validateInput($request);
        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = $request->user()->id;
        // Vendor promotions need admin approval
        $data['approval_status'] = Promotion::APPROVAL_PENDING;
        $data['slug'] = Str::slug($data['title']) . '-' . Str::lower(Str::random(6));
        Promotion::create($data);
        return redirect('/vendor/promotions')->with('success', 'Promotion submitted for approval.');
    }

    public function edit(Request $request, Promotion $promotion): Response
    {
        $this->authorizeOwn($request, $promotion);
        return Inertia::render('Vendor/Promotions/Edit', [
            'promotion' => $promotion,
            'types' => ['flash_sale', 'limited_time', 'product_specific', 'vendor'],
            'discountTypes' => ['percentage', 'fixed_amount'],
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizeOwn($request, $promotion);
        $promotion->update($this->validateInput($request));
        return redirect('/vendor/promotions')->with('success', 'Promotion updated.');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizeOwn($request, $promotion);
        $promotion->delete();
        return redirect('/vendor/promotions')->with('success', 'Promotion deleted.');
    }

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'promotion_type' => ['required', 'string', 'max:30'],
            'discount_type' => ['required', 'in:percentage,fixed_amount'],
            'discount_value' => ['required', 'integer', 'min:0', 'max:10000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'per_customer_limit' => ['nullable', 'integer', 'min:0'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'max_discount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
    }

    private function authorizeOwn(Request $request, Promotion $promotion): void
    {
        abort_unless($promotion->vendor_id === $request->user()->vendor?->id, 403);
    }
}
