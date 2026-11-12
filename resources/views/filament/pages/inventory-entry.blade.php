<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">Guardar entradas</x-filament::button>
    </form>

    {{ $this->table }}
</x-filament-panels::page>
