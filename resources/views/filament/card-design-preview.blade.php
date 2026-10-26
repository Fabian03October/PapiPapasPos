{{-- Vista previa de "Mi tarjeta" en el panel (CardDesignPage). Estilos en
     línea: el panel no carga los estilos de la tarjeta. Ejemplo: 5 de 8. --}}
@php
    $d = $design;
    $stamps = [1, 2, 3, 4, 5, 6, 7, 8];
    $filled = 5;
@endphp
<div style="position: sticky; top: 1rem;">
    <p style="font-size: 0.8rem; color: #71717a; margin: 0 0 0.5rem; text-align: center;">Vista previa (así la ve el cliente)</p>
    <div style="max-width: 300px; margin: 0 auto; border-radius: 2rem; border: 8px solid #27272a; overflow: hidden; background: {{ $d['bg'] }}; color: {{ $d['fg'] }}; font-family: system-ui, -apple-system, sans-serif;">
        <div style="padding: 14px 12px 18px; display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; justify-content: center;">
                <img src="{{ $d['logo_url'] }}" alt="Logo" style="height: 56px; max-width: 80%; object-fit: contain;{{ ($d['logo_badge'] ?? false) ? ' background:#0a0a0a; border-radius: 12px; padding: 4px 8px;' : '' }}">
            </div>

            <div style="background: {{ $d['accent'] }}; color: {{ $d['on_accent'] }}; border-radius: 14px; padding: 10px 12px; font-size: 11px;">
                <strong style="font-size: 13px;">¡Bienvenido, Ana!</strong><br>
                {{ $d['welcome_message'] ?? "Ya eres parte de Papi's Papas. Guarda esta tarjeta en tu celular (abajo te decimos cómo) y muéstrala en cada visita." }}
            </div>

            <div style="background: #fff; color: #111; border-radius: 18px; padding: 12px; text-align: center;">
                <div style="font-size: 9px; letter-spacing: .12em; text-transform: uppercase; color: #71717a; font-weight: 600;">{{ $d['title'] }}</div>
                <div style="font-weight: 700; font-size: 15px; margin-top: 2px;">Ana López</div>
                <img alt="QR de ejemplo" style="width: 110px; height: 110px; margin: 8px auto; display: block;"
                    src="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(0)->generate('ejemplo-papis-papas')) }}">
                <div style="font-size: 10px; color: #52525b;">Muestra este código en caja en cada visita</div>
            </div>

            <div style="background: {{ $d['panel'] }}; border-radius: 18px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <strong style="font-size: 13px;">Tus sellos</strong>
                    <span style="font-size: 11px; color: {{ $d['muted'] }};"><b style="font-size: 16px; color: {{ $d['accent'] }};">{{ $filled }}</b> de 8</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 10px;">
                    @foreach ($stamps as $n)
                        @php $on = $n <= $filled; @endphp
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                            <div style="width: 42px; height: 42px; border-radius: 999px; display: flex; align-items: center; justify-content: center; font-size: 20px;
                                {{ $on ? 'background:'.$d['accent'].';' : 'border: 2px dashed '.$d['line'].';' }}">
                                @if ($on)
                                    @if ($d['stamp_image_url'])
                                        <img src="{{ $d['stamp_image_url'] }}" alt="" style="width: 28px; height: 28px; object-fit: contain;">
                                    @else
                                        {{ $d['stamp_emoji'] }}
                                    @endif
                                @elseif ($n === 4)
                                    <span style="font-size: 12px; font-weight: 700; color: {{ $d['accent'] }};">{{ $d['discount_icon'] }}</span>
                                @elseif ($n === 8)
                                    {{ $d['gift_icon'] }}
                                @endif
                            </div>
                            <span style="font-size: 9px; color: {{ $d['muted'] }};">{{ $n }}</span>
                        </div>
                    @endforeach
                </div>
                <div style="margin-top: 10px; background: {{ $d['panel_2'] }}; border-radius: 10px; padding: 8px 10px; font-size: 11px;">
                    <span style="color: {{ $d['muted'] }};">Próximo premio:</span> Producto gratis · faltan 3 visitas
                </div>
            </div>

            <div style="background: {{ $d['accent'] }}; color: {{ $d['on_accent'] }}; border-radius: 18px; padding: 12px;">
                <div style="font-size: 9px; letter-spacing: .12em; text-transform: uppercase; font-weight: 600;">Premio por cobrar</div>
                <div style="font-weight: 700; font-size: 14px; margin-top: 2px;">{{ $d['gift_icon'] }} 10% de descuento</div>
            </div>

            @if ($d['contact_info'])
                <div style="background: {{ $d['panel'] }}; border-radius: 18px; padding: 12px; font-size: 11px;">
                    <strong style="font-size: 13px;">Visítanos</strong>
                    <div style="white-space: pre-line; margin-top: 4px;">{{ $d['contact_info'] }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
