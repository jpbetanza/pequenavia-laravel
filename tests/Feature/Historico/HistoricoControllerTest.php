<?php

use App\Models\CampoCertificado;
use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use App\Models\User;

it('redireciona o visitante para o login', function () {
    $this->get(route('historico.index'))->assertRedirect(route('login'));
});

it('mostra as 50 gerações mais recentes do próprio usuário, da mais nova para a mais antiga', function () {
    $user = User::factory()->create();
    $geracoes = GeracaoCertificado::factory()
        ->for($user)
        ->count(55)
        ->sequence(fn ($sequence) => ['created_at' => now()->subMinutes(55 - $sequence->index)])
        ->create();
    GeracaoCertificado::factory()->create();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('historico/index')
            ->has('geracoes', 50)
            ->where('geracoes.0.id', $geracoes->last()->id)
            ->where('geracoes.49.id', $geracoes[5]->id));
});

it('entrega de cada geração o modelo, os valores e a data', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->create(['title' => 'Crisma 2024']);
    CampoCertificado::factory()->for($modelo, 'modelo')->count(2)->sequence(
        ['nome' => 'Nome', 'created_at' => now()->subMinute()],
        ['nome' => 'Data', 'created_at' => now()],
    )->create();
    $geracao = GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create([
        'modelo_title' => 'Crisma 2024',
        'campos_valores' => ['Nome' => 'Maria'],
    ]);

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->has('geracoes.0', fn ($item) => $item
                ->where('id', $geracao->id)
                ->where('modelo_id', $modelo->id)
                ->where('modelo_title', 'Crisma 2024')
                ->where('campos_valores', ['Nome' => 'Maria'])
                ->where('ordem_campos', ['Nome', 'Data'])
                ->where('gerado_por', $user->name)
                ->has('created_at')));
});

it('devolve a ordem dos campos vazia quando o modelo da geração foi excluído', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->create();
    GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create(['campos_valores' => ['Nome' => 'Maria']]);
    $modelo->delete();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->where('geracoes.0.modelo_id', null)
            ->where('geracoes.0.ordem_campos', []));
});

it('entrega os filtros aplicados e o nome de quem gerou cada certificado', function () {
    $user = User::factory()->create(['name' => 'Ana Souza']);
    GeracaoCertificado::factory()->for($user)->create();

    $this->actingAs($user)->get(route('historico.index', ['busca' => 'Maria', 'modelo' => null]))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.busca', 'Maria')
            ->where('filtros.modelo', null)
            ->where('geracoes.0.gerado_por', 'Ana Souza')
            ->etc());
});

it('acha as gerações pelo valor de qualquer campo, sem diferenciar maiúsculas de minúsculas', function () {
    $user = User::factory()->create();
    $maria = GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nome' => 'Maria Clara', 'Data' => '2024']]);
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nome' => 'João']]);

    $this->actingAs($user)->get(route('historico.index', ['busca' => 'MARIA']))
        ->assertInertia(fn ($page) => $page
            ->has('geracoes', 1)
            ->where('geracoes.0.id', $maria->id)
            ->etc());
});

it('não mostra na busca as gerações de outro usuário com o mesmo valor', function () {
    $user = User::factory()->create();
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nome' => 'Maria']]);
    GeracaoCertificado::factory()->create(['campos_valores' => ['Nome' => 'Maria']]);

    $this->actingAs($user)->get(route('historico.index', ['busca' => 'Maria']))
        ->assertInertia(fn ($page) => $page->has('geracoes', 1)->etc());
});

it('trata o % e o _ da busca como texto, não como curinga', function () {
    $user = User::factory()->create();
    $literal = GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nota' => '100% presente']]);
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nota' => '100 presente']]);
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nota' => 'a_b']]);
    $sublinhado = GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nota' => 'a_b mais']]);
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nota' => 'axb']]);

    $this->actingAs($user)->get(route('historico.index', ['busca' => '100%']))
        ->assertInertia(fn ($page) => $page
            ->has('geracoes', 1)
            ->where('geracoes.0.id', $literal->id)
            ->etc());

    $this->actingAs($user)->get(route('historico.index', ['busca' => 'a_b']))
        ->assertInertia(fn ($page) => $page
            ->has('geracoes', 2)
            ->where('geracoes.0.id', $sublinhado->id)
            ->etc());
});

it('trata a barra invertida da busca como texto', function () {
    $user = User::factory()->create();
    $comBarra = GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nome' => 'Maria\\Clara']]);
    GeracaoCertificado::factory()->for($user)->create(['campos_valores' => ['Nome' => 'Maria Clara']]);

    $this->actingAs($user)->get(route('historico.index', ['busca' => 'a\\C']))
        ->assertInertia(fn ($page) => $page->has('geracoes', 1)->where('geracoes.0.id', $comBarra->id)->etc());
});

it('filtra as gerações pelo modelo e lista só os modelos do histórico do usuário, por título', function () {
    $user = User::factory()->create();
    $batismo = ModeloCertificado::factory()->create(['title' => 'Batismo']);
    $crisma = ModeloCertificado::factory()->create(['title' => 'Crisma']);
    ModeloCertificado::factory()->create(['title' => 'Outro do sistema']);
    $geracaoDeBatismo = GeracaoCertificado::factory()->for($user)->for($batismo, 'modelo')->create();
    GeracaoCertificado::factory()->for($user)->for($crisma, 'modelo')->create();
    GeracaoCertificado::factory()->for($user)->for($crisma, 'modelo')->create();
    GeracaoCertificado::factory()->for($user)->create(['modelo_id' => null]);

    $this->actingAs($user)->get(route('historico.index', ['modelo' => $batismo->id]))
        ->assertInertia(fn ($page) => $page
            ->where('filtros.modelo', $batismo->id)
            ->has('geracoes', 1)
            ->where('geracoes.0.id', $geracaoDeBatismo->id)
            ->where('modelos', [
                ['id' => $batismo->id, 'title' => 'Batismo'],
                ['id' => $crisma->id, 'title' => 'Crisma'],
            ]));
});

it('ignora o filtro de modelo que não é um uuid', function () {
    $user = User::factory()->create();
    GeracaoCertificado::factory()->for($user)->create();

    $this->actingAs($user)->get(route('historico.index', ['modelo' => 'nao-e-uuid']))
        ->assertStatus(302)
        ->assertSessionHasErrors('modelo');
});

it('recusa uma busca com mais de 100 caracteres', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('historico.index', ['busca' => str_repeat('a', 101)]))
        ->assertSessionHasErrors('busca');
});
