<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 10 — robots.txt.
 *
 * Allow: public catalog, service catalog, individual product/service pages,
 *        deals, categories.
 * Disallow: admin/*, vendor/* (dashboard side, not /products/{slug:vendor-...}),
 *           account/*, orders, bookings, tickets, cart, checkout, login, register,
 *           password-reset paths.
 *
 * The Sitemap directive points at the live /sitemap.xml so crawlers find it.
 *
 * Served as a route (not a static file) because the sitemap URL needs to
 * reflect the runtime app.url (different in dev/staging/prod).
 */
class RobotsController extends Controller
{
    public function index(): Response
    {
        $sitemap = url('/sitemap.xml');

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Phase 10 — block admin + vendor + customer-only surfaces',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /vendor',
            'Disallow: /vendor/',
            'Disallow: /account',
            'Disallow: /orders',
            'Disallow: /orders/',
            'Disallow: /bookings',
            'Disallow: /bookings/',
            'Disallow: /tickets',
            'Disallow: /tickets/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password',
            'Disallow: /password/',
            'Disallow: /email/verify',
            'Disallow: /wishlist',
            '',
            "Sitemap: {$sitemap}",
        ];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
