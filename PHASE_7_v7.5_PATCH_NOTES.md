# Phase 7 v7.5 — Registration mail-transport resilience

**Status:** Defensive hardening on top of Phase 7 v7.4. Pending CI verification.
**Scope:** `.env.example` default + `User` model override + `RegisterController` wrap + 6 new Pest scenarios + 2 new CI sub-checks. **No other Phase 7 file touched.**

---

## What broke (the developer's screenshot)

Registration crashed in local development with:

```
Symfony\Component\Mailer\Exception\TransportException

Connection could not be established with host "mailpit:1025":
stream_socket_client(): php_network_getaddresses:
getaddrinfo for mailpit failed: No such host is known
```

The `mailpit` hostname only resolves when the Docker Compose stack is running (which provides a service named `mailpit` on the internal network). On a non-Docker dev machine where the developer ran `php artisan migrate:fresh --seed` + `php artisan serve` directly, `mailpit` is an unknown host — the Symfony Mailer throws `TransportException`, it bubbles out of `event(new Registered($user))` in `RegisterController::store()`, and the customer sees a HTTP 500 even though their user row was inserted seconds earlier.

This made local registration testing impossible without manually editing `.env`.

---

## v7.5 fix — three complementary defenses

### Defense 1 — safe default in `.env.example`

`.env.example` now defaults to `MAIL_MAILER=log`. Fresh local installs send verification emails to `storage/logs/laravel.log` (the dev can copy the signed URL from there to verify accounts). No external service required.

The Docker/Mailpit configuration is still documented inline — developers running the full Docker stack can switch to `MAIL_MAILER=smtp` + `MAIL_HOST=mailpit` whenever they want.

```diff
# =====================================================================
-# Mail (Mailpit in dev)
+# Mail
# =====================================================================
-MAIL_MAILER=smtp
+# Phase 7 v7.5 — default to the 'log' driver so fresh local installs can
+# register users without any external mail service. Verification emails
+# are written to storage/logs/laravel.log; copy the signed URL from there
+# to verify accounts during local testing.
+#
+# To use Mailpit (only useful if you started the Docker stack), switch to:
+#   MAIL_MAILER=smtp
+#   MAIL_HOST=mailpit
+#   MAIL_PORT=1025
+# Mailpit UI is at http://localhost:8025
+MAIL_MAILER=log
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Defense 2 — `User::sendEmailVerificationNotification()` override

Even if a developer (or production environment) misconfigures the mailer and points it at an unreachable host, registration should still complete. The User model now overrides `sendEmailVerificationNotification()` to catch ANY `Throwable` from the mailer and log it at WARNING level:

```php
public function sendEmailVerificationNotification(): void
{
    try {
        parent::sendEmailVerificationNotification();
    } catch (\Throwable $e) {
        Log::warning(
            'Verification email could not be sent — registration/resend will continue without it. '
            . 'Configure MAIL_MAILER (see .env.example) or start the Mailpit Docker service. '
            . 'Error: ' . $e->getMessage(),
            ['user_id' => $this->id, 'email' => $this->email, 'exception' => get_class($e)]
        );
    }
}
```

This is the **primary defense**. It catches every code path that sends a verification email — `event(new Registered)` listener, `EmailVerificationController::send` (resend), any future custom flow — because they all funnel through `User::sendEmailVerificationNotification()`.

**Important**: the failure is still **logged with the user's email and the exception class** at WARNING level. Production monitoring (Sentry, Bugsnag, log aggregation) still picks up the failure for triage. The only behaviour change is "don't show a 500 to the customer when their user row was already created successfully."

### Defense 3 — `RegisterController::store()` wraps `event(new Registered($user))`

Belt-and-suspenders. Other listeners may be registered against the `Registered` event in the future. Any one of them failing must NOT crash registration after the user row has been created. The dispatch is now wrapped:

```php
try {
    event(new Registered($user));
} catch (\Throwable $e) {
    Log::warning(
        'A listener on Registered failed — registration will continue. '
        . 'Error: ' . $e->getMessage(),
        ['user_id' => $user->id, 'exception' => get_class($e)]
    );
}
```

Defense 2 covers verification-email listeners specifically; Defense 3 covers everything else.

---

## What v7.5 changes

| File | Change |
|---|---|
| `.env.example` | `MAIL_MAILER=smtp` → `MAIL_MAILER=log` + ~20 lines of inline comments documenting the Docker/Mailpit alternative. |
| `app/Models/User.php` | +30 lines: override `sendEmailVerificationNotification()` with `try/catch` + structured `Log::warning` call. |
| `app/Http/Controllers/Auth/RegisterController.php` | +9 lines: wrap `event(new Registered($user))` in `try/catch`. |
| `tests/Feature/Phase7RegistrationResilienceTest.php` | New file — 6 Pest scenarios covering: registration with log mailer, registration with forced TransportException, User override behaviour, and 3 static-source checks that the safeguards exist. |
| `.github/workflows/ci.yml` | +120 lines, 2 new sub-checks: static (3 defenses must be present in source) + runtime (POST /register against a live `php artisan serve` and assert HTTP < 500 + user row + customer role). Verdict bumped. |
| `PHASE_7_v7.5_PATCH_NOTES.md` | This file (new) |
| `PHASE_7_REPORT.md` | v7.5 update section appended |
| `README.md` | v7.5 changelog block prepended; status header bumped |
| `TROUBLESHOOTING.md` | New entry: "TransportException / getaddrinfo for mailpit failed" |

**Files NOT touched in v7.5:** all Phase 7 migrations, all 4 customization models, all 4 services, both Filament resources, all 5 customization controllers, all 11 customization routes, both Phase 7 React files, the demo seeder. No business logic, no schema, no permission changes.

---

## Docker / Mailpit clarification

The `docker-compose.yml` shipped from Phase 0 onwards already includes a `mailpit` service:

```yaml
mailpit:
  image: axllent/mailpit:latest
  container_name: marketplace_mailpit
