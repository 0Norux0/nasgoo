<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;

/**
 * Phase 11B.4 §14 — store profile completion + §16 vendor action checklist.
 *
 * §14 checks the vendor's own real fields. §14 caveat "Do not expose
 * sensitive documents to customers" — this service only reports whether
 * a field is present, never returns the document itself.
 *
 * §16 checklist reads a mix of store-completion + alert data. All items
 * point to real routes; items only appear when there is real evidence.
 */
class VendorActionChecklistService
{
    /**
     * Compute store completion score + missing field list.
     *
     * @return array{score:int, missing_fields:list<string>}
     */
    public function storeCompletion(Vendor $vendor): array
    {
        $checks = [
            'business_name' => !empty($vendor->business_name),
            'logo'          => !empty($vendor->logo_path),
            'banner'        => !empty($vendor->banner_path),
            'description'   => !empty($vendor->description),
            'business_email'=> !empty($vendor->business_email),
            'business_phone'=> !empty($vendor->business_phone),
            'address'       => !empty($vendor->address),
            'country'       => !empty($vendor->country),
        ];

        // Arabic description if the vendor's business is multi-locale
        // — check the JSON translation column if present
        $desc = $vendor->description_translations ?? [];
        if (is_array($desc) && isset($desc['ar'])) {
            $checks['description_arabic'] = !empty($desc['ar']);
        }

        $missing = [];
        foreach ($checks as $k => $v) {
            if (!$v) $missing[] = $k;
        }

        $score = count($checks) > 0
            ? (int) round((count($checks) - count($missing)) / count($checks) * 100)
            : 0;

        return [
            'score' => $score,
            'missing_fields' => $missing,
        ];
    }

    /**
     * Produce the vendor's action checklist as list of items.
     * Only items with real evidence are returned.
     *
     * @return list<array{key:string, title:string, priority:string, link:string, evidence:array<string,mixed>}>
     */
    public function checklistFor(Vendor $vendor, int $lowStockCount, int $missingArabicCount, int $missingImagesCount, int $storeCompletionScore, int $activeSupportTickets = 0): array
    {
        $items = [];

        if ($storeCompletionScore < 80) {
            $items[] = [
                'key'      => 'complete_store_profile',
                'title'    => 'checklist.complete_store_profile',
                'priority' => Alert::PRIORITY_HIGH,
                'link'     => '/vendor/settings',
                'evidence' => ['score' => $storeCompletionScore],
            ];
        }

        if ($lowStockCount > 0) {
            $items[] = [
                'key'      => 'review_low_stock',
                'title'    => 'checklist.review_low_stock',
                'priority' => Alert::PRIORITY_HIGH,
                'link'     => '/vendor/products?filter=low_stock',
                'evidence' => ['count' => $lowStockCount],
            ];
        }

        if ($missingImagesCount > 0) {
            $items[] = [
                'key'      => 'add_product_images',
                'title'    => 'checklist.add_product_images',
                'priority' => Alert::PRIORITY_MEDIUM,
                'link'     => '/vendor/products?filter=missing_images',
                'evidence' => ['count' => $missingImagesCount],
            ];
        }

        if ($missingArabicCount > 0) {
            $items[] = [
                'key'      => 'add_arabic_translations',
                'title'    => 'checklist.add_arabic_translations',
                'priority' => Alert::PRIORITY_MEDIUM,
                'link'     => '/vendor/products?filter=missing_arabic',
                'evidence' => ['count' => $missingArabicCount],
            ];
        }

        if ($activeSupportTickets > 0) {
            $items[] = [
                'key'      => 'respond_support_tickets',
                'title'    => 'checklist.respond_support_tickets',
                'priority' => Alert::PRIORITY_HIGH,
                'link'     => '/vendor/tickets',
                'evidence' => ['count' => $activeSupportTickets],
            ];
        }

        return $items;
    }
}
