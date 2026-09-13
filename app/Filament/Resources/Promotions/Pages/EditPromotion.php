<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected array $pendingProducts = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['products'] = $this->record->products->map(fn ($product) => [
            'product_id' => $product->id,
            'qty_required' => $product->pivot->qty_required,
        ])->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingProducts = $data['products'] ?? [];
        unset($data['products']);

        return $data;
    }

    protected function afterSave(): void
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