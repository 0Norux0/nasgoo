<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductTranslationResource\Pages;

use App\Filament\Resources\ProductTranslationResource;
use Filament\Resources\Pages\ListRecords;

class ListProductTranslations extends ListRecords
{
    protected static string $resource = ProductTranslationResource::class;
}
