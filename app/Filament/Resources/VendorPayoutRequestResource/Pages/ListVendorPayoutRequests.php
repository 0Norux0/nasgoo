<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorPayoutRequestResource\Pages;
use App\Filament\Resources\VendorPayoutRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
class ListVendorPayoutRequests extends ListRecords {
    protected static string $resource = VendorPayoutRequestResource::class;
    
}