```

Developers running the full Docker stack with `docker compose up -d` can switch their `.env` to `MAIL_MAILER=smtp` + `MAIL_HOST=mailpit` and have all verification emails appear in Mailpit's UI at `http://localhost:8025`. The v7.5 default just doesn't *require* it.

The CI runner uses `MAIL_MAILER=log` in the new v7.5 sub-check — same as the recommended local default.

---

## Verification I ran in the sandbox

I cannot run `php artisan serve` or `php artisan test` in the sandbox (network 403, no PHP runtime). What I verified:

- **Sandbox-state diagnostic**: again caught a working-tree regression at the start of this turn (sandbox file system reset). Restored from the shipped v7.4 archive before applying v7.5 changes — same workflow as v7.4.
- **PHP brace balance**: 347/347 (was 346 in v7.4, +1 for the new Pest test file)
- **All v7.1–v7.4 static pre-flights still green**: schema-vs-code, unique-index lookup, null-vs-NOT-NULL, model safeguard
- **v7.5 static check** (the exact code shipping as the new CI sub-check): all 3 defenses present — `MAIL_MAILER=log` in `.env.example`, User override with `parent::sendEmailVerificationNotification()` + Throwable catch + `Log::warning`, RegisterController wraps `event(new Registered)`
- **CI YAML parses**: valid
- **Phase 7 CI step count**: 11 (was 9 in v7.4, +2 new v7.5 steps)
- **Phase 7 Pest scenario count**: 34 (was 28 in v7.4, +6 new v7.5 scenarios)

---

## Developer testing checklist after pulling v7.5

```bash
git pull
composer install
php artisan optimize:clear

# Fresh .env from the new default
cp .env.example .env
php artisan key:generate

php artisan migrate:fresh --seed     # must succeed
php artisan migrate:fresh --seed     # run AGAIN — confirms idempotency
npm ci && npm run typecheck && npm run build
php artisan test --filter "Phase7"   # Phase 7 customization + registration resilience suites
```

Then manually test registration:

1. `php artisan serve` (port 8000)
2. Visit http://localhost:8000/register
3. Fill in: name, email, password (8+ chars with letters + numbers), confirm, terms ✓
4. Submit — should redirect to `/` with "Welcome! Please check your email to verify your account." flash message
5. Check `storage/logs/laravel.log` — should contain the verification email (with a signed `/email/verify/...` URL you can click to verify the account)

**To prove the v7.5 defense works**, set `MAIL_MAILER=smtp` and `MAIL_HOST=does-not-exist.invalid` in `.env`, run `php artisan optimize:clear`, and try registration again. It should STILL succeed (with a warning in the log).

---

## Accountability — sixth Phase 7 release

This is the sixth Phase 7 release. The pattern of "runtime error → developer reports → I add fix + CI guard" has now extended to:

| Version | Bug | Defense |
|---|---|---|
| v7.0 | Wrong column name | v7.1 schema-vs-code pre-flight |
| v7.1 | Duplicate SKU | v7.2 unique-index lookup pre-flight + migrate × 2 |
| v7.2 | `file_path = null` for NOT NULL col | v7.3 null-vs-NOT-NULL pre-flight |
| v7.3 | `Storage::put` returns false silently | v7.4 model-level `LogicException` safeguard |
| v7.4 | `mailpit:1025` unreachable in local dev | **v7.5 mail-transport resilience (3 defenses)** |

All 6 defenses are now permanent CI guards. Phase 7 has acquired more layered protection than any other phase in the project. The customization domain itself is feature-complete; everything since v7.0 has been hardening the seeder + environment surface.

**Phase 7 v7.5 STOPS HERE. Do not start Phase 8 until CI shows `✅ Phase 7 v7.5 PASSES` AND your developer confirms registration works locally with `MAIL_MAILER=log`.**
