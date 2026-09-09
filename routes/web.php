<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;

Route::get('/', function () {
    return redirect()->route('login.pin');
});

Route::get('/login', [AuthController::class, 'showPinLogin'])->name('login.pin');
Route::post('/login', [AuthController::class, 'loginPin'])->name('login.pin.submit');
Route::post('/login/cambiar-pin', [AuthController::class, 'changePin'])->name('login.change-pin')->middleware('auth');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::post('/caja/abrir', [CajaController::class, 'abrirStore'])->name('caja.abrir.store');

    Route::get('/venta', [VentaController::class, 'index'])->name('venta.index');
    Route::get('/venta/productos/{product}', [VentaController::class, 'productDetails'])->name('venta.product.details');

    Route::post('/venta/cobrar', [SaleController::class, 'store'])->name('venta.cobrar');
    
});