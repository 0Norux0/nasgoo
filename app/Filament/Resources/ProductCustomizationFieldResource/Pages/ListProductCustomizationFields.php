<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCustomizationFieldResource\Pages;

use App\Filament\Resources\ProductCustomizationFieldResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListProductCustomizationFields extends ListRecords
{
    protected static string $resource = ProductCustomizationFieldResource::class;

}
