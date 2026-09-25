<x-layouts.guest title="Olvidé mi PIN - posPapisV1">

    <x-card padding="p-7" class="w-full max-w-sm shadow-lg">

        <div id="form-screen">
            <p class="text-center text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">¿Olvidaste tu PIN?</p>
            <p class="text-center text-sm text-neutral-500 dark:text-neutral-400 mb-6">Escribe tu correo y te mandamos un enlace para elegir uno nuevo.</p>

            <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Correo</label>
            <input type="email" id="email-input" placeholder="tucorreo@ejemplo.com"
                class="w-full h-12 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm mb-4">

            <x-button id="submit-btn">
                Enviar enlace
            </x-button>

            <a href="{{ route('login.pin') }}" class="block text-center text-sm text-neutral-500 dark:text-neutral-400 mt-4">
                ← Regresar al login
            </a>
        </div>

        <div id="success-screen" class="hidden text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">Revisa tu correo</p>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Si ese correo existe en el sistema, te mandamos un enlace para restablecer tu PIN.</p>
            <a href="{{ route('login.pin') }}" class="text-sm text-primary-600 dark:text-primary-400">← Regresar al login</a>
        </div>
    </x-card>

    <script>
        document.getElementById('submit-btn').addEventListener('click', () => {
            const email = document.getElementById('email-input').value.trim();
            if (!email) return;

            fetch('{{ route("forgot-pin.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email }),
            })
            .then(res => res.json())
            .then(() => {
                document.getElementById('form-screen').classList.add('hidden');
                document.getElementById('success-screen').classList.remove('hidden');
            });
        });
    </script>
</x-layouts.guest>
