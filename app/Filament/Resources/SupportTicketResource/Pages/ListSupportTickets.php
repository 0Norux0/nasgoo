<?php
declare(strict_types=1);
namespace App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Resources\SupportTicketResource;
use Filament\Resources\Pages\ListRecords;
class ListSupportTickets extends ListRecords {
    protected static string $resource = SupportTicketResource::class;
}
