<?php
declare(strict_types=1);
namespace App\Http\Controllers\Cart;

use App\Domain\Promotion\CouponValidator;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'min:2', 'max:50']]);
        $user = $request->user();
        abort_unless($user, 401);
        $cart = Cart::firstOrCreate(['user_id' => $user->id], ['currency' => 'KWD', 'subtotal_minor' => 0, 'items_count' => 0]);
        $result = CouponValidator::validate($data['code'], $cart, $user);
        if (! $result['ok']) {
            return back()->withErrors(['code' => CouponValidator::reasonMessage($result['reason'])]);
        }
        $cart->update(['coupon_id' => $result['coupon']->id, 'discount_minor' => $result['discount_minor']]);
        return back()->with('success', CouponValidator::reasonMessage(CouponValidator::OK));
    }

    public function remove(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        $cart = Cart::where('user_id', $user->id)->first();
        if ($cart) $cart->update(['coupon_id' => null, 'discount_minor' => 0]);
        return back()->with('success', 'Coupon removed.');
    }
}
