<x-layouts.guest title="Regístrate - Papi's Papas">
    <x-card padding="p-7" class="w-full max-w-sm shadow-lg">
        <div class="text-center mb-6">
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Únete a Papi's Papas</p>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">Regístrate y empieza a sumar sellos</p>
        </div>

        @if ($errors->any())
            <p class="text-sm text-red-500 dark:text-red-400 mb-4 text-center">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('registro.store') }}" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Nombre</label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Tu nombre completo"
                    class="w-full h-12 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </div>
            <div>
                <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Teléfono (10 dígitos)</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" required inputmode="numeric" maxlength="10" placeholder="55 1234 5678"
                    class="w-full h-12 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </div>

            <x-button type="submit">Registrarme</x-button>

            <p class="text-xs text-neutral-400 dark:text-neutral-500 text-center">
                Tu nombre y teléfono se usan únicamente para tu tarjeta de fidelidad de Papi's Papas — no los compartimos con nadie.
            </p>
        </form>
    </x-card>
</x-layouts.guest>
