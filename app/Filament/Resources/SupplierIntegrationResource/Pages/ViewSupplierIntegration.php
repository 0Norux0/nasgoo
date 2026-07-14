<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierIntegrationResource\Pages;

use App\Filament\Resources\SupplierIntegrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSupplierIntegration extends ViewRecord
{
    protected static string $resource = SupplierIntegrationResource::class;

    /** v5.6 lesson: ensure eager loads apply to the record on Edit/View pages too. */
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()->whereKey($key)->firstOrFail();
    }
}
