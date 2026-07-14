<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceBookings extends ListRecords
{
    protected static string $resource = ServiceBookingResource::class;
}
