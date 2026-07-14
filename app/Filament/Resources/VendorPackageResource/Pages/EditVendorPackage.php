<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorPackageResource\Pages;
use App\Filament\Resources\VendorPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditVendorPackage extends EditRecord {
    protected static string $resource = VendorPackageResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
