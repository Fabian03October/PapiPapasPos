<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login.pin');
});

Route::get('/login', [AuthController::class, 'showPinLogin'])->name('login.pin');
Route::post('/login', [AuthController::class, 'loginPin'])->name('login.pin.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/venta', function () {
    return 'Login exitoso, bienvenido ' . auth()->user()->name;
})->name('venta.index')->middleware('auth');