<x-layouts.guest title="Nuevo PIN - posPapisV1">

    <x-card padding="p-7" class="w-full max-w-sm shadow-lg">

        <div id="form-screen">
            <p class="text-center text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">Elige tu nuevo PIN</p>
            <p class="text-center text-sm text-neutral-500 dark:text-neutral-400 mb-6">Este será tu PIN para entrar de ahora en adelante</p>

            <p id="error-msg" class="text-center text-sm text-red-500 dark:text-red-400 mb-3 hidden"></p>

            <p class="text-xs text-neutral-400 dark:text-neutral-500 text-center mb-2">Nuevo PIN</p>
            <x-pin-dots id="new-pin-dots" class="mb-5" />

            <p class="text-xs text-neutral-400 dark:text-neutral-500 text-center mb-2">Confirma tu PIN</p>
            <x-pin-dots id="confirm-pin-dots" class="mb-6" />

            <x-pin-keypad key-class="pin-key" backspace-id="backspace-btn" />
        </div>

        <div id="success-screen" class="hidden text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">¡Listo!</p>
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Tu PIN se actualizó correctamente.</p>
            <a href="{{ route('login.pin') }}" class="inline-block bg-primary-600 text-white rounded-lg py-3 px-6 text-sm font-medium">Ir al login</a>
        </div>
    </x-card>

    <script src="{{ asset('js/pin-input.js') }}"></script>
    <script>
        const newPinDots = document.querySelectorAll('#new-pin-dots .dot');
        const confirmPinDots = document.querySelectorAll('#confirm-pin-dots .dot');
        const errorMsg = document.getElementById('error-msg');
        let newPin = '';
        let confirmPin = '';
        let stage = 'new';

        function renderDots() {
            PinInput.renderDots(newPinDots, newPin.length);
            PinInput.renderDots(confirmPinDots, confirmPin.length);
        }

        document.querySelectorAll('.pin-key').forEach(key => {
            key.addEventListener('click', () => {
                const digit = key.textContent.trim();
                if (stage === 'new') {
                    if (newPin.length >= 4) return;
                    newPin += digit;
                    if (newPin.length === 4) stage = 'confirm';
                } else {
                    if (confirmPin.length >= 4) return;
                    confirmPin += digit;
                    if (confirmPin.length === 4) submitPin();
                }
                renderDots();
            });
        });

        document.getElementById('backspace-btn').addEventListener('click', () => {
            if (stage === 'confirm' && confirmPin.length > 0) {
                confirmPin = confirmPin.slice(0, -1);
            } else if (stage === 'confirm' && confirmPin.length === 0) {
                stage = 'new';
                newPin = newPin.slice(0, -1);
            } else {
                newPin = newPin.slice(0, -1);
            }
            renderDots();
        });

        function submitPin() {
            errorMsg.classList.add('hidden');

            if (newPin !== confirmPin) {
                errorMsg.textContent = 'Los PIN no coinciden, intenta de nuevo';
                errorMsg.classList.remove('hidden');
                newPin = '';
                confirmPin = '';
                stage = 'new';
                renderDots();
                return;
            }

            fetch('{{ route("forgot-pin.reset") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: '{{ $email }}',
                    token: '{{ $token }}',
                    new_pin: newPin,
                }),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    document.getElementById('form-screen').classList.add('hidden');
                    document.getElementById('success-screen').classList.remove('hidden');
                } else {
                    errorMsg.textContent = data.message || 'Ocurrió un error';
                    errorMsg.classList.remove('hidden');
                    newPin = '';
                    confirmPin = '';
                    stage = 'new';
                    renderDots();
                }
            });
        }
    </script>
</x-layouts.guest>
