<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOrderResource\Pages;

use App\Filament\Resources\SupplierOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSupplierOrder extends ViewRecord
{
    protected static string $resource = SupplierOrderResource::class;
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()->whereKey($key)->firstOrFail();
    }
}
