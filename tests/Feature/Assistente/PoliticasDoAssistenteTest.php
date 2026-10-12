<?php

use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('quem não é dono da conversa recebe 404 em enviar e excluir', function (string $habilidade) {
    $conversa = Conversa::factory()->create();

    $resposta = Gate::forUser(User::factory()->create())->inspect($habilidade, $conversa);

    expect($resposta->denied())->toBeTrue()
        ->and($resposta->status())->toBe(404);
})->with(['enviar', 'delete']);

test('quem não é dono da ação recebe 404 em descartar', function () {
    $acao = AcaoProposta::factory()->create();

    $resposta = Gate::forUser(User::factory()->create())->inspect('descartar', $acao);

    expect($resposta->denied())->toBeTrue()
        ->and($resposta->status())->toBe(404);
});
