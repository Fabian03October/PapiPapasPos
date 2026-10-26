<?php

use App\Http\Controllers\AppleWalletController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\CustomerRegistrationController;
use App\Http\Controllers\ForgotPinController;
use App\Http\Controllers\PrintStationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleIncidentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VentaController;
use App\Http\Middleware\WorkerWatchdog;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return redirect()->route('login.pin');
});

Route::get('/login', [AuthController::class, 'showPinLogin'])->name('login.pin');
// Límite de intentos: el PIN es de 4 dígitos (10,000 combinaciones).
Route::post('/login', [AuthController::class, 'loginPin'])->name('login.pin.submit')->middleware('throttle:login-pin');
Route::post('/login/cambiar-pin', [AuthController::class, 'changePin'])->name('login.change-pin')->middleware('auth');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/login/olvide-pin', [ForgotPinController::class, 'showRequestForm'])->name('forgot-pin.show');
Route::post('/login/olvide-pin', [ForgotPinController::class, 'sendResetLink'])->name('forgot-pin.send')->middleware('throttle:olvide-pin');
Route::get('/login/restablecer-pin/{token}', [ForgotPinController::class, 'showResetForm'])->name('forgot-pin.reset.show');
Route::post('/login/restablecer-pin', [ForgotPinController::class, 'resetPin'])->name('forgot-pin.reset')->middleware('throttle:restablecer-pin');

Route::get('/registro', [CustomerRegistrationController::class, 'showForm'])->name('registro.form');
Route::post('/registro', [CustomerRegistrationController::class, 'store'])->name('registro.store')->middleware('throttle:registro');
// "Mi tarjeta" del cliente (app web instalable, funciona sin internet).
// Sin sesión ni cookies: el cliente no inicia sesión (su enlace es la llave).
// Antes cada visita creaba una fila en "sessions"; con miles de clientes
// abriendo su tarjeta tras una promo, era la carga más pesada.
Route::prefix('mi-tarjeta/{qrCode}')
    ->middleware('throttle:mi-tarjeta')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
    ])
    ->group(function () {
        Route::get('/', [CustomerPortalController::class, 'show'])->name('mi-tarjeta');
        Route::get('/datos', [CustomerPortalController::class, 'data'])->name('mi-tarjeta.datos');
        Route::get('/manifest.webmanifest', [CustomerPortalController::class, 'manifest'])->name('mi-tarjeta.manifest');
        Route::post('/avisos', [CustomerPortalController::class, 'subscribe'])->name('mi-tarjeta.avisos');
        Route::delete('/avisos', [CustomerPortalController::class, 'unsubscribe']);
        Route::post('/promociones/{message}/ocultar', [CustomerPortalController::class, 'hideMessage'])->name('mi-tarjeta.ocultar');
    });

// Apple Wallet: la descarga usa el mismo enlace secreto (UUID) que la
// confirmación de registro; las rutas v1/ son el "Wallet Web Service" que
// llama el iPhone para registrarse y bajar el pase actualizado.
Route::prefix('wallet/apple')->middleware('throttle:60,1')->group(function () {
    Route::get('/{qrCode}.pkpass', [AppleWalletController::class, 'download'])->name('wallet.apple.download');
    Route::post('/v1/devices/{device}/registrations/{passType}/{serial}', [AppleWalletController::class, 'register']);
    Route::delete('/v1/devices/{device}/registrations/{passType}/{serial}', [AppleWalletController::class, 'unregister']);
    Route::get('/v1/devices/{device}/registrations/{passType}', [AppleWalletController::class, 'serials']);
    Route::get('/v1/passes/{passType}/{serial}', [AppleWalletController::class, 'latest']);
    Route::post('/v1/log', [AppleWalletController::class, 'log']);
});

Route::get('/registro/confirmacion/{qrCode}', [CustomerRegistrationController::class, 'confirmation'])->name('registro.confirmacion');

