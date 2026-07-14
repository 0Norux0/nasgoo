<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminProductRelationshipResource\Pages;

use App\Filament\Resources\AdminProductRelationshipResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminProductRelationship extends CreateRecord
{
    protected static string $resource = AdminProductRelationshipResource::class;

    // Stamp created_by on save (audit per dev §15)
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
