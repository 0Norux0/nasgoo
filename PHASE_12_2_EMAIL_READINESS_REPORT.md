# Phase 12.2 — Email Readiness Report

Verifies email infrastructure for production. Grounded in the actual Mail classes shipped with the codebase.

## Mailables in this codebase

Enumerated from `app/Mail/` and `resources/views/emails/`:

| Class | Template | Purpose |
| --- | --- | --- |
| `App\Mail\VendorIntelligenceDigestMail` | `resources/views/emails/vendor-intelligence-digest.blade.php` | Vendor intelligence digest (Phase 11B.4.3) |

Only one Mailable is currently shipped. Other transactional emails (password reset, order confirmation, vendor notification) use Laravel's built-in notification/mail infrastructure via `notification_templates` table:

```bash
$ grep -c "Schema::create('notification_templates'" database/migrations/*.php
1     # → template-driven notifications table exists
```

Verify templates exist by querying:

```sql
SELECT event, subject, is_active FROM notification_templates ORDER BY event;
```

Expected events (from `NotificationTemplatesSeeder`): `order.placed`, `order.paid`, `order.shipped`, `order.delivered`, `vendor.approved`, `vendor.rejected`, `password_reset`, `support_ticket.created`, etc. If any expected event is missing, run:

```bash
php artisan db:seed --class=NotificationTemplatesSeeder --force
```

## SMTP configuration

Required `.env` values (from `.env.example.production`):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=CHANGE_ME_SMTP_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Do NOT use** port 25 (unencrypted). Use 587 (STARTTLS) or 465 (SMTPS). Prefer a transactional provider:

- **Amazon SES** — cheap at scale, requires DKIM setup
- **Postmark** — best deliverability, transactional-only (rejects marketing)
- **Mailgun** — good for high volume
- **SendGrid** — mixed reputation, works for most cases

Do NOT use Gmail or Yahoo SMTP in production — they rate-limit aggressively.

## Mail from name + from address

Both configured in `.env`:

```env
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="Marketplace"
```

The from-address MUST be on a domain you control and have DNS access to (for SPF/DKIM). `no-reply@` conventions are fine but consider `hello@` or `orders@` for warmer customer perception.

## DNS records the operator must add

### SPF (TXT record on the sending domain)

Tells receiving mail servers "these servers are allowed to send email as us."

```
your-domain.com.  TXT  "v=spf1 include:_spf.provider.com include:_spf.google.com -all"
```

The exact `include:` value depends on the provider:

- Amazon SES: `include:amazonses.com`
- Postmark: `include:spf.mtasv.net`
- Mailgun: `include:mailgun.org`
- SendGrid: `include:sendgrid.net`

Use `-all` (hard fail) not `~all` (soft fail) once you're confident.

### DKIM (TXT record on `<selector>._domainkey.<domain>`)

Public key that receiving servers use to verify the mail's cryptographic signature. Provider gives you the key.

Amazon SES example:

```
xxx._domainkey.your-domain.com.  CNAME  xxx.dkim.amazonses.com.
```

Postmark example:

```
20240101pm._domainkey.your-domain.com.  TXT  "k=rsa; p=..."
```

Copy verbatim from the provider dashboard.

### DMARC (TXT record on `_dmarc.<domain>`)

Policy for mail that fails SPF and DKIM.

```
_dmarc.your-domain.com.  TXT  "v=DMARC1; p=quarantine; rua=mailto:dmarc@your-domain.com"
```

Start with `p=none` for a week (monitor reports), then move to `p=quarantine`, then `p=reject` when you're confident nothing is failing legitimately.

## Verification

After DNS records are added:

```bash
dig +short TXT your-domain.com | grep spf
dig +short TXT xxx._domainkey.your-domain.com
dig +short TXT _dmarc.your-domain.com
```

Or use online tools: mxtoolbox.com, dmarcian.com, mail-tester.com.

Send a test email and check the receiving-side headers for:

```
Authentication-Results: spf=pass, dkim=pass, dmarc=pass
```

Any `fail` or `none` needs fixing.

