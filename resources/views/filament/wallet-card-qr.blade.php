{{-- QR para que el cliente abra SU tarjeta en SU teléfono (ver CustomersTable). --}}
<div class="text-center space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Pide al cliente que escanee este código con la cámara de su teléfono: se abre
        <strong>su tarjeta</strong>, que puede guardar en el celular (iPhone o Android).
    </p>
    <img alt="Código QR de la tarjeta de {{ $customer->name }}" class="mx-auto rounded-lg bg-white p-2"
        src="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(1)->generate($url)) }}">
    <p class="text-xs text-gray-500 dark:text-gray-400 break-all select-all">{{ $url }}</p>
    <p class="text-xs text-gray-500 dark:text-gray-400">
        No lo abras en este dispositivo: es la tarjeta personal del cliente.
    </p>
</div>
