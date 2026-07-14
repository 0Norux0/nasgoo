<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorSubscriptionResource\Pages;
use App\Filament\Resources\VendorSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditVendorSubscription extends EditRecord {
    protected static string $resource = VendorSubscriptionResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
