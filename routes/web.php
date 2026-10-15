<?php

use App\Http\Controllers\Agendamentos\DisponibilidadeController;
use App\Http\Controllers\Agendamentos\EquipamentoController;
use App\Http\Controllers\Agendamentos\EspacoController;
use App\Http\Controllers\Agendamentos\GrupoController;
use App\Http\Controllers\Agendamentos\ReservaController;
use App\Http\Controllers\Agendamentos\ReservaEquipamentoController;
use App\Http\Controllers\Agendamentos\SolicitacaoController;
use App\Http\Controllers\Agendamentos\SolicitacaoPublicaController;
use App\Http\Controllers\Assistente\AcaoPropostaController;
use App\Http\Controllers\Assistente\ConversaController;
use App\Http\Controllers\Assistente\MensagemController;
use App\Http\Controllers\Assistente\SugestoesController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CampoCertificadoController;
use App\Http\Controllers\CompartilhamentoController;
use App\Http\Controllers\FonteController;
use App\Http\Controllers\GeracaoCertificadoController;
use App\Http\Controllers\Gestao\ClienteController;
use App\Http\Controllers\Gestao\ConversaController as GestaoConversaController;
use App\Http\Controllers\Gestao\EscopoController;
use App\Http\Controllers\Gestao\TemaController;
use App\Http\Controllers\Gestao\VisaoGeralController;
use App\Http\Controllers\HistoricoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\ModeloCertificadoController;
use App\Http\Controllers\PrimeirosPassosController;
use App\Http\Controllers\VersaoController;
use App\Http\Middleware\GarantirAssistenteConfigurado;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('versao', VersaoController::class)->name('versao');

Route::get('fontes/{pasta}/{peso}.ttf', [FonteController::class, 'show'])
    ->where('pasta', '[a-z0-9-]+')
    ->where('peso', '[1-9]00')
    ->name('fontes.show');

Route::get('reservar/{slug}', [SolicitacaoPublicaController::class, 'create'])->name('reservar.create');
Route::post('reservar/{slug}', [SolicitacaoPublicaController::class, 'store'])
    ->middleware('throttle:solicitacoes')
    ->name('reservar.store');
Route::get('reservar/{slug}/enviado', [SolicitacaoPublicaController::class, 'enviado'])->name('reservar.enviado');

Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => to_route('inicio'))->name('dashboard');
    Route::get('inicio', InicioController::class)->name('inicio');
    Route::post('primeiros-passos', [PrimeirosPassosController::class, 'update'])->name('primeiros-passos.update');

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
            Route::get('calendario', [ReservaController::class, 'calendario'])->name('calendario');
            Route::post('reservas', [ReservaController::class, 'store'])->name('reservas.store');
            Route::put('reservas/{reserva}', [ReservaController::class, 'update'])->name('reservas.update');
            Route::delete('reservas/{reserva}', [ReservaController::class, 'destroy'])->name('reservas.destroy');
            Route::get('solicitacoes', [SolicitacaoController::class, 'index'])->name('solicitacoes.index');
            Route::put('solicitacoes/{solicitacao}', [SolicitacaoController::class, 'update'])->name('solicitacoes.update');
            Route::post('solicitacoes/{solicitacao}/aprovar', [SolicitacaoController::class, 'aprovar'])->name('solicitacoes.aprovar');
            Route::post('solicitacoes/{solicitacao}/recusar', [SolicitacaoController::class, 'recusar'])->name('solicitacoes.recusar');
            Route::get('espacos', [EspacoController::class, 'index'])->name('espacos.index');
            Route::get('espacos/novo', [EspacoController::class, 'create'])->name('espacos.create');
            Route::post('espacos', [EspacoController::class, 'store'])->name('espacos.store');
            Route::get('espacos/{espaco}/editar', [EspacoController::class, 'edit'])->name('espacos.edit');
            Route::put('espacos/{espaco}', [EspacoController::class, 'update'])->name('espacos.update');
            Route::delete('espacos/{espaco}', [EspacoController::class, 'destroy'])->name('espacos.destroy');
            Route::get('equipamentos', [EquipamentoController::class, 'index'])->name('equipamentos.index');
            Route::post('equipamentos', [EquipamentoController::class, 'store'])->name('equipamentos.store');
            Route::put('equipamentos/{equipamento}', [EquipamentoController::class, 'update'])->name('equipamentos.update');
            Route::delete('equipamentos/{equipamento}', [EquipamentoController::class, 'destroy'])->name('equipamentos.destroy');
            Route::scopeBindings()->group(function () {
                Route::patch('reservas/{reserva}/equipamentos/{equipamento}', [ReservaEquipamentoController::class, 'update'])->name('reservas.equipamentos.update');
            });
            Route::get('grupos', [GrupoController::class, 'index'])->name('grupos.index');
            Route::post('grupos', [GrupoController::class, 'store'])->name('grupos.store');
            Route::put('grupos/{grupo}', [GrupoController::class, 'update'])->name('grupos.update');
            Route::delete('grupos/{grupo}', [GrupoController::class, 'destroy'])->name('grupos.destroy');
            Route::post('disponibilidade', DisponibilidadeController::class)->name('disponibilidade');
        });
    });

    Route::prefix('assistente')->name('assistente.')->middleware(GarantirAssistenteConfigurado::class)->group(function () {
        Route::post('acoes/{acao}/confirmar', [AcaoPropostaController::class, 'confirmar'])->name('acoes.confirmar');
        Route::post('acoes/{acao}/descartar', [AcaoPropostaController::class, 'descartar'])->name('acoes.descartar');
        Route::get('sugestoes', SugestoesController::class)->name('sugestoes');
        Route::get('conversas', [ConversaController::class, 'index'])->name('conversas.index');
        Route::post('conversas', [ConversaController::class, 'store'])->name('conversas.store');
        Route::get('conversas/{conversa}', [ConversaController::class, 'show'])->name('conversas.show');
        Route::delete('conversas/{conversa}', [ConversaController::class, 'destroy'])->name('conversas.destroy');
        Route::post('conversas/{conversa}/mensagens', [MensagemController::class, 'store'])
            ->middleware('throttle:assistente')
            ->name('mensagens.store');
    });

    Route::get('modelos/{modelo}/imagem', [ModeloCertificadoController::class, 'imagem'])->name('modelos.imagem');
});

Route::prefix('gestao')->name('gestao.')->middleware(['auth', 'can:gestao'])->group(function () {
    Route::get('/', VisaoGeralController::class)->name('index');
    Route::put('escopo', EscopoController::class)->name('escopo');

    Route::get('conversas', [GestaoConversaController::class, 'index'])->name('conversas.index');
    Route::patch('conversas/{conversa}/classificacao', [GestaoConversaController::class, 'corrigir'])->name('conversas.corrigir');

    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('clientes/{user}', [ClienteController::class, 'show'])->name('clientes.show');

    Route::get('temas', [TemaController::class, 'index'])->name('temas.index');
    Route::patch('temas/{tema}', [TemaController::class, 'update'])->name('temas.update');
    Route::post('temas/{tema}/juntar', [TemaController::class, 'juntar'])->name('temas.juntar');
});

require __DIR__.'/settings.php';
