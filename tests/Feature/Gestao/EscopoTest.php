<?php

use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\User;
use App\Services\Gestao\EscopoDaGestao;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

/** Conversa classificada agora, do dono indicado. */
function conversaDoEscopo(User $dono): Conversa
{
    $conversa = Conversa::factory()->for($dono)->create();

    ClassificacaoDeConversa::factory()->for($conversa)->create([
        'user_id' => $dono->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
    ]);

    return $conversa;
}

it('deixa as contas de gestão fora do painel por padrão', function () {
    $gestor = User::factory()->create(['admin' => true]);
    conversaDoEscopo($gestor);
    conversaDoEscopo(User::factory()->create(['admin' => false]));

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversas.total', 1)
            ->where('gestao.incluir_gestores', false));

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.show', $gestor))
        ->assertNotFound();
});

it('inclui as contas de gestão em todo o painel quando o gestor liga', function () {
    $gestor = User::factory()->create(['admin' => true]);
    $minha = conversaDoEscopo($gestor);
    conversaDoEscopo(User::factory()->create(['admin' => false]));

    $this->actingAs($gestor)
        ->from(route('gestao.index'))
        ->put(route('gestao.escopo'), ['incluir_gestores' => true])
        ->assertRedirect(route('gestao.index'))
        ->assertSessionHas(EscopoDaGestao::CHAVE_DA_SESSAO, true);

    $this->actingAs($gestor)
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumo.conversas', 2)
            ->where('gestao.incluir_gestores', true));

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['conversa' => $minha->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversas.total', 2)
            ->where('selecionada.id', $minha->id));

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.show', $gestor))
        ->assertOk();

    $this->actingAs($gestor)
        ->put(route('gestao.escopo'), ['incluir_gestores' => false])
        ->assertSessionHas(EscopoDaGestao::CHAVE_DA_SESSAO, false);

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('conversas.total', 1));
});

it('não deixa quem não é gestor mudar o escopo', function () {
    $this->actingAs(User::factory()->create(['admin' => false]))
        ->put(route('gestao.escopo'), ['incluir_gestores' => true])
        ->assertForbidden();
});
