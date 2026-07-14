<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceBookingResource\Pages;

use App\Filament\Resources\ServiceBookingResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceBooking extends EditRecord
{
    protected static string $resource = ServiceBookingResource::class;
}
