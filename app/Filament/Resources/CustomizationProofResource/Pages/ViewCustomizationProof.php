<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomizationProofResource\Pages;

use App\Filament\Resources\CustomizationProofResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewCustomizationProof extends ViewRecord
{
    protected static string $resource = CustomizationProofResource::class;
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()->whereKey($key)->firstOrFail();
    }
}
