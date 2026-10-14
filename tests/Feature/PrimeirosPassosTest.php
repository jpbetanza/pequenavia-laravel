<?php

use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\InscricaoPush;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Assistente\MontadorDeContexto;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00', 'America/Sao_Paulo'));
    config(['push.chave_publica' => 'BPublica', 'push.chave_privada' => 'privada']);
    $this->usuario = User::factory()->create();
});

it('quem chega sem nada vê o roteiro do começo, e o Início grava o começo', function () {
    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.fase', 'inicio')
            ->where('primeiros_passos.feitos', 0)
            ->where('primeiros_passos.total', 5)
            ->where('primeiros_passos.atual', 'espacos')
            ->where('primeiros_passos.adiado', false)
            ->where('primeiros_passos.passos.0.chave', 'espacos')
            ->where('primeiros_passos.passos.4.chave', 'avisos')
            ->where('primeiros_passos.link_do_formulario', route('reservar.create', $this->usuario->fresh()->agendaSlug()))
            ->missing('auth.user.primeiros_passos'));

    expect($this->usuario->fresh()->primeiros_passos)->toHaveKey('iniciado_em');
});

it('os passos ficam feitos pelos dados: cadastros, uma solicitação e um aparelho com avisos', function () {
    Espaco::factory()->for($this->usuario)->count(3)->create();
    Equipamento::factory()->for($this->usuario)->create();

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.fase', 'andamento')
            ->where('primeiros_passos.feitos', 2)
            ->where('primeiros_passos.atual', 'grupos')
            ->where('primeiros_passos.contagens', ['espacos' => 3, 'equipamentos' => 1, 'grupos' => 0]));

    Grupo::factory()->for($this->usuario)->create();
    Solicitacao::factory()->for($this->usuario)->create();
    InscricaoPush::factory()->for($this->usuario)->create();

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.fase', 'concluido')
            ->where('primeiros_passos.feitos', 5)
            ->where('primeiros_passos.atual', null));
});

it('quem já chegou com tudo pronto não vê boas-vindas', function () {
    Espaco::factory()->for($this->usuario)->create();
    Equipamento::factory()->for($this->usuario)->create();
    Grupo::factory()->for($this->usuario)->create();
    Solicitacao::factory()->for($this->usuario)->create();
    InscricaoPush::factory()->for($this->usuario)->create();

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('primeiros_passos', null));
});

it('sem as chaves VAPID, o passo dos avisos sai do roteiro', function () {
    config(['push.chave_privada' => null]);

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.total', 4)
            ->has('primeiros_passos.passos', 4));

    $this->actingAs($this->usuario)
        ->post(route('primeiros-passos.update'), ['acao' => 'pular', 'passo' => 'avisos'])
        ->assertSessionHasErrors('passo');
});

it('pular conta como resolvido e o próximo passo vira o atual', function () {
    $this->actingAs($this->usuario)
        ->from(route('inicio'))
        ->post(route('primeiros-passos.update'), ['acao' => 'pular', 'passo' => 'espacos'])
        ->assertRedirect(route('inicio'));

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.fase', 'andamento')
            ->where('primeiros_passos.feitos', 1)
            ->where('primeiros_passos.passos.0.pulado', true)
            ->where('primeiros_passos.atual', 'equipamentos'));
});

it('"já enviei o link" resolve o passo do formulário', function () {
    $this->actingAs($this->usuario)->post(route('primeiros-passos.update'), ['acao' => 'link_enviado']);

    $this->actingAs($this->usuario)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.passos.3.chave', 'formulario')
            ->where('primeiros_passos.passos.3.feito', true));
});

it('adiar esconde até amanhã; dispensar esconde de vez', function () {
    $this->actingAs($this->usuario)->post(route('primeiros-passos.update'), ['acao' => 'adiar']);

    $this->actingAs($this->usuario)->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('primeiros_passos.adiado', true));

    $this->travel(25)->hours();

    $this->actingAs($this->usuario)->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('primeiros_passos.adiado', false));

    $this->actingAs($this->usuario)->post(route('primeiros-passos.update'), ['acao' => 'dispensar']);

    $this->actingAs($this->usuario)->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('primeiros_passos', null));
});

it('recusa ação desconhecida, pular sem passo e visitante', function () {
    $this->actingAs($this->usuario)
        ->post(route('primeiros-passos.update'), ['acao' => 'apagar'])
        ->assertSessionHasErrors('acao');
    $this->actingAs($this->usuario)
        ->post(route('primeiros-passos.update'), ['acao' => 'pular'])
        ->assertSessionHasErrors('passo');

    auth()->logout();
    $this->post(route('primeiros-passos.update'), ['acao' => 'adiar'])->assertRedirect(route('login'));
});

it('as outras páginas só leem o roteiro: o começo e o endereço do formulário saem do Início', function () {
    $this->actingAs($this->usuario)
        ->get(route('agendamentos.espacos.index'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('primeiros_passos.fase', 'inicio')
            ->where('primeiros_passos.link_do_formulario', null));

    expect($this->usuario->fresh())->primeiros_passos->toBeNull()->agenda_slug->toBeNull();
});

it('o assistente sabe do roteiro enquanto ele não termina', function () {
    Espaco::factory()->for($this->usuario)->create();
    $conversa = Conversa::factory()->for($this->usuario)->create();

    $instrucoes = app(MontadorDeContexto::class)->montar($this->usuario, $conversa)[0]['content'];

    expect($instrucoes)
        ->toContain('Primeiros passos (a secretaria está começando a usar a agenda; 1 de 5 resolvidos)')
        ->toContain('- Espaços: feito')
        ->toContain('- Equipamentos: pendente')
        ->toContain('conduza um passo por vez')
        ->toContain(route('reservar.create', $this->usuario->fresh()->agendaSlug()));

    $this->usuario->forceFill(['primeiros_passos' => ['dispensado_em' => now()->toIso8601String()]])->save();
    $instrucoes = app(MontadorDeContexto::class)->montar($this->usuario->fresh(), $conversa)[0]['content'];

    expect($instrucoes)->not->toContain('Primeiros passos (');
});
