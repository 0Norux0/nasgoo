<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $code): RedirectResponse
    {
        $supported = config('marketplace.supported_locales', ['en']);

        if (! in_array($code, $supported, true)) {
            abort(404);
        }

        $request->session()->put('locale', $code);

        // Persist on the user if they're authenticated, so it survives a
        // logout/login cycle and follows them across devices.
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $code])->save();
        }

        return back();
    }
}
