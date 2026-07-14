<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 11B.4 v11B.4.3 Fix 2 — vendor intelligence digest email.
 *
 * Sent by SendVendorIntelligenceDigest job after
 * `vendor-intelligence:generate --send-emails` (or a future scheduled
 * trigger). One digest per vendor per throttle window (default 24h).
 *
 * PII discipline (per directive §4 "do not include customer names,
 * customer emails, payment data, or private customer-level
 * information"): the payload is entirely marketplace-side aggregates
 * (counters + top alert titles). No order rows, no customer rows, no
 * order_items pass through this Mailable.
 *
 * Locale: the vendor's user->preferred_locale (falling back to app
 * default) is set on the Mailable so the Blade template's __() calls
 * resolve into en.json or ar.json. The class does not force a locale;
 * the caller does via Mail::to()->locale($vendor->user->locale)
 * if needed.
 */
class VendorIntelligenceDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Vendor  $vendor
     * @param  array{
     *     summary: array<string,mixed>,
     *     top_alerts: list<array{alert_type:string, priority:string, evidence:array<string,mixed>}>,
     *     dashboard_url: string,
     * }  $data
     */
    public function __construct(
        public readonly Vendor $vendor,
        public readonly array $data,
    ) {}

    public function envelope(): Envelope
    {
        // Subject deliberately unspecific — matches directive §4 example
        return new Envelope(
            subject: __('vendor_intelligence.digest.subject', [
                'store' => $this->vendor->business_name ?? 'Vendor',
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.vendor-intelligence-digest',
            with: [
                'vendor'       => $this->vendor,
                'summary'      => $this->data['summary'] ?? [],
                'topAlerts'    => $this->data['top_alerts'] ?? [],
                'dashboardUrl' => $this->data['dashboard_url'] ?? url('/vendor'),
            ],
        );
    }
}
