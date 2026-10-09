<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

it('compartilha o modelo por email, em minúsculas e só para leitura por padrão', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('compartilhamentos.store', $modelo), ['email' => '  Joao@Example.COM '])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Modelo compartilhado.']);

    expect($modelo->compartilhamentos()->sole())
        ->email->toBe('joao@example.com')
        ->pode_editar->toBeFalse();
});

it('compartilha com permissão de edição quando o dono pede', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('compartilhamentos.store', $modelo), ['email' => 'joao@example.com', 'pode_editar' => true])
        ->assertSessionHasNoErrors();

    expect($modelo->compartilhamentos()->sole()->pode_editar)->toBeTrue();
});

it('compartilha com quem ainda não tem conta', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('compartilhamentos.store', $modelo), ['email' => 'sem-conta@example.com'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('compartilhamentos_modelo', 1);
});

it('recusa o compartilhamento inválido', function (string $email, string $mensagem) {
    $dono = User::factory()->create(['email' => 'dono@example.com']);
    $modelo = ModeloCertificado::factory()->for($dono)->create();
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'joao@example.com']);

    $this->actingAs($dono)
        ->post(route('compartilhamentos.store', $modelo), ['email' => $email])
        ->assertSessionHasErrors(['email' => $mensagem]);

    $this->assertDatabaseCount('compartilhamentos_modelo', 1);
})->with([
    'email já compartilhado, ignorando maiúsculas' => ['JOAO@example.com', 'Este modelo já está compartilhado com este e-mail.'],
    'email do próprio dono' => ['Dono@Example.com', 'Você não precisa compartilhar um modelo com você mesmo.'],
    'email malformado' => ['nao-e-email', 'Informe um e-mail válido.'],
    'email vazio' => ['', 'Informe o e-mail.'],
]);

it('permite compartilhar o mesmo email em outro modelo', function () {
    $dono = User::factory()->create();
    $primeiro = ModeloCertificado::factory()->for($dono)->create();
    $segundo = ModeloCertificado::factory()->for($dono)->create();
    CompartilhamentoModelo::factory()->for($primeiro, 'modelo')->create(['email' => 'joao@example.com']);

    $this->actingAs($dono)
        ->post(route('compartilhamentos.store', $segundo), ['email' => 'joao@example.com'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('compartilhamentos_modelo', 2);
});

it('deixa o dono alternar a permissão de edição', function () {
    $modelo = ModeloCertificado::factory()->create();
    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patch(route('compartilhamentos.update', [$modelo, $compartilhamento]), ['pode_editar' => true])
        ->assertRedirect();

    expect($compartilhamento->fresh()->pode_editar)->toBeTrue();
});

it('exige informar se o convidado pode editar', function () {
    $modelo = ModeloCertificado::factory()->create();
    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patch(route('compartilhamentos.update', [$modelo, $compartilhamento]))
        ->assertSessionHasErrors('pode_editar');
});

it('remove o compartilhamento', function () {
    $modelo = ModeloCertificado::factory()->create();
    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->delete(route('compartilhamentos.destroy', [$modelo, $compartilhamento]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Compartilhamento removido.']);

    $this->assertModelMissing($compartilhamento);
});

it('responde 403 ao editor compartilhado que tenta gerenciar compartilhamentos', function () {
    $modelo = ModeloCertificado::factory()->create();
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);

    $this->actingAs($editor)
        ->post(route('compartilhamentos.store', $modelo), ['email' => 'novo@example.com'])
        ->assertForbidden();

    $this->assertDatabaseCount('compartilhamentos_modelo', 1);
});

it('responde 404 a quem não pode ver o modelo', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('compartilhamentos.store', $modelo), ['email' => 'novo@example.com'])
        ->assertNotFound();

    $this->assertDatabaseCount('compartilhamentos_modelo', 0);
});

it('responde 404 quando o compartilhamento pertence a outro modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    $deOutroModelo = CompartilhamentoModelo::factory()->create();

    $this->actingAs($modelo->user)
        ->delete(route('compartilhamentos.destroy', [$modelo, $deOutroModelo]))
        ->assertNotFound();

    $this->assertModelExists($deOutroModelo);
});
