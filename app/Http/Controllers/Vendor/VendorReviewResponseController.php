<?php
declare(strict_types=1);
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VendorReviewResponseController extends Controller
{
    /**
     * POST /vendor/reviews/{review}/respond — vendor posts a public
     * response to a customer review on one of their products.
     */
    public function respond(Request $request, ProductReview $review): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);
        // Ensure review is on a product owned by this vendor
        abort_unless($review->product?->vendor_id === $vendor->id, 403);

        $data = $request->validate([
            'response' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $review->update([
            'vendor_response' => $data['response'],
            'vendor_responded_at' => Carbon::now(),
        ]);
        return back()->with('success', 'Response posted.');
    }
}
