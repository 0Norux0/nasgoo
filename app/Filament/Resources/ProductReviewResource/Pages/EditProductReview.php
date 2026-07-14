<?php
declare(strict_types=1);
namespace App\Filament\Resources\ProductReviewResource\Pages;
use App\Filament\Resources\ProductReviewResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
class EditProductReview extends EditRecord {
    protected static string $resource = ProductReviewResource::class;
    protected function getHeaderActions(): array { return [Actions\ViewAction::make()]; }
}
