<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCustomizationFieldResource\Pages;

use App\Filament\Resources\ProductCustomizationFieldResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProductCustomizationField extends EditRecord
{
    protected static string $resource = ProductCustomizationFieldResource::class;
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()->whereKey($key)->firstOrFail();
    }
}
