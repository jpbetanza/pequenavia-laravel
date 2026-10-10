<?php

use App\Http\Controllers\Agendamentos\DisponibilidadeController;
use App\Http\Controllers\Agendamentos\GrupoController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CampoCertificadoController;
use App\Http\Controllers\CompartilhamentoController;
use App\Http\Controllers\FonteController;
use App\Http\Controllers\GeracaoCertificadoController;
use App\Http\Controllers\HistoricoController;
use App\Http\Controllers\ModeloCertificadoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('fontes/{pasta}/{peso}.ttf', [FonteController::class, 'show'])
    ->where('pasta', '[a-z0-9-]+')
    ->where('peso', '[1-9]00')
    ->name('fontes.show');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => to_route('meus-certificados.index'))->name('dashboard');

    Route::get('historico', [HistoricoController::class, 'index'])->name('historico.index');

    Route::get('meus-certificados', [ModeloCertificadoController::class, 'index'])->name('meus-certificados.index');
    Route::post('modelos', [ModeloCertificadoController::class, 'store'])->name('modelos.store');
    Route::put('modelos/{modelo}', [ModeloCertificadoController::class, 'update'])->name('modelos.update');
    Route::delete('modelos/{modelo}', [ModeloCertificadoController::class, 'destroy'])->name('modelos.destroy');
    Route::get('meus-certificados/{modelo}/campos', [CampoCertificadoController::class, 'edit'])->name('meus-certificados.campos');
    Route::get('meus-certificados/{modelo}/gerar', [GeracaoCertificadoController::class, 'create'])->name('meus-certificados.gerar');

    Route::scopeBindings()->group(function () {
        Route::post('modelos/{modelo}/campos', [CampoCertificadoController::class, 'store'])->name('campos.store');
        Route::patch('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'update'])->name('campos.update');
        Route::delete('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'destroy'])->name('campos.destroy');

        Route::post('modelos/{modelo}/compartilhamentos', [CompartilhamentoController::class, 'store'])->name('compartilhamentos.store');
        Route::patch('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'update'])->name('compartilhamentos.update');
        Route::delete('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'destroy'])->name('compartilhamentos.destroy');
    });

    Route::post('modelos/{modelo}/certificados', [GeracaoCertificadoController::class, 'store'])
        ->middleware('throttle:gerar-certificado')
        ->name('certificados.store');

    Route::prefix('agendamentos')->name('agendamentos')->group(function () {
        Route::get('/', fn () => to_route('agendamentos.calendario'));

        Route::name('.')->group(function () {
            Route::inertia('calendario', 'agendamentos/calendario')->name('calendario');
            Route::inertia('solicitacoes', 'agendamentos/solicitacoes')->name('solicitacoes.index');
            Route::inertia('espacos', 'agendamentos/espacos/index')->name('espacos.index');
            Route::inertia('equipamentos', 'agendamentos/equipamentos/index')->name('equipamentos.index');
            Route::get('grupos', [GrupoController::class, 'index'])->name('grupos.index');
            Route::post('grupos', [GrupoController::class, 'store'])->name('grupos.store');
            Route::put('grupos/{grupo}', [GrupoController::class, 'update'])->name('grupos.update');
            Route::delete('grupos/{grupo}', [GrupoController::class, 'destroy'])->name('grupos.destroy');
            Route::post('disponibilidade', DisponibilidadeController::class)->name('disponibilidade');
        });
    });

    Route::get('modelos/{modelo}/imagem', [ModeloCertificadoController::class, 'imagem'])->name('modelos.imagem');
});

require __DIR__.'/settings.php';
