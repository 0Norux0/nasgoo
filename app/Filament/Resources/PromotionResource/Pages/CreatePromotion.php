<?php
declare(strict_types=1);
namespace App\Filament\Resources\PromotionResource\Pages;
use App\Filament\Resources\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
class CreatePromotion extends CreateRecord {
    protected static string $resource = PromotionResource::class;
}
