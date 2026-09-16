<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5; padding: 32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td style="background-color: #2563eb; padding: 24px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 20px; font-weight: bold;">posPapisV1</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 18px; color: #111827;">¡Hola, {{ $customer->name }}!</p>
                            <p style="margin: 0 0 24px; font-size: 14px; color: #6b7280;">
                                Este es tu taregeta de fidelidad. Muéstralo cada vez que vengas para sumar sellos y desbloquear premios.
                            </p>
                            <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="Código QR" style="width: 220px; height: 220px;">
                            <p style="margin: 24px 0 0; font-size: 12px; color: #9ca3af;">
                                Guarda este correo — el cajero solo necesita escanear este código.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>