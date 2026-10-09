<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CampoCertificadoController;
use App\Http\Controllers\CompartilhamentoController;
use App\Http\Controllers\HistoricoController;
use App\Http\Controllers\ModeloCertificadoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('historico', [HistoricoController::class, 'index'])->name('historico.index');

    Route::get('meus-certificados', [ModeloCertificadoController::class, 'index'])->name('meus-certificados.index');
    Route::post('modelos', [ModeloCertificadoController::class, 'store'])->name('modelos.store');
    Route::put('modelos/{modelo}', [ModeloCertificadoController::class, 'update'])->name('modelos.update');
    Route::delete('modelos/{modelo}', [ModeloCertificadoController::class, 'destroy'])->name('modelos.destroy');
    Route::get('meus-certificados/{modelo}/campos', [CampoCertificadoController::class, 'edit'])->name('meus-certificados.campos');

    Route::scopeBindings()->group(function () {
        Route::post('modelos/{modelo}/campos', [CampoCertificadoController::class, 'store'])->name('campos.store');
        Route::patch('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'update'])->name('campos.update');
        Route::delete('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'destroy'])->name('campos.destroy');

        Route::post('modelos/{modelo}/compartilhamentos', [CompartilhamentoController::class, 'store'])->name('compartilhamentos.store');
        Route::patch('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'update'])->name('compartilhamentos.update');
        Route::delete('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'destroy'])->name('compartilhamentos.destroy');
    });

    Route::get('modelos/{modelo}/imagem', [ModeloCertificadoController::class, 'imagem'])->name('modelos.imagem');
});

require __DIR__.'/settings.php';
