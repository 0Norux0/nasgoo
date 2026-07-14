<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierIntegrationResource\Pages;

use App\Filament\Resources\SupplierIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplierIntegration extends CreateRecord
{
    protected static string $resource = SupplierIntegrationResource::class;


}
