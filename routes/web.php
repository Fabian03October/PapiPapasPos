<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ForgotPinController;

Route::get('/', function () {
    return redirect()->route('login.pin');
});

Route::get('/login', [AuthController::class, 'showPinLogin'])->name('login.pin');
Route::post('/login', [AuthController::class, 'loginPin'])->name('login.pin.submit');
Route::post('/login/cambiar-pin', [AuthController::class, 'changePin'])->name('login.change-pin')->middleware('auth');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/login/olvide-pin', [ForgotPinController::class, 'showRequestForm'])->name('forgot-pin.show');
Route::post('/login/olvide-pin', [ForgotPinController::class, 'sendResetLink'])->name('forgot-pin.send');
Route::get('/login/restablecer-pin/{token}', [ForgotPinController::class, 'showResetForm'])->name('forgot-pin.reset.show');
Route::post('/login/restablecer-pin', [ForgotPinController::class, 'resetPin'])->name('forgot-pin.reset');


Route::middleware('auth')->group(function () {
    Route::get('/caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::post('/caja/abrir', [CajaController::class, 'abrirStore'])->name('caja.abrir.store');

    Route::get('/caja', [CajaController::class, 'resumen'])->name('caja.resumen');
    Route::post('/caja/movimientos', [CajaController::class, 'addMovement'])->name('caja.movimientos.store');
    Route::get('/caja/cerrar', [CajaController::class, 'showClose'])->name('caja.cerrar');
    Route::post('/caja/cerrar', [CajaController::class, 'closeStore'])->name('caja.cerrar.store');

    Route::get('/venta', [VentaController::class, 'index'])->name('venta.index');
    Route::get('/venta/productos/{product}', [VentaController::class, 'productDetails'])->name('venta.product.details');

    Route::post('/venta/cobrar', [SaleController::class, 'store'])->name('venta.cobrar');

    Route::post('/venta/promociones/preview', [SaleController::class, 'preview'])->name('venta.promotions.preview');

    Route::get('/venta/cliente', [VentaController::class, 'clientePage'])->name('venta.cliente');
    Route::get('/venta/cliente/seleccionar/{customer}', [VentaController::class, 'selectCustomer'])->name('venta.customers.select');
    Route::get('/venta/cliente/quitar', [VentaController::class, 'clearCustomer'])->name('venta.customers.clear');

    Route::get('/venta/clientes/buscar', [VentaController::class, 'searchCustomers'])->name('venta.customers.search');
    Route::get('/venta/clientes/qr/{qrCode}', [VentaController::class, 'findCustomerByQr'])->name('venta.customers.byqr');
    Route::post('/venta/clientes', [VentaController::class, 'quickRegisterCustomer'])->name('venta.customers.store');

});