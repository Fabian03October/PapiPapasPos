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
                            <p style="margin: 0 0 8px; font-size: 18px; color: #18181b;">¡Hola, {{ $user->name }}!</p>
                            <p style="margin: 0 0 24px; font-size: 14px; color: #71717a;">
                                Pediste restablecer tu PIN. Da clic en el botón para elegir uno nuevo. Este enlace expira en 1 hora.
                            </p>
                            <a href="{{ $resetUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: bold;">
                                Elegir nuevo PIN
                            </a>
                            <p style="margin: 24px 0 0; font-size: 12px; color: #a1a1aa;">
                                Si tú no pediste esto, ignora este correo — tu PIN actual sigue funcionando normal.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>