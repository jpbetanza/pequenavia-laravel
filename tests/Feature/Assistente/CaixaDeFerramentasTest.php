<?php

use App\Models\Conversa;
use App\Models\User;
use App\Services\Assistente\CaixaDeFerramentas;
use App\Services\Assistente\ChamadaDeFerramenta;

test('definições seguem o formato de tools do OpenAI', function () {
    $definicoes = app(CaixaDeFerramentas::class)->definicoes();

    expect(collect($definicoes)->pluck('function.name')->all())
        ->toContain('consultar_agenda', 'verificar_disponibilidade', 'listar_solicitacoes', 'listar_cadastros', 'resumo_de_certificados')
        ->and($definicoes[0]['type'])->toBe('function')
        ->and($definicoes[0]['function']['parameters']['type'])->toBe('object');
});

test('JSON quebrado, ferramenta desconhecida e argumento inválido viram erro para o modelo', function (string $nome, string $argumentos, string $trecho) {
    $usuario = User::factory()->create();
    $resultado = app(CaixaDeFerramentas::class)->executar($usuario, Conversa::factory()->for($usuario)->create(), new ChamadaDeFerramenta('c1', $nome, $argumentos));

    expect($resultado->paraOModelo['erro'])->toContain($trecho)->and($resultado->cartao)->toBeNull();
})->with([
    ['consultar_agenda', '{"inicio":', 'Argumentos inválidos'],
    ['apagar_tudo', '{}', 'Ferramenta desconhecida'],
    ['consultar_agenda', '{"inicio":"ontem","fim":"hoje"}', 'formato'],
]);
