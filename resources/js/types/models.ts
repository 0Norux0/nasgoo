/**
 * Domain model TypeScript interfaces.
 *
 * Mirrors the Laravel Eloquent models and JSON API resources. Updated
 * phase-by-phase as we add migrations. This file is the single source
 * of truth for frontend types.
 *
 * Phase 0: only Timestamp (shared). Phase 1+ populates User, Vendor,
 * Product, etc.
 */

export interface Timestamp {
    created_at: string;
    updated_at: string;
}
