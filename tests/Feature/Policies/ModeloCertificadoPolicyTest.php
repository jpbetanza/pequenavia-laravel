<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

/**
 * Cria um modelo e devolve o usuário no papel pedido em relação a ele.
 *
 * @return array{0: User, 1: ModeloCertificado}
 */
function usuarioComPapelNoModelo(string $papel): array
{
    $modelo = ModeloCertificado::factory()->create();

    if ($papel === 'dono') {
        return [$modelo->user, $modelo];
    }

    if ($papel === 'estranho') {
        return [User::factory()->create(), $modelo];
    }

    $convidado = $papel === 'convidado-sem-email-verificado'
        ? User::factory()->unverified()->create(['email' => 'Convidado@Example.com'])
        : User::factory()->create(['email' => 'Convidado@Example.com']);

    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo');

    if ($papel !== 'leitor') {
        $compartilhamento = $compartilhamento->editor();
    }

    $compartilhamento->create(['email' => 'CONVIDADO@example.com']);

    return [$convidado, $modelo];
}

dataset('permissoes por papel', [
    'dono' => ['dono', ['view', 'gerar', 'update', 'editarCampos', 'gerenciarCompartilhamentos', 'delete']],
    'editor compartilhado' => ['editor', ['view', 'gerar', 'update', 'editarCampos']],
    'leitor compartilhado' => ['leitor', ['view', 'gerar']],
    'convidado sem email verificado' => ['convidado-sem-email-verificado', []],
    'estranho' => ['estranho', []],
]);

it('concede a cada papel só as ações permitidas', function (string $papel, array $permitidas) {
    [$user, $modelo] = usuarioComPapelNoModelo($papel);

    $concedidas = collect(['view', 'gerar', 'update', 'editarCampos', 'gerenciarCompartilhamentos', 'delete'])
        ->filter(fn (string $acao) => $user->can($acao, $modelo))
        ->values()
        ->all();

    expect($concedidas)->toBe($permitidas);
})->with('permissoes por papel');

it('lista em acessiveisPor os modelos próprios e os compartilhados, nunca os de outros', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $proprio = ModeloCertificado::factory()->for($user)->create();
    $compartilhado = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->create(['email' => 'ANA@example.com']);
    ModeloCertificado::factory()->create();

    $ids = ModeloCertificado::query()->acessiveisPor($user)->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$proprio->id, $compartilhado->id])->sort()->values()->all());
});

it('ignora em acessiveisPor os compartilhamentos de quem não verificou o email', function () {
    $user = User::factory()->unverified()->create(['email' => 'ana@example.com']);
    $compartilhado = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->create(['email' => 'ana@example.com']);

    expect(ModeloCertificado::query()->acessiveisPor($user)->count())->toBe(0);
});
