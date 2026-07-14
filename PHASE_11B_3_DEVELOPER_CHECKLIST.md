# Phase 11B.3 — Developer Checklist

Concise action list for verifying and deploying Phase 11B.3.

## 1. Backup

```bash
git tag phase-11B-2-final-approved
git checkout -b phase-11B-3-personalized-homepage
mysqldump --single-transaction ... > backup-before-11b3.sql
```

## 2. Extract archive

```bash
tar -xzf marketplace-phase-11B-3-personalized-homepage.tar.gz
diff -r . /path/to/extracted-marketplace  # confirm SHA-matches PACKAGE_INTEGRITY.md
```

## 3. Migrate

```bash
php artisan migrate:status                     # 3 v11B.3 rows should be Ran=No
php artisan migrate                            # applies all 3 idempotently
php artisan migrate:status | grep "2026_08"    # confirm the 3 v11B.3 migrations Ran=Yes
```

## 4. Rebuild + prune sanity

```bash
php artisan personalization:prune --dry-run    # expect counts printed, 0 deletions
php artisan personalization:rebuild            # runs for all active customers
```

## 5. Tests

```bash
php artisan test --filter=Phase11B3            # 56 v11B.3 scenarios
php artisan test --filter=Phase11B22           # 45 v11B.2.2 regression
php artisan test --filter=Phase11B21           # 38 v11B.2.1 regression
php artisan test --filter=Phase11B2            # 50 v11B.2 regression
php artisan test                                # 731 total
```

## 6. Build

```bash
npm ci
npm run typecheck                              # must pass — 0 errors on new files
npm run build
```

## 7. Smoke verification (manual)

- [ ] `/` renders as authenticated customer with personalized band
- [ ] `/` renders as guest with Recently Viewed (after viewing 2+ products)
- [ ] Product detail page records a view (check `customer_product_views` table)
- [ ] `/account/personalization` renders 3 toggles + Reset button
- [ ] Toggle behavioral_personalization off → homepage no longer shows personalized band
- [ ] "Not Interested" on a product card hides it from Recently Viewed
- [ ] "Clear" on Recently Viewed section wipes the caller's rows
- [ ] Arabic locale: section headings translated; RTL layout correct
- [ ] Feature flag PERSONALIZATION_ENABLED=false → homepage falls back to pre-v11B.3

## 8. Feature-flag defaults on production

`.env` should NOT need any personalization variables for defaults. Leave unset → defaults from config file apply. Set `PERSONALIZATION_ENABLED=false` if you want to launch dark.

## 9. Data retention

Confirm the daily schedule is running:

```bash
php artisan schedule:list | grep personalization
# Expect:
#   personalization:prune   03:00 daily
#   personalization:rebuild 03:15 daily
```

If your production uses a queue worker, ensure `queue:work` is running (the rebuild command is synchronous but any queued jobs from other phases share the same worker).

## 10. Privacy-audit checklist

- [ ] No file under `app/Services/Personalization/` reads `users.email`, `users.name`, or any address field
- [ ] No file under `app/Services/Personalization/` reads `payments.*` or `support_tickets.*`
- [ ] Every controller action guards identity from `$request->user()` or session ID (never from a request-body user_id)
- [ ] `personalization_feedback` rows have `expires_at` set on write
- [ ] `personalization:prune` deletion counts are non-zero after 30 days in production

## 11. Rollback readiness

Keep `marketplace-phase-11B-2-final-approved.tar.gz` accessible at all times. The 3-tier rollback procedure is in `PHASE_11B_3_ROLLBACK.md`.
