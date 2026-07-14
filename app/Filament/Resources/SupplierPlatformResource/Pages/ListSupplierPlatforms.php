<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierPlatformResource\Pages;

use App\Filament\Resources\SupplierPlatformResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSupplierPlatforms extends ListRecords
{
    protected static string $resource = SupplierPlatformResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
