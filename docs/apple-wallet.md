# Apple Wallet — tarjeta de fidelidad (.pkpass)

El código ya está listo y **apagado** (`APPLE_WALLET_ENABLED=false`). Se enciende cuando estén los certificados.
Convive con Google Wallet: las dos tarjetas salen del mismo contenido (`App\Services\Wallet\LoyaltyCardContent`)
y `App\Services\Wallet\WalletNotifier` avisa a cada una por su lado cuando cambian los sellos.

## Cómo funciona

```
Cliente abre /wallet/apple/{uuid-del-QR}.pkpass  →  iPhone guarda el pase
iPhone  ──POST /wallet/apple/v1/devices/…/registrations/…──▶  se guarda su push token
Venta suma un sello → UpdateAppleWalletPassJob → marca el pase modificado → aviso VACÍO por APNs
iPhone  ──GET  /wallet/apple/v1/devices/…/registrations/…?passesUpdatedSince=…──▶  "cambió este"
iPhone  ──GET  /wallet/apple/v1/passes/{tipo}/{serie}──▶  baja el pase nuevo y lo reemplaza
```

| Pieza | Archivo |
|---|---|
| Contenido común (visitas, próximo premio, premio por cobrar, QR) | `app/Services/Wallet/LoyaltyCardContent.php` |
| Genera y firma el `.pkpass`, manda avisos APNs | `app/Services/AppleWalletService.php` |
| Descarga + "Wallet Web Service" de Apple | `app/Http/Controllers/AppleWalletController.php` |
| Rutas (`wallet/apple/...`, sin CSRF) | `routes/web.php`, `bootstrap/app.php` |
| Actualización en cola (reintentos, uno por cliente) | `app/Jobs/UpdateAppleWalletPassJob.php` |
| Tablas `apple_wallet_passes`, `apple_wallet_registrations` | `database/migrations/2026_10_22_100000_wallet_indices_y_apple_wallet.php` |
| Pruebas | `tests/Feature/AppleWalletTest.php` |

Paquete: [`pkpass/pkpass`](https://github.com/includable/php-pkpass) 2.5 (solo para armar y firmar el pase; trae el
certificado intermedio WWDR G4 de Apple). Los avisos APNs están escritos a mano: la clase `Push` del paquete manda
notificaciones con texto y firma el token de forma que Apple rechaza; los pases necesitan un aviso vacío
autenticado con el certificado del Pass Type ID.

## Paso a paso para encenderlo

### 1. Cuenta de Apple Developer
Se necesita el **Apple Developer Program** (99 USD al año) a nombre del negocio o de quien lo administre.
Anota el **Team ID** (10 caracteres): developer.apple.com → Account → Membership details.

### 2. Crear el Pass Type ID
Certificates, Identifiers & Profiles → Identifiers → **+** → **Pass Type IDs**.
- Description: `Papi's Papas fidelidad`
- Identifier: `pass.com.<tudominio>.papipapas` (no se puede cambiar después)

### 3. Certificado del Pass Type ID
1. En la Mac: **Acceso a Llaveros** → menú *Acceso a Llaveros* → *Asistente para certificados* →
   *Solicitar un certificado de una autoridad de certificación*. Correo, nombre, "Guardado en disco". Sale un `.certSigningRequest`.
2. En developer.apple.com → el Pass Type ID → **Create Certificate** → sube el `.certSigningRequest` → descarga `pass.cer`.
3. Doble clic en `pass.cer` (se instala en el Llavero). En *Mis certificados*, clic derecho sobre
   "Pass Type ID: pass.com…" → **Exportar** → formato `.p12`, con una contraseña.
4. Conviértelo a una sola línea para Railway (Railway no guarda archivos entre despliegues):
   ```bash
   base64 -i Certificados.p12 | tr -d '\n' | pbcopy
   ```
   El certificado vence cada año: hay que repetir el paso 3 y actualizar la variable.

### 4. Variables en Railway (servicios **web** y **worker**)
```
APPLE_WALLET_ENABLED=true
APPLE_WALLET_PASS_TYPE_ID=pass.com.<tudominio>.papipapas
APPLE_WALLET_TEAM_ID=XXXXXXXXXX
APPLE_WALLET_CERTIFICATE_P12_BASE64=<lo que copiaste>
APPLE_WALLET_CERTIFICATE_PASSWORD=<contraseña del .p12>
# Opcionales (colores del pase, por defecto negro/blanco/amarillo del logo)
APPLE_WALLET_BACKGROUND_COLOR=rgb(0, 0, 0)
APPLE_WALLET_FOREGROUND_COLOR=rgb(255, 255, 255)
APPLE_WALLET_LABEL_COLOR=rgb(245, 179, 47)
```
`APP_URL` debe ser la URL **https** de producción: el pase lleva `APP_URL/wallet/apple` como `webServiceURL` y
Apple no acepta http.

### 5. Probar
1. En un iPhone, abre `https://<tu-app>/wallet/apple/<qr_code del cliente>.pkpass` (Safari). Debe aparecer "Agregar".
2. Haz una venta a ese cliente. En segundos (si el worker corre) el pase muestra la visita nueva y el iPhone
   avisa "Ya llevas N de 8 visitas".
3. Si algo falla, el iPhone manda sus errores a `/wallet/apple/v1/log` → aparecen en los logs de Railway como
   `Apple Wallet (iPhone): …`. En el iPhone: Ajustes → Desarrollador → activar "Registro de PassKit" ayuda.

### 6. Mostrar el botón al cliente
Por decisión de diseño **no se tocó ninguna pantalla**. El enlace ya funciona y se puede mandar por WhatsApp.
Cuando se quiera, agregar a `registro/confirmacion` el botón oficial "Add to Apple Wallet"
([recursos de marca de Apple](https://developer.apple.com/wallet/add-to-apple-wallet-guidelines/)) apuntando a
`route('wallet.apple.download', $customer->qr_code)`.

## Requisito para Google y Apple: el worker
Las actualizaciones de las tarjetas viajan por la **cola**. En Railway debe existir un servicio **worker** con
start command `bash worker.sh` (mismas variables que web). Sin él, las tarjetas nunca se actualizan y tampoco
corre el respaldo de las 3 a.m.
