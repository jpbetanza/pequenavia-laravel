<?php

use App\Enums\SituacaoDoTema;
use App\Models\RodadaDeClassificacao;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Dados da classificação de hoje, 15/10/2026 15h30 em São Paulo: a próxima rodada é às 17h.
    $this->travelTo(CarbonImmutable::parse('2026-10-15 15:30', 'America/Sao_Paulo'));
});

it('não manda dados de gestão para quem não é gestor', function () {
    $cliente = User::factory()->create(['admin' => false]);

    TemaDePedido::factory()->create(['situacao' => SituacaoDoTema::Novo]);

    $this->actingAs($cliente)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('gestao', null));
});

it('manda ao gestor o número de temas novos e a classificação', function () {
    $gestor = User::factory()->create(['admin' => true]);

    // Conta só temas novos e ativos: acompanhando, descartado e juntado ficam de fora.
    TemaDePedido::factory()->count(2)->create(['situacao' => SituacaoDoTema::Novo]);
    TemaDePedido::factory()->create(['situacao' => SituacaoDoTema::Acompanhando]);
    TemaDePedido::factory()->create(['situacao' => SituacaoDoTema::Descartado]);
    TemaDePedido::factory()->create([
        'situacao' => SituacaoDoTema::Novo,
        'juntado_em_id' => TemaDePedido::factory()->create(['situacao' => SituacaoDoTema::Planejado])->id,
    ]);

    $this->actingAs($gestor)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('gestao.temas_novos', 2)
            ->where('gestao.classificacao.ultima', null)
            ->where('gestao.classificacao.proxima', '17:00'));
});

it('mostra a última rodada de classificação ao gestor', function () {
    $gestor = User::factory()->create(['admin' => true]);

    RodadaDeClassificacao::create([
        'iniciada_em' => CarbonImmutable::parse('2026-10-15 05:00', 'America/Sao_Paulo')->utc(),
        'terminada_em' => CarbonImmutable::parse('2026-10-15 05:04', 'America/Sao_Paulo')->utc(),
        'conversas_lidas' => 46,
        'resumidas' => 7,
        'temas_novos' => 0,
        'falhas' => 0,
        'tokens_jev' => 900,
        'custo_jev' => '0.000038',
        'custo_haiku' => '0.001200',
    ]);

    $this->actingAs($gestor)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->where('gestao.classificacao.ultima.conversas_lidas', 46)
            ->where('gestao.classificacao.ultima.resumidas', 7)
            ->where('gestao.classificacao.ultima.iniciada_em', '2026-10-15T05:00:00-03:00'));
});
