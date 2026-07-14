# Phase 10 v10.7 — Patch Notes

## What's fixed

One concrete defect from runtime testing:

| Defect | Root cause | Fix |
|---|---|---|
| Image vendor documents (JPG/JPEG/PNG/WebP) appear as "File not found"; PDFs work | `VendorRegistrationController` wrote ALL uploads (logo, banner, license, ID) to `config('filesystems.default')` = `'local'` disk. But `VendorFileLinks::urlFor` read public kinds (logo, banner) from the `'public'` disk via `Storage::url`. Different disks → different filesystem roots → image files weren't where the preview code looked. PDFs were unaffected because license/ID (private kinds) used the `'vendors'` disk which shares the same root as `'local'` after v10.6. | (1) NEW `VendorFileResolver` centralizes disk/path resolution and probes legacy fallbacks for old records. (2) `VendorFileLinks::urlFor` delegates to the resolver. (3) `VendorFileController` accepts logo + banner kinds and uses the resolver. (4) `VendorRegistrationController` routes logo/banner to the public disk and license/ID to the private disk going forward. (5) `config/marketplace.php` adds canonical `vendor_public_disk` key. |

## What's preserved (per dev: "do not break the working PDF flow")

- Private kinds (license_document, id_document) STILL go through the signed `/admin/vendor-files/{id}/{kind}` route. Defense-in-depth admin auth check unchanged.
- PDF MIME detection unchanged: `Storage::disk($disk)->mimeType($path)` is the same call, the resolver just tells the controller which `$disk` and `$path` to use.
- The pre-v10.7 stored path format (`vendors/{id}/{filename}`) is unchanged. NO database migration required.

## Counts

| | v10.6 → v10.7 |
|---|---|
| Phase 10 CI sub-checks | 34 → 38 (6+7+5+5+4+3+4+4) |
| Phase 10 Pest scenarios | 66 → 84 (13+14+8+8+6+6+11+18) |
| Phase-specific CI grand total | 89 → 93 |
| New PHP source files | 1 (VendorFileResolver) |
| Modified PHP source files | 4 (VendorFileLinks, VendorFileController, VendorRegistrationController, config/marketplace) |
| Modified React/JS files | 0 |
| New Pest test files | 1 |
| v1-v9 files touched | 0 |
| v10.0-v10.6 fix code reverted | 0 |

## tsc verification

No React files changed in v10.7. The previous v10.6 tsc-clean state is preserved.

## Per §O acceptance

**Phase 10 v10.7 is implemented but requires developer runtime verification.**

The static evidence is in `PHASE_10_v10.7_VENDOR_IMAGE_REPAIR.md`. The dev's runtime gate is the §14 final clause: "PDF vendor documents still open correctly AND image vendor documents display and open correctly."
