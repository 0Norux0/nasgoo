<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierProductResource\Pages;

use App\Filament\Resources\SupplierProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListSupplierProducts extends ListRecords
{
    protected static string $resource = SupplierProductResource::class;

}
