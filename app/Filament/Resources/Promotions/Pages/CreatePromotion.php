<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected array $pendingProducts = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingProducts = $data['products'] ?? [];
        unset($data['products']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $syncData = [];

        foreach ($this->pendingProducts as $row) {
            if (! empty($row['product_id'])) {
                $syncData[$row['product_id']] = ['qty_required' => $row['qty_required'] ?? 1];
            }
        }

        $this->record->products()->sync($syncData);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}