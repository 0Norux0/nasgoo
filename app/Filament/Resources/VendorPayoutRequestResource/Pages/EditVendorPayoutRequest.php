<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorPayoutRequestResource\Pages;
use App\Filament\Resources\VendorPayoutRequestResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
class EditVendorPayoutRequest extends EditRecord {
    protected static string $resource = VendorPayoutRequestResource::class;
    protected function getHeaderActions(): array { return [Actions\ViewAction::make()]; }
}
