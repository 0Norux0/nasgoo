# Phase 10 v10.7 — Vendor Image-Document Repair

Per dev §12: the focused repair report.

## Confirmed defect, narrowed

- **Working pre-v10.7:** pending vendor application opens, PDF documents are viewable.
- **Broken pre-v10.7:** image documents (JPG, JPEG, PNG, WebP) on logo + banner fields display as "File not found".
- **Hypothesis from dev:** disk/path mismatch between upload and preview.

The hypothesis is exactly correct.

## Exact root cause

In `app/Http/Controllers/Vendor/VendorRegistrationController.php:117-128` (pre-v10.7):
```php
$disk = config('filesystems.default');                       // → 'local'
foreach (['logo', 'banner', 'license_document', 'id_document'] as $field) {
    if ($request->hasFile($field)) {
        $path = $request->file($field)->store("vendors/{$vendor->id}", $disk);
        $vendor->{$field . '_path'} = $path;
    }
}
```

All four fields write to the **default disk = `'local'`** (root: `storage/app/private`). Then `app/Domain/Vendor/VendorFileLinks.php::urlFor()` reads them differently by privacy class:

```php
if (in_array($kind, self::PRIVATE_KINDS, true)) {  // license_document, id_document
    $disk = config('marketplace.vendor_private_disk', 'vendors');  // 'vendors'
    // … the v10.6 'vendors' disk has root storage/app/private — matches upload location ✓
    return URL::temporarySignedRoute(...);
}
// Public kinds (logo, banner):
$disk = config('marketplace.vendor_public_disk', 'public');         // 'public'
if (! Storage::disk($disk)->exists($path)) {
    return null;                                                    // ← "File not found"
}
return Storage::disk($disk)->url($path);
```

| Kind | Upload disk | Upload disk root | Preview disk | Preview disk root | Match? |
|---|---|---|---|---|---|
| `license_document` (PDF) | `local` | `storage/app/private` | `vendors` (v10.6) | `storage/app/private` | ✓ same physical dir |
| `id_document` (PDF) | `local` | `storage/app/private` | `vendors` | `storage/app/private` | ✓ same |
| `logo` (image) | `local` | `storage/app/private` | `public` | `storage/app/public` | ✗ **different dirs** |
| `banner` (image) | `local` | `storage/app/private` | `public` | `storage/app/public` | ✗ **different dirs** |

PDFs worked by coincidence: the v10.6 `vendors` disk and the default `local` disk both root at `storage/app/private`. Logo/banner failed because the `public` disk roots at `storage/app/public` — a different directory entirely.

## Concrete examples

**Example: a JPG logo uploaded by a vendor pre-v10.7:**

```text
Database value (vendors.logo_path):  vendors/15/abc123.jpg
Upload disk (was):                    local
Physical file location:               storage/app/private/vendors/15/abc123.jpg
Preview disk (was):                   public
Preview lookup path:                  storage/app/public/vendors/15/abc123.jpg ← does not exist
Result:                               Storage::disk('public')->exists() = false → urlFor null → "File not found"
```

**Example: a PDF license uploaded by a vendor (still works pre-v10.7):**

```text
Database value:                       vendors/15/xyz789.pdf
Upload disk (was):                    local            → storage/app/private/vendors/15/xyz789.pdf
Preview disk:                         vendors (v10.6)  → storage/app/private/vendors/15/xyz789.pdf
Result:                               same file, exists() = true → signed URL → ✓ works
```

## Repair

### A. New canonical `VendorFileResolver` (NEW file)

`app/Domain/Vendor/VendorFileResolver.php`. Centralizes:
- Disk + path resolution for every vendor file kind
- Path normalization (strip `/storage/`, `storage/app/private/`, `storage/app/public/`, `storage/`, `public/`; convert backslashes; collapse duplicate `vendors/vendors/` prefix; reject `../` traversal and NUL bytes)
- Existence probing across configured disks in priority order (canonical first, legacy fallbacks for old records)
- Returns `{disk, path, is_image, is_canonical}` — telling the caller which disk the file was actually found on

```php
$resolved = VendorFileResolver::resolve($vendor, 'logo');
// ['disk' => 'public', 'path' => 'vendors/15/abc.jpg', 'is_image' => true, 'is_canonical' => true]
```

### B. `VendorFileLinks::urlFor` rewritten

Now delegates to `VendorFileResolver::resolve()`:
- Private kinds (license_document, id_document) → always signed admin route (defense in depth)
- Public kinds (logo, banner) found on the canonical public disk → `Storage::url`
- Public kinds found on a non-public legacy disk → signed admin route (admin can still view; storefront usage is a separate code path)

Returns null only when the file truly does not exist on any configured disk.

### C. `VendorFileController::show` rewritten

Now uses the resolver to find the actual disk + path, then streams. `ALLOWED_KINDS` expanded from `['license_document','id_document']` to **`['license_document','id_document','logo','banner']`** so legacy logo/banner files on the wrong disk can be served through the signed admin route.

