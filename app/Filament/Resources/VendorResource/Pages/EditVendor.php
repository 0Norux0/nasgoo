<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorResource\Pages;
use App\Filament\Resources\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditVendor extends EditRecord {
    protected static string $resource = VendorResource::class;
    protected function getHeaderActions(): array { return [Actions\ViewAction::make(), Actions\DeleteAction::make()]; }
}
