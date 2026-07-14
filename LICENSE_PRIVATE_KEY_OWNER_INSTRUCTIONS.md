# Private Key — Owner Instructions

This document is for the license owner (Aamir Farooq / ICSA). It describes how the private signing key is generated, stored, and used. Keep this document with you; you'll refer to it every 60 days when you renew.

## The core principle

You hold ONE private cryptographic key. The **production application
package** NEVER contains it — not in Git, not in `.env`, not in the shipped
`.zip` or `.tar.gz`, not on the production server.

The offline generator DOES write a `private.b64` file when you run it in
keypair-generation mode (`--generate-keypair --out ./license-keys`). That
file is written ONLY to the local directory you specify, on the machine you
run the tool on. That machine must be yours, private, and trusted — never
a shared workspace, never the production server, never the developer's
laptop.

If it did leak, the entire ownership protection collapses — anyone who saw
the file could generate their own valid license tokens forever.

## Generating the key (do this ONCE)

Do this on a machine you trust — your personal laptop, ideally not one that runs the marketplace or any customer-facing service.

```bash
cd /path/to/marketplace-code    # any copy of the code that has tools/license-generator/
mkdir -p /secure/license-keys
chmod 700 /secure/license-keys
php tools/license-generator/generate.php --generate-keypair --out /secure/license-keys
```

You'll get:

- `/secure/license-keys/private.b64` — mode 0600, this is the private key
- `/secure/license-keys/public.b64` — mode 0644, this is the public key

The command also prints the public key to the terminal. Copy it.

## Storing the private key safely

Pick TWO OR MORE of these:

### Option A — Password manager (recommended, simplest)

1Password, Bitwarden, LastPass, Dashlane — any reputable one.

Steps:
1. Open your password manager
2. Create a new secure note titled "Marketplace license private key"
3. Open `/secure/license-keys/private.b64` in a text editor
4. Copy the entire content (it's one short base64 line)
5. Paste into the secure note
6. Save
7. Delete the plain file: `shred -u /secure/license-keys/private.b64` (Linux) or `srm /secure/license-keys/private.b64` (macOS)

### Option B — GPG-encrypted file

1. Encrypt with a strong passphrase:

    ```bash
    gpg -c /secure/license-keys/private.b64
    # Enter a strong passphrase (write it in your password manager)
    # Produces private.b64.gpg
    ```

2. Copy `private.b64.gpg` to cloud storage (Google Drive, Dropbox, iCloud — pick one)
3. Also copy it to a USB drive kept in a safe / drawer
4. Delete the plain file: `shred -u /secure/license-keys/private.b64`

To decrypt when you need to sign a token:

```bash
gpg -d private.b64.gpg > /tmp/private.b64
chmod 600 /tmp/private.b64
# Use it (see next section)
shred -u /tmp/private.b64      # after use
```

### Option C — Air-gapped USB drive

1. Copy `private.b64` to a fresh USB drive
2. Store the USB drive in a locked drawer / safe
3. Delete from the working machine
4. When needed: plug in USB, use, remove USB

Not convenient, but bulletproof.

### Option D — Printed backup

1. Print the base64 string on paper
2. Store in a home safe, safety deposit box, or with a lawyer
3. If needed: retype the string (it's short — 44 characters)

Belt-and-suspenders with any of the above.

## Never do

| Do NOT | Why |
| --- | --- |
| Email the key to yourself or anyone | Email is stored on mail server, indexed, backed up, discoverable |
| Slack / WhatsApp / Discord / any chat | Same problem, plus company access |
| Commit to Git (public OR private) | Git history is forever; even deleted from history, forks/clones retain it |
| Put in `.env` on any deployed server | `.env` can be leaked via nginx misconfig, backup exposure, or malicious hoster |
| Store in project repo | Whoever downloads the repo gets the key |
| Screenshot | Screenshots go into cloud photo sync automatically |
| Share with the developer | If the developer needs to activate, they ask you for a token |
| Share with the hoster | Never |
| Include in the marketplace archive delivered to any deployment | The archive is a distribution — never distribute your private key |

## Using the private key to sign a token

Restore the private key temporarily (from wherever you stored it), then:

```bash
php tools/license-generator/generate.php \
    --private-key /tmp/private.b64 \
    --holder "Aamir Farooq / ICSA" \
    --domain marketplace.example.com \
    --days 60 \
    --type owner
```

The command prints a token. Copy the whole line. Paste into `/admin/license` on the deployed marketplace, click "Activate license".

Then remove the temporary copy of the private key:

```bash
shred -u /tmp/private.b64
```

## Rotation (only if key is compromised or you want to)

If you think the key was leaked, OR you want to change it for any reason:

1. Generate a fresh keypair: `php tools/license-generator/generate.php --generate-keypair --out /secure/license-keys-new`
2. Tell the developer to update `LICENSE_PUBLIC_KEY` in production `.env` to the new public key value
3. Developer runs: `php artisan config:cache && php artisan license:clear-cache`
4. You issue a fresh token using the NEW private key
5. You activate it via `/admin/license`
6. Destroy the OLD private key (all copies, all backups)

After step 3, any token signed by the old key stops working. That's the whole point.

## What if you lose access to the private key

If ALL your backup copies are gone:

- The current active license keeps working until its `expires_at`
- You cannot issue any new tokens
- After the current license expires, you follow the "rotation" flow above with a fresh keypair

There's no other recovery path. That's why the "at least TWO backup locations" rule exists.

## What the app cannot do without the private key

- Cannot generate valid license tokens
- Cannot bypass the middleware verification (unless someone modifies the source code + redeploys — which is out of the cryptographic layer's scope)
- Cannot phone home to get a license
- Cannot ask an external server for permission

The app is entirely dependent on YOU as the key holder for continued operation past 60 days.

## Summary — the daily-life checklist

**Every 60 days (or when the admin banner warns of expiry):**

1. Restore private key from backup
2. Run generator with fresh dates
3. Copy token
4. Paste into `/admin/license`
5. Delete the temporary private key copy
6. Done — enjoy the next 60 days

**Every 6 months:**

1. Verify your backups still work
2. Test that you can decrypt (if using GPG) with your remembered passphrase
3. Test that you can still access the password manager entry
4. Rotate storage location if any risk factor has changed

**Every rotation event (loss suspected, new hire in position of trust, hosting change):**

Follow the rotation flow above.
