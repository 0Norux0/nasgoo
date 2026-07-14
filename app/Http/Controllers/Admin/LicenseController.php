<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Licensing\LicenseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 12.3 — admin license activation UI + public status endpoint.
 *
 * Routes:
 *   GET  /admin/license          → admin.license.index
 *   POST /admin/license/activate → admin.license.activate
 *   GET  /license/status         → license.status (public, minimal)
 */
class LicenseController extends Controller
{
    public function __construct(private readonly LicenseManager $license)
    {
    }

    /**
     * Admin activation UI. Access limited to super-admin — but reachable
     * even when the license is expired, so the owner can always fix it.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/License/Index', [
            'status' => $this->license->status(),
        ]);
    }

    /**
     * Activate a pasted token.
     */
    public function activate(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'token' => 'required|string|min:32|max:8192',
        ]);

        $result = $this->license->activate($data['token'], $request, $request->user()?->id);

        if (! $result['ok']) {
            $reasonMap = [
                'bad_format'           => 'The token format is not recognised.',
                'bad_header'           => 'The token header is not accepted.',
                'bad_signature'        => 'The token signature does not verify against the installed public key.',
                'expired'              => 'The token has already expired.',
                'domain_mismatch'      => 'The token was issued for a different domain.',
                'fingerprint_mismatch' => 'The token was issued for a different server fingerprint.',
                'no_public_key'        => 'No public key is installed. Set LICENSE_PUBLIC_KEY in .env.',
                'max_days_exceeded'    => 'The token claims a duration longer than the configured maximum.',
                'schema_missing'       => 'The license tables have not been migrated yet.',
            ];
            $userFacing = $reasonMap[$result['status']] ?? 'The token could not be activated.';

            return Redirect::route('admin.license.index')
                ->with('license_error', $userFacing);
        }

        return Redirect::route('admin.license.index')
            ->with('license_success', 'License activated successfully.');
    }

    /**
     * Public license-status endpoint. Shows minimal info: whether the
     * marketplace is available. Reveals no fingerprint, no expiry date,
     * no license holder.
     */
    public function publicStatus(Request $request): Response
    {
        $status = $this->license->status();

        return Inertia::render('License/Status', [
            'available' => in_array($status['status'], ['active', 'grace', 'unconfigured'], true),
            'reason'    => $status['status'] === 'expired'
                ? 'The marketplace license has expired. The site owner has been notified.'
                : ($status['status'] === 'unlicensed'
                    ? 'The marketplace is not yet activated.'
                    : null),
        ]);
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin'),
            403, 'Only super-admin can manage the license.');
    }
}
