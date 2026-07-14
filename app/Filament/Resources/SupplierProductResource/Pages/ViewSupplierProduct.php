<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierProductResource\Pages;

use App\Filament\Resources\SupplierProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSupplierProduct extends ViewRecord
{
    protected static string $resource = SupplierProductResource::class;
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()->whereKey($key)->firstOrFail();
    }
}