// El vigilante del worker revisa (cada 10 min, mientras se usa el POS) que
// la cola y el programador sigan vivos; si no, manda un correo.
Route::middleware(['auth', WorkerWatchdog::class])->group(function () {
    Route::get('/caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::post('/caja/abrir', [CajaController::class, 'abrirStore'])->name('caja.abrir.store');

    Route::get('/caja', [CajaController::class, 'resumen'])->name('caja.resumen');
    Route::post('/caja/movimientos', [CajaController::class, 'addMovement'])->name('caja.movimientos.store');
    Route::post('/caja/fondo', [CajaController::class, 'updateFund'])->name('caja.fondo');
    Route::get('/caja/cerrar/inventario', [CajaController::class, 'showInventoryCount'])->name('caja.cerrar.inventario');
    Route::post('/caja/cerrar/inventario', [CajaController::class, 'storeInventoryCount'])->name('caja.cerrar.inventario.store');
    Route::get('/caja/cerrar', [CajaController::class, 'showClose'])->name('caja.cerrar');
    Route::post('/caja/cerrar', [CajaController::class, 'closeStore'])->name('caja.cerrar.store');
    Route::get('/caja/{session}/reporte-cierre', [CajaController::class, 'reporteCierre'])->name('caja.reporte-cierre');
    Route::get('/caja/{session}/reporte-cierre-bluetooth', [CajaController::class, 'reporteCierreBluetooth'])->name('caja.reporte-cierre.bt');

    Route::get('/venta', [VentaController::class, 'index'])->name('venta.index');
    Route::get('/venta/productos/{product}', [VentaController::class, 'productDetails'])->name('venta.product.details');

    Route::post('/venta/cobrar', [SaleController::class, 'store'])->name('venta.cobrar');

    Route::post('/venta/promociones/preview', [SaleController::class, 'preview'])->name('venta.promotions.preview');

    Route::get('/venta/cliente', [VentaController::class, 'clientePage'])->name('venta.cliente');
    Route::get('/venta/cliente/seleccionar/{customer}', [VentaController::class, 'selectCustomer'])->name('venta.customers.select');
    Route::get('/venta/cliente/quitar', [VentaController::class, 'clearCustomer'])->name('venta.customers.clear');

    Route::get('/venta/clientes/buscar', [VentaController::class, 'searchCustomers'])->name('venta.customers.search');
    Route::post('/venta/clientes', [VentaController::class, 'storeCustomer'])->name('venta.customers.store');
    Route::get('/venta/clientes/{customer}', [VentaController::class, 'showCustomer'])->name('venta.customers.show');
    Route::post('/venta/clientes/{customer}', [VentaController::class, 'updateCustomer'])->name('venta.customers.update');
    Route::get('/venta/clientes/qr/{qrCode}', [VentaController::class, 'findCustomerByQr'])->name('venta.customers.byqr');

    Route::get('/venta/historial', [SaleIncidentController::class, 'shiftHistory'])->name('venta.historial');
    Route::post('/venta/incidencias', [SaleIncidentController::class, 'store'])->name('venta.incidencias.store');
    Route::post('/venta/{sale}/deshacer', [SaleIncidentController::class, 'undo'])->name('venta.deshacer');

    Route::get('/venta/{sale}/ticket', [TicketController::class, 'venta'])->name('venta.ticket');
    Route::get('/venta/{sale}/comanda', [TicketController::class, 'comanda'])->name('venta.comanda');
    Route::get('/venta/{sale}/impresion-bluetooth', [TicketController::class, 'bluetooth'])->name('venta.bluetooth');
    Route::get('/prueba-impresora', [TicketController::class, 'pruebaImpresora'])->name('impresora.prueba');

    Route::get('/impresion/pendientes', [PrintStationController::class, 'pending'])->name('print-station.pending');
    Route::post('/impresion/{sale}/marcar', [PrintStationController::class, 'markPrinted'])->name('print-station.mark');
    Route::post('/impresion/{sale}/liberar', [PrintStationController::class, 'release'])->name('print-station.release');
});
