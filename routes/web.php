<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\ModeloCertificadoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('meus-certificados', [ModeloCertificadoController::class, 'index'])->name('meus-certificados.index');
    Route::post('modelos', [ModeloCertificadoController::class, 'store'])->name('modelos.store');
    Route::put('modelos/{modelo}', [ModeloCertificadoController::class, 'update'])->name('modelos.update');
    Route::delete('modelos/{modelo}', [ModeloCertificadoController::class, 'destroy'])->name('modelos.destroy');
    Route::get('modelos/{modelo}/imagem', [ModeloCertificadoController::class, 'imagem'])->name('modelos.imagem');
});

require __DIR__.'/settings.php';
