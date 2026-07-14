<?php
declare(strict_types=1);
namespace App\Filament\Resources\ProductReviewResource\Pages;
use App\Filament\Resources\ProductReviewResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
class ViewProductReview extends ViewRecord {
    protected static string $resource = ProductReviewResource::class;
    protected function getHeaderActions(): array { return [Actions\EditAction::make()]; }
}
