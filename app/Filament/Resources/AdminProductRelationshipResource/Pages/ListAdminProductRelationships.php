<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminProductRelationshipResource\Pages;

use App\Filament\Resources\AdminProductRelationshipResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAdminProductRelationships extends ListRecords
{
    protected static string $resource = AdminProductRelationshipResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
