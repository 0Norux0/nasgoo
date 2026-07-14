<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierIntegrationResource\Pages;

use App\Filament\Resources\SupplierIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListSupplierIntegrations extends ListRecords
{
    protected static string $resource = SupplierIntegrationResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }

}
