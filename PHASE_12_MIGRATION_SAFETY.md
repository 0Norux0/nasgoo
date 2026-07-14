# Phase 12 — Migration Safety Reference

Detailed guidance on running migrations against production. Cross-references §3, §4, §13, §14 of the main readiness report.

## Golden rules

1. `php artisan migrate --force` — ONLY command allowed on production
2. `php artisan migrate:fresh` — NEVER on production
3. `php artisan migrate:rollback` — only after backup, in maintenance mode, with second-engineer approval
4. Take backup BEFORE every migration on production
5. Deploy in maintenance mode for schema changes on hot tables (products, orders, users)

## The migration workflow (production)

```
    ┌─────────────────────────────────────────────────────────┐
    │  1. Deploy code to a staging environment                 │
    │     • Run `php artisan test`                             │
    │     • Run `php artisan migrate --force` on staging       │
    │     • Smoke-test the app                                 │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  2. Take a FRESH backup of production                    │
    │     • `mysqldump ... | gzip -c > pre_deploy.sql.gz`      │
    │     • Verify size is reasonable                          │
    │     • Test-restore to scratch (optional but recommended) │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  3. Enter maintenance mode (if migration is destructive) │
    │     • `php artisan down --refresh=15`                    │
    │     • Use `--secret=OPERATOR_ONE_OFF` for admin bypass   │
    │     • Skip if only additive columns/tables and small     │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  4. Preview                                              │
    │     • `php artisan migrate --pretend` shows the SQL      │
    │     • Verify only expected migrations appear             │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  5. Run                                                  │
    │     • `php artisan migrate --force`                      │
    │     • Watch for errors                                   │
    │     • Check `php artisan migrate:status` afterward       │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  6. Bring up                                             │
    │     • `php artisan optimize:clear`                       │
    │     • Cache config: `php artisan config:cache`           │
    │     • `php artisan up`                                   │
    │     • Smoke-test /health, /admin, /vendor                │
    └─────────────────────────────────────────────────────────┘
                             │
                             ▼
    ┌─────────────────────────────────────────────────────────┐
    │  7. Post-deploy                                          │
    │     • Watch error logs 30 min                            │
    │     • Watch slow query log                               │
    │     • Rollback plan ready if metrics degrade             │
    └─────────────────────────────────────────────────────────┘
```

## When to take special care

Certain columns/tables have large row counts and DDL operations may take minutes and lock the table. Watch out for:

- `products`, `product_translations` — thousands of rows in a mature marketplace
- `orders`, `order_items` — grows unbounded; may lock during ADD COLUMN
- `product_reviews`, `wishlists` — same growth pattern
- `customer_product_views`, `recommendation_events` — very high write rate; ALTER TABLE can lock out writes

For any migration touching these on a mature production DB, use one of:

1. **`pt-online-schema-change`** (Percona) — copies the table, streams changes, swaps at the end
2. **`gh-ost`** (GitHub) — similar approach, cleaner rollback
3. **Blue-green deployment** — new schema in a replica, promote

Simple ADD COLUMN with a nullable default is usually safe (MySQL 8 does this online without a full copy).

Adding an index on a large table: use `ALTER TABLE ... ADD INDEX ... ALGORITHM=INPLACE, LOCK=NONE` to avoid blocking writes.

## Rollback decision tree

```
Migration deployed, users report a problem
              │
              ▼
    Is data being corrupted RIGHT NOW?
              │
     ┌────────┴────────┐
    YES               NO
     │                 │
     ▼                 ▼
  Maintenance      Can I forward-fix?
  mode NOW         (add a new migration)
     │            │
     ▼         ┌──┴───┐
  Assess    YES     NO
  backup    │        │
  freshness ▼        ▼
     │    Forward-  Rollback plan:
     ▼    fix and   backup → maint mode → migrate:rollback --step=1 --force
  Restore  deploy   
  from     
  backup
```

**Forward-fix is almost always the answer.** Adding a new migration that reverses the problematic change is safer than rollback because:
- No data loss risk
- No production maintenance window
- Version-control history stays clean
- CI/CD flow stays normal

## What `migrate:rollback --step=1` actually does (v11B.4.3 last migration)

The most recent migration is `2027_01_01_000001_add_vendor_intelligence_digest_columns.php`. Its `down()` method:

1. Drops the `vis_last_digest_idx` index on `vendor_intelligence_summaries`
2. Drops columns `last_digest_sent_at` and `email_opted_out`

Rolling back this migration LOSES: digest send history (when each vendor last got a digest) + per-vendor opt-out flags. Both are regenerated behavior — vendors will start getting digests again after next `--send-emails` run — so rollback is low-risk. Still take a backup first.

## What `migrate:rollback --step=1` would do at each recent migration

- `2027_01_01_000001` (v11B.4.3 digest columns) → losing digest history + opt-outs (low risk)
- `2026_12_01_000001` (v11B.4.2 dedupe + stale) → losing UNIQUE constraint on active_dedupe_key + stale_at + last_generated_at columns (MEDIUM risk — duplicates could reappear)
- `2026_11_01_000001` (v11B.4 vendor intelligence tables) → drops 4 tables including feedback history (HIGH risk — permanent feedback loss)
- Anything older → catastrophic

Never rollback more than 1 step at a time on production.

## Automated safe deploy (v12.1)

`scripts/deploy-production-phase12.sh` implements the workflow diagram in this document. The script:

- Refuses to run if `APP_ENV=local` (exit code 2)
- Warns if `APP_DEBUG=true` and requires typed `CONTINUE-WITH-DEBUG`
- Verifies php ≥ 8.3, composer, mysqldump, mysql, node/npm
- Verifies DB reachable via `php artisan db:show`
- Verifies ≥ 1 GB free on backup volume
- Takes `mysqldump --single-transaction --routines --triggers --events --hex-blob` gzipped backup
- Verifies backup file is non-empty (exit code 3 if not)
- Requires typed `DEPLOY` confirmation before touching the DB
- Enables maintenance mode with a bypass secret URL
- Runs `php artisan migrate --force`
- Rebuilds config/route/view/event caches
- Gracefully restarts queue workers
- Brings the app back up

On any failure, the script's `trap ERR` handler logs the failing step, the log-file path, and the exact restore command. The app stays in maintenance mode so no customer traffic sees a broken state.

The old `scripts/deploy.sh` is now marked LEGACY with a runtime `if grep -qE '^APP_ENV=production' .env` guard that refuses to run against production.

## The old script's problem (recorded for the audit trail)

`scripts/deploy.sh` (Phase 10 v10.2) executed `php artisan migrate --force` at line 64 without:
- Any DB backup
- Any operator confirmation
- Any maintenance mode
- Any failure-recovery pathway

That was unsafe for a live marketplace. Recording the finding here so future audits know why the file is retained but legacy-only.