## Queued mail behavior

Every `Mail::to()->send(...)` from a `ShouldQueue` job is auto-queued via the mail queue. Sends happen in the queue worker, not the request cycle.

The vendor intelligence digest specifically uses `Mail::to()->locale($locale)->send(new VendorIntelligenceDigestMail(...))` — queued because `SendVendorIntelligenceDigest implements ShouldQueue`.

If mail sends synchronously (queue driver = `sync`), page loads can stall waiting for SMTP. Use `redis` or `database` queue driver in production.

## Failed mail troubleshooting

If mail isn't arriving:

1. Check the queue worker is running: `sudo supervisorctl status marketplace-queue`
2. Check `queue:failed`: `php artisan queue:failed | grep -i mail`
3. Check Laravel log: `tail -100 storage/logs/laravel.log | grep -i mail`
4. Check provider dashboard (SES / Postmark / etc.) for bounces / spam-folder placements
5. Send yourself a test using the safe artisan command below

## Safe mail test command

No production mail-test command is shipped in this codebase. Below is what the operator can use temporarily on staging:

```bash
php artisan tinker

>>> use Illuminate\Support\Facades\Mail;
>>> Mail::raw('Marketplace mail test - '.now(), function($msg) {
...     $msg->to('operator@your-domain.com')->subject('Marketplace mail test');
... });
>>> // Look for return value; check inbox within 30 seconds.
```

If you'd like a permanent artisan command, tell me — I'll add `app/Console/Commands/MailTestCommand.php` in the next release.

## Mail templates present

The single custom template (Phase 11B.4.3):

```
resources/views/emails/vendor-intelligence-digest.blade.php
```

- Uses `@component('mail::message')` — Laravel's default mail styling
- Uses `@component('mail::table')` and `@component('mail::button')` — responsive
- All strings localized via `__()` — no forced-locale calls (Phase 11B.4.3 audit fix)
- PII discipline: no customer names/emails/order IDs; only marketplace-side aggregates

Other emails (password reset, verification, etc.) use Laravel's default templates in `resources/views/vendor/mail/` (created via `php artisan vendor:publish --tag=laravel-mail` if you want to customize them).

## Localization

Vendor intelligence digest supports `en` and `ar`. The Job calls `Mail::to($to)->locale($vendor->user->locale)->send(...)`, and every `__()` in the Blade resolves to `lang/en.json` or `lang/ar.json`.

Missing key handling: if a key isn't in the JSON, `__()` returns the key path. The Blade template detects this via the `resolveAlertTitle` closure and falls back to a humanized `alert_type` — verified in Phase 11B.4.3 post-audit fix.

## Evidence status

| Claim | Status | Evidence |
| --- | :---: | --- |
| `VendorIntelligenceDigestMail` Mailable exists | ✅ | `app/Mail/VendorIntelligenceDigestMail.php` |
| Blade template exists | ✅ | `resources/views/emails/vendor-intelligence-digest.blade.php` |
| `SendVendorIntelligenceDigest` implements ShouldQueue | ✅ | `grep "implements ShouldQueue" app/Jobs/SendVendorIntelligenceDigest.php` |
| Blade has no forced-locale `__()` (Phase 11B.4.3 audit fix) | ✅ | `grep "__('[^']+', \[\], '" resources/views/emails/*.blade.php` returns 0 |
| `notification_templates` table exists | ✅ | Migration `2026_01_01_000004_create_notification_templates_table.php` |
| Real SMTP credentials NOT in template | ✅ | `.env.example.production` uses `CHANGE_ME_SMTP_PASSWORD` |
| SMTP host reachable from server | ⏳ | Operator runs telnet / nc to verify port 587 open |
| SPF / DKIM / DMARC records added | ⏳ | Operator DNS provider console |
| First real mail sent and received | ⏳ | Operator runs the tinker test |
| Queue worker delivering mail | ⏳ | Operator checks `queue:failed` after 1 hour |
| Mailer authentication passes at receiver | ⏳ | Operator inspects `Authentication-Results` header on received mail |
