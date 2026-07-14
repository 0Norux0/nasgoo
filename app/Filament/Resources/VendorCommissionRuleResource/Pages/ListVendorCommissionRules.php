<?php
declare(strict_types=1);
namespace App\Filament\Resources\VendorCommissionRuleResource\Pages;
use App\Filament\Resources\VendorCommissionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListVendorCommissionRules extends ListRecords {
    protected static string $resource = VendorCommissionRuleResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