### D. `VendorRegistrationController::store` upload routing fixed

```php
foreach ([
    'logo'             => $publicDisk,    // 'public'  (was 'local' — bug)
    'banner'           => $publicDisk,    // 'public'
    'license_document' => $privateDisk,   // 'vendors'
    'id_document'      => $privateDisk,   // 'vendors'
] as $field => $disk) { ... }
```

NEW uploads after v10.7 land on the correct disk. LEGACY records remain readable via the resolver's fallback search.

### E. `config/marketplace.php` adds canonical `vendor_public_disk` key

Mirrors the v10.6 `vendor_private_disk` key. Default `'public'`, env override `VENDOR_PUBLIC_DISK`.

## Path normalization rules (resolver)

| Input | Normalized output | Rule |
|---|---|---|
| `/storage/vendors/1/x.jpg` | `vendors/1/x.jpg` | strip leading `/storage/` |
| `storage/vendors/1/x.jpg` | `vendors/1/x.jpg` | strip `storage/` |
| `public/vendors/1/x.jpg` | `vendors/1/x.jpg` | strip `public/` |
| `storage/app/public/vendors/1/x.jpg` | `vendors/1/x.jpg` | strip `storage/app/public/` |
| `storage/app/private/vendors/1/x.jpg` | `vendors/1/x.jpg` | strip `storage/app/private/` |
| `vendors\\1\\x.jpg` | `vendors/1/x.jpg` | backslash → forward slash |
| `vendors/vendors/1/x.jpg` | `vendors/1/x.jpg` | collapse duplicate prefix |
| `../../etc/passwd` | **null** | reject traversal |
| `vendors/1/x\0.jpg` | **null** | reject NUL byte |
| `""` | **null** | reject empty |

## Tests added (§8 demand)

`tests/Feature/Phase10V107RegressionTest.php` — 18 scenarios using `Storage::fake()`:

| # | Scenario |
|---|---|
| 1-4 | Path normalization (slashes, prefixes, double-vendors collapse, traversal rejection) |
| 5 | `isImage` extension whitelist (jpg/jpeg/png/webp/gif yes; pdf/txt no) |
| 6 | Resolver finds JPG logo on the public disk (new architecture) |
| 7 | Resolver finds LEGACY JPG logo on the local disk via fallback |
| 8 | Resolver finds PDF license on the vendors disk (regression check — PDF flow preserved) |
| 9 | Resolver finds JPG license on the vendors disk |
| 10 | Resolver finds PNG id_document on the vendors disk |
| 11 | Resolver returns null when no file recorded |
| 12 | Resolver returns null when file is missing on every disk |
| 13 | Resolver normalizes a stored path with accidental leading slash |
| 14 | Admin opens JPG logo through signed file route → HTTP 200, image MIME |
| 15 | Admin opens PDF license through signed file route → HTTP 200 (regression) |
| 16 | Non-admin → HTTP 403 |
| 17 | Missing file → controlled HTTP 404 (not a crash) |
| 18 | VERSION = `Phase 10 v10.7` |

## Active files changed (exhaustive)

| File | Change |
|---|---|
| `app/Domain/Vendor/VendorFileResolver.php` | NEW — canonical resolver |
| `app/Domain/Vendor/VendorFileLinks.php` | `urlFor` rewritten to delegate to resolver |
| `app/Http/Controllers/Admin/VendorFileController.php` | Rewritten to use resolver; `ALLOWED_KINDS` expanded to include logo + banner |
| `app/Http/Controllers/Vendor/VendorRegistrationController.php` | Upload routing fixed — logo/banner → public disk, license/ID → vendors disk |
| `config/marketplace.php` | Added `vendor_public_disk` key |
| `tests/Feature/Phase10V107RegressionTest.php` | NEW — 18 Pest scenarios |
| `.github/workflows/ci.yml` | 4 new v10.7 sub-checks + verdict bump |
| `VERSION` | v10.6 → v10.7 |

## Per dev §13 acceptance

Per §14 final clause: "may be described as fixed only after both of these are manually confirmed: PDF vendor documents still open correctly; image vendor documents display and open correctly."

I have NOT run the manual browser test in this sandbox. The static evidence:

- 5 source files modified, all brace-balanced
- 18 Pest scenarios written using `Storage::fake()`
- CI YAML valid (`yaml.safe_load` parses)
- 4 new CI sub-checks enforcing the fix presence at three layers (resolver class, both consumers, upload routing + config)
- v10.1-v10.6 markers all preserved (11/11)
- 53 unique global Pest helpers, 0 duplicates
- The PDF code path (PRIVATE branch in `urlFor`) is structurally unchanged: still issues a signed URL through the same admin route. PDF behavior preservation is structural, not just an assertion.

**Phase 10 v10.7 is implemented but requires developer runtime verification per §9 + §14.**
