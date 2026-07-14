<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorPayoutRequestResource\Pages;
use App\Filament\Resources\VendorPayoutRequestResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
class ViewVendorPayoutRequest extends ViewRecord {
    protected static string $resource = VendorPayoutRequestResource::class;
    protected function getHeaderActions(): array { return [Actions\EditAction::make()]; }
}
