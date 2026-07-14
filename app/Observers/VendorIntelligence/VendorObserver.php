<?php

declare(strict_types=1);

namespace App\Observers\VendorIntelligence;

use App\Models\Vendor;
use App\Services\VendorIntelligence\VendorIntelligenceManager;

/**
 * Phase 11B.4 v11B.4.2 Defect 11 fix — mark stale on vendor profile
 * updates so store-completion score reflects fresh data.
 */
class VendorObserver
{
    public function __construct(
        private readonly VendorIntelligenceManager $manager,
    ) {}

    public function updated(Vendor $vendor): void
    {
        $material = ['business_name', 'logo_path', 'banner_path', 'description',
                     'description_translations', 'business_email', 'business_phone',
                     'address', 'country', 'status'];
        $dirty = $vendor->getChanges();
        if (empty(array_intersect_key($dirty, array_flip($material)))) {
            return;
        }
        $this->manager->markVendorStale($vendor->id, 'vendor_profile_updated');
    }
}
