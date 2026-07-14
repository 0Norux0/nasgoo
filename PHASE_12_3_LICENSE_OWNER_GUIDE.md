# Phase 12.3 — License Owner Guide (For Aamir)

This is your guide as the owner of the marketplace. It covers how the license protection works, what you do to protect your ownership, and what to do when things need to change (renewal, new server, etc.).

## The one-sentence explanation

You hold a private cryptographic key. The marketplace holds only the matching public verification key. Every 60 days you generate a fresh signed license token with your private key, then paste that token into the marketplace's admin panel to keep it running. Nobody else can generate valid tokens — not the developer, not a hoster, not a customer.

## Where the private key lives (v12.3.1 clarification)

The **production application package** never contains the private key. It's not in Git, not in `.env`, not in the shipped archive.

But when you run `generate.php --generate-keypair`, the tool DOES write a `private.b64` file to the local directory you specify on YOUR machine. That's normal and expected — it's how you get a private key in the first place. What matters is that this file:

- exists only on YOUR trusted machine (not the production server, not the developer's machine, not shared storage)
- is protected (mode 0600, backed up encrypted, kept off the internet)
- is used only for signing tokens, then either destroyed or moved to secure backup

The generator in signing mode (`--private-key ... --holder ... --domain ...`) READS an existing `private.b64`, uses it to sign, wipes the key from memory, and exits without writing anything new. It's the keypair-generation mode that produces the file.

## Initial setup (one-time)

You need to do this exactly ONCE, on a machine you trust (your laptop, ideally not one used for internet browsing):

### 1. Generate your signing keypair

```bash
# On your personal machine
cd /path/to/marketplace-code
php tools/license-generator/generate.php --generate-keypair --out /secure/license-keys
```

This creates:
- `/secure/license-keys/private.b64` — YOUR PRIVATE KEY. Guard this like your bank password.
- `/secure/license-keys/public.b64` — the public key. This goes into the marketplace.

The command also prints the public key to the terminal. Copy it.

### 2. Store the private key safely

Do at least TWO of these:

- **Password manager** (1Password, Bitwarden, LastPass): create a secure note titled "Marketplace license private key", paste the contents of `private.b64` into it
- **GPG encryption** (`gpg -c private.b64` — enter a strong passphrase, produces `private.b64.gpg`), then keep the `.gpg` file in cloud storage or a USB drive
- **Air-gapped backup** (USB drive or physical printout kept in a safe)

DELETE the plain `private.b64` from your working machine after backing up. Never leave a plain-text copy sitting on a hard drive that's connected to the internet.

**Never do this:**
- Never email the private key
- Never Slack/WhatsApp the private key
- Never put it in Git
- Never put it in `.env`
- Never share it with the developer or hoster
- Never leave a plain copy on a shared machine

### 3. Install the public key on the marketplace

Give the developer the public key value (or install it yourself). It goes into the deployment's `.env`:

```env
LICENSE_PUBLIC_KEY=<the base64 string you copied>
LICENSE_ENFORCEMENT_ENABLED=true
```

Then have the developer run:

```bash
php artisan migrate --force
php artisan config:cache
php artisan license:clear-cache
```

### 4. Issue your first license token

```bash
# On your personal machine, with the private key restored from backup
php tools/license-generator/generate.php \
    --private-key /secure/license-keys/private.b64 \
    --holder "Aamir Farooq / ICSA" \
    --domain marketplace.example.com \
    --days 60 \
    --type owner
```

The command prints a long token to your terminal (all one line). Copy the whole thing.

### 5. Activate on the marketplace

Go to `https://marketplace.example.com/admin/license`, log in as super_admin, paste the token into the box, click "Activate license".

You should see: "License activated successfully. Expires in 60 days."

## Every 60 days (renewal)

The admin panel will show a warning banner starting 14 days before expiry, escalating at 7 days and 3 days. When you see the banner:

1. Restore your private key from backup (do not leave it lying around)
2. Run the generator again with fresh dates
3. Paste the new token into `/admin/license`
4. Done — the old token is superseded automatically

The renewal flow is the same as the initial activation. Nothing complicated.

## When to issue a token

The generator asks for these values:

| Field | What to put | Notes |
| --- | --- | --- |
| `--holder` | Your name or company | Appears in the admin UI |
| `--domain` | The site's real domain | Must match `APP_URL` on the deployment |
| `--days` | 60 (default) | Longer = less friction; shorter = less risk if something leaks |
| `--type` | `owner` | Distinguishes your own token from a delegated one |
| `--fingerprint` | (optional) | See "Server fingerprint binding" below |

## Server fingerprint binding (optional, stronger)

By default, the token is bound to the domain only. That's usually enough. If you want stronger binding — so a token can't be moved to a different server even at the same domain — turn on fingerprint binding:

1. Ask the developer to run `php artisan license:fingerprint` on production
2. Note the fingerprint value (64 hex characters)
3. Pass it when generating: `--fingerprint <64-hex>`
4. Have the developer set `LICENSE_REQUIRE_FINGERPRINT_MATCH=true` in `.env`

Downside: if the server ever changes (new host, new DB, new APP_URL), the fingerprint changes and the token becomes invalid. You then need to issue a new one bound to the new fingerprint. That's a feature, not a bug — it prevents casual copying.

## Moving to a new server / new domain

Perfectly normal — every rehost needs a new token:

1. Get the new server's fingerprint (`php artisan license:fingerprint` on the new box)
2. Get the new `APP_URL` (the new site's HTTPS URL)
3. Restore your private key from backup
4. Generate a new token bound to the new domain + new fingerprint
5. Activate it via `/admin/license` on the new server

Old activations remain in the `license_activations` table for audit but are marked `superseded`.

## When someone else asks for the private key

Do not give it. Not to the developer, not to a hoster, not to anyone else. Point them at this document. Anyone who legitimately needs to activate the marketplace can do so by asking you for a token — you sign it, paste it into a message, and they activate it via `/admin/license`.

## What if you lose the private key

If your `private.b64` file AND all your backups are gone:

- The marketplace continues running until the current token expires (up to 60 days)
- After that, you can't issue new tokens — no new activations are possible
- The developer must generate a fresh keypair (via `--generate-keypair` again), install the new public key on production, and you'd receive a fresh private key for the future
- The marketplace's `license_activations` records the past activations for audit — nothing is lost

The point is: don't lose the key. Follow the "at least TWO backup locations" rule.

## What if you think the private key was leaked

Same as losing it, plus more urgent:

1. Generate a fresh keypair immediately
2. Install the new public key on production (`.env` `LICENSE_PUBLIC_KEY`)
3. Run `php artisan license:clear-cache && php artisan config:cache`
4. Issue a new token with the NEW private key
5. Activate on production
6. Existing tokens signed by the old key stop working

The gap where the old key could still be used is at most `LICENSE_CACHE_TTL_SECONDS` (default 5 minutes) after the new public key is installed.

## What the developer can and cannot do

**Cannot:**
- Generate valid license tokens without the private key
- Bypass the check without modifying the source code AND redeploying
- See the private key (it's not in the codebase, it's not in `.env`, it's not on the server)

**Can:**
- Modify the source code to disable the middleware (Git history shows this happened)
- Rebuild the marketplace from scratch without your license code (but that's a separate business)
- See license activation records in the database — including who activated, when, and payload metadata (NOT the token itself)

The system raises the bar. It doesn't eliminate risk. Per §17: **code-level protection is not absolute**.

## Common questions

**Q: Can I make the license last longer than 60 days?**

Yes — pass `--days 365` for a 1-year license. Longer tokens are more convenient but more damaging if leaked. 60 days is a decent balance.

**Q: Do I need to be online to generate a token?**

No. The generator runs entirely offline. No network access is required or performed.

**Q: What if I miss the expiry?**

The public storefront stays visible (default). The admin panel redirects super_admin to `/admin/license`. Vendors and customers see a "marketplace unavailable" page. Nothing is deleted or damaged. You issue a fresh token, activate it, everything resumes.

**Q: Can I run the generator on the production server?**

You can, but you shouldn't. Running it on production means the private key is briefly on the production server. Keep it on your personal secure machine and paste the token from there.

**Q: Can multiple people activate tokens?**

Only super_admin can activate. The activation is audited — the `activated_by` column shows which user's account did it, and `license_audit_logs` records IP + user agent.

**Q: Does this send any data to me / to Anthropic / anywhere?**

No. There's no phone-home. The generator doesn't talk to anything. The marketplace doesn't call out. Everything is local to your setup.
