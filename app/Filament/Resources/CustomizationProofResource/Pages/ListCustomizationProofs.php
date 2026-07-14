<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomizationProofResource\Pages;

use App\Filament\Resources\CustomizationProofResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListCustomizationProofs extends ListRecords
{
    protected static string $resource = CustomizationProofResource::class;

}
