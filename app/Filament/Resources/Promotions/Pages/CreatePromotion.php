<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected array $pendingProducts = [];

    protected array $pendingModifiers = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingProducts = $data['products'] ?? [];
        $this->pendingModifiers = $data['modifiers'] ?? [];
        unset($data['products'], $data['modifiers']);

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

        $this->record->modifiers()->sync(
            collect($this->pendingModifiers)->pluck('modifier_id')->filter()->all()
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}