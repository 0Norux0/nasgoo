<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductTranslationResource\Pages;

use App\Filament\Resources\ProductTranslationResource;
use Filament\Resources\Pages\EditRecord;

class EditProductTranslation extends EditRecord
{
    protected static string $resource = ProductTranslationResource::class;
}
