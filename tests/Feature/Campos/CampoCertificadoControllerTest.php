<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

/**
 * Cria um modelo e o convidado com quem ele é compartilhado.
 *
 * @return array{0: ModeloCertificado, 1: User}
 */
function modeloCompartilhadoCom(bool $podeEditar): array
{
    $modelo = ModeloCertificado::factory()->create();
    $convidado = User::factory()->create(['email' => 'convidado@example.com']);
    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo');

    if ($podeEditar) {
        $compartilhamento = $compartilhamento->editor();
    }

    $compartilhamento->create(['email' => 'convidado@example.com']);

    return [$modelo, $convidado];
}

it('entrega ao editor o modelo, os campos em ordem, a permissão e o catálogo de fontes', function () {
    $modelo = ModeloCertificado::factory()->create(['title' => 'Batismo']);
    [$primeiro, $segundo] = CampoCertificado::factory()
        ->for($modelo, 'modelo')
        ->count(2)
        ->sequence(
            ['nome' => 'Nome', 'created_at' => now()->subMinute()],
            ['nome' => 'Data', 'created_at' => now()],
        )
        ->create();

    $this->actingAs($modelo->user)->get(route('meus-certificados.campos', $modelo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/campos')
            ->where('modelo.id', $modelo->id)
            ->where('modelo.title', 'Batismo')
            ->where('modelo.imagem_url', route('modelos.imagem', $modelo))
            ->has('campos', 2)
            ->where('campos.0.id', $primeiro->id)
            ->where('campos.0.nome', 'Nome')
            ->where('campos.1.id', $segundo->id)
            ->where('pode_editar', true)
            ->where('fontes.Sistema.Georgia', 'gelasio')
            ->has('fontes.Serif')
            ->has('fontes.Sem serifa')
            ->has('fontes.Display'));
});

it('mostra a página ao leitor compartilhado sem permissão de edição', function () {
    [$modelo, $leitor] = modeloCompartilhadoCom(podeEditar: false);

    $this->actingAs($leitor)->get(route('meus-certificados.campos', $modelo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pode_editar', false));
});

it('responde 404 na página de campos a quem não pode ver o modelo', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('meus-certificados.campos', $modelo))
        ->assertNotFound();
});

it('dá ao campo novo o primeiro nome livre e o estilo enviado', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Campo 1']);
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Campo 3']);

    $this->actingAs($modelo->user)
        ->post(route('campos.store', $modelo), [
            'fonte_familia' => 'Cinzel',
            'cor' => '#1a1a1a',
            'x' => 50,
            'y' => 80,
        ])
        ->assertRedirect();

    expect($modelo->campos()->where('nome', 'Campo 2')->sole())
        ->fonte_familia->toBe('Cinzel')
        ->cor->toBe('#1a1a1a')
        ->x->toBe(50)
        ->y->toBe(80);
});

it('cria o campo com os valores padrão quando nada é enviado', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)->post(route('campos.store', $modelo))->assertRedirect();

    expect($modelo->campos()->sole())
        ->nome->toBe('Campo 1')
        ->label->toBeNull()
        ->fonte_familia->toBe('Georgia')
        ->peso_fonte->toBe(600)
        ->centralizado->toBeFalse();
});

it('ignora o modelo_id enviado e cria o campo no modelo da rota', function () {
    $modelo = ModeloCertificado::factory()->create();
    $deOutro = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('campos.store', $modelo), ['modelo_id' => $deOutro->id])
        ->assertRedirect();

    expect($modelo->campos()->count())->toBe(1)
        ->and($deOutro->campos()->count())->toBe(0);
});

it('deixa o editor compartilhado atualizar só os campos enviados', function () {
    [$modelo, $editor] = modeloCompartilhadoCom(podeEditar: true);
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['x' => 10, 'y' => 100]);

    $this->actingAs($editor)
        ->patch(route('campos.update', [$modelo, $campo]), [
            'x' => 320,
            'centralizado' => true,
            'espacamento_letras' => 1.5,
        ])
        ->assertRedirect();

    expect($campo->fresh())
        ->x->toBe(320)
        ->centralizado->toBeTrue()
        ->espacamento_letras->toBe(1.5)
        ->y->toBe(100);
});

it('recusa renomear um campo para um nome já usado no modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);
    $outro = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Data']);

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $outro]), ['nome' => 'Nome'])
        ->assertSessionHasErrors(['nome' => 'Já existe um campo com este nome neste modelo.']);

    expect($outro->fresh()->nome)->toBe('Data');
});

it('aceita salvar o campo com o próprio nome', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $campo]), ['nome' => 'Nome', 'x' => 5])
        ->assertSessionHasNoErrors();
});

it('recusa valores inválidos de campo', function (array $dados, string $campo, string $mensagem) {
    $modelo = ModeloCertificado::factory()->create();
    $existente = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $existente]), $dados)
        ->assertSessionHasErrors([$campo => $mensagem]);
})->with([
    'fonte fora do catálogo' => [['fonte_familia' => 'Comic Sans MS'], 'fonte_familia', 'Escolha uma fonte da lista.'],
    'cor que não é hexadecimal' => [['cor' => 'red'], 'cor', 'Informe a cor no formato #RRGGBB.'],
    'peso fora da escala' => [['peso_fonte' => 650], 'peso_fonte', 'Escolha um peso de fonte da lista.'],
    'posição x negativa' => [['x' => -1], 'x', 'A posição não pode ser negativa.'],
    'posição y negativa' => [['y' => -1], 'y', 'A posição não pode ser negativa.'],
]);

it('responde 403 ao leitor compartilhado que tenta alterar um campo', function () {
    [$modelo, $leitor] = modeloCompartilhadoCom(podeEditar: false);
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['x' => 10]);

    $this->actingAs($leitor)
        ->patch(route('campos.update', [$modelo, $campo]), ['x' => 999])
        ->assertForbidden();

    expect($campo->fresh()->x)->toBe(10);
});

it('responde 404 quando o campo pertence a outro modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campoDeOutroModelo = CampoCertificado::factory()->create(['x' => 10]);

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $campoDeOutroModelo]), ['x' => 999])
        ->assertNotFound();

    expect($campoDeOutroModelo->fresh()->x)->toBe(10);
});

it('exclui o campo do modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)->delete(route('campos.destroy', [$modelo, $campo]))->assertRedirect();

    $this->assertModelMissing($campo);
});
