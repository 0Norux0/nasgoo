<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorSubscriptionResource\Pages;
use App\Filament\Resources\VendorSubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListVendorSubscriptions extends ListRecords {
    protected static string $resource = VendorSubscriptionResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
