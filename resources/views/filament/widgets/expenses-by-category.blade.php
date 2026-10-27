<x-filament-widgets::widget>
    <x-filament::section heading="Gastos por categoría">
        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay gastos en este periodo.</p>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($rows as $nombre => $monto)
                    @php $pct = $total > 0 ? round($monto / $total * 100) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $nombre }}</span>
                            <span class="text-gray-600 dark:text-gray-300">${{ number_format($monto, 2) }} · {{ $pct }}%</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-2 rounded-full bg-danger-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
                <p class="pt-2 text-right text-sm font-semibold text-gray-950 dark:text-white">Total: ${{ number_format($total, 2) }}</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
