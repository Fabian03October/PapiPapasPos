{{-- Barra de arriba del admin. Cuando el admin se abre dentro del marco del
     POS (para no cortar la impresora), la barra del POS se esconde y este es
     el camino de regreso; abierto suelto, también lleva a ventas. --}}
<x-filament::button tag="a" :href="route('venta.index')" icon="heroicon-o-arrow-left" size="sm" color="gray" outlined>
    Volver a ventas
</x-filament::button>
