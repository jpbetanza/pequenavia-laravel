<?php

use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Enums\SituacaoDoTema;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Espaco;
use App\Models\Mensagem;
use App\Models\Reserva;
use App\Models\RodadaDeClassificacao;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

function gestorDoPainel(): User
{
    return User::factory()->create(['admin' => true]);
}

/** Classificação de uma conversa do cliente, classificada agora. */
function classificacaoDaParoquia(User $cliente, array $atributos = []): ClassificacaoDeConversa
{
    $conversa = Conversa::factory()->for($cliente)->create();

    return ClassificacaoDeConversa::factory()->for($conversa)->create(array_merge([
        'user_id' => $cliente->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
    ], $atributos));
}

it('mostra os números do período com clientes, conversas e resolvidas', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    $bruno = User::factory()->create(['admin' => false, 'name' => 'Bruno', 'created_at' => now()->subDays(60)]);

    // Ana conversou e fez uma reserva no período; Bruno só existe.
    $conversa = Conversa::factory()->for($ana)->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'created_at' => now()]);
    Reserva::factory()->for($ana)->for(Espaco::factory()->for($ana))->create();

    // Uma resolvida (a correção do gestor vence o JEV) e uma não resolvida.
    classificacaoDaParoquia($ana, [
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'correcao' => ['resultado' => ResultadoDaConversa::Resolvida->value],
    ]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::NaoResolvida->value]);

    // Admin não conta como paróquia.
    classificacaoDaParoquia(gestorDoPainel());

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index', ['periodo' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/visao-geral')
            ->where('periodo', 7)
            ->where('resumo.paroquias_total', 2)
            ->where('resumo.paroquias_ativas', 1)
            ->where('resumo.paroquias_novas', 1)
            ->where('resumo.conversas', 3)
            ->where('resumo.conversas_anterior', 0)
            ->where('resumo.resolvidas_pct', 50)
            ->where('resumo.a_revisar', 0)
            ->where('resumo.custo.jev', 0)
            ->where('resumo.custo.assistente', null)
            ->has('por_dia', 7)
            ->where('clientes.0.nome', 'Ana')
            ->where('clientes.0.reservas', 1)
            ->where('clientes.0.resolvidas_pct', 50)
            ->where('clientes.1.nome', 'Bruno')
            ->where('clientes.1.reservas', 0));
});

it('separa o dia por resolvidas e não resolvidas, com parcial contando como não resolvida', function () {
    $ana = User::factory()->create(['admin' => false]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Parcial->value]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('por_dia', 30)
            ->where('por_dia.29.data', '2026-10-17')
            ->where('por_dia.29.resolvidas', 1)
            ->where('por_dia.29.nao_resolvidas', 1)
            ->where('por_dia.0.resolvidas', 0));
});

it('deixa as conversas que aguardam a secretaria fora da taxa de resolvidas e das não resolvidas', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::NaoResolvida->value]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Aguardando->value]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Aguardando->value]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumo.resolvidas_pct', 50)
            ->where('clientes.0.resolvidas_pct', 50)
            ->where('por_dia.29.resolvidas', 1)
            ->where('por_dia.29.nao_resolvidas', 1)
            ->where('por_dia.29.aguardando', 2));

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.conversas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('matriz.0.total', 4)
            ->where('matriz.0.aguardando', 2)
            ->where('matriz.0.nao_resolvida', 1));

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.clientes.show', $ana))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assistente.resolvidas_pct', 50)
            ->where('casos.0.aguardando', 2));
});

it('ordena os casos de uso pelo efetivo e calcula o percentual', function () {
    $ana = User::factory()->create(['admin' => false]);
    classificacaoDaParoquia($ana, ['caso_de_uso' => 'reserva']);
    classificacaoDaParoquia($ana, ['caso_de_uso' => 'reserva']);
    classificacaoDaParoquia($ana, ['caso_de_uso' => 'consultar_agenda', 'correcao' => ['caso_de_uso' => 'reserva']]);
    classificacaoDaParoquia($ana, ['caso_de_uso' => 'certificados']);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('casos.0.caso', 'reserva')
            ->where('casos.0.rotulo', 'Criar ou mudar reserva')
            ->where('casos.0.total', 3)
            ->where('casos.0.pct', 75)
            ->where('casos.1.caso', 'certificados')
            ->where('casos.1.total', 1));
});

it('mostra a classificação com a última rodada e a próxima às 04h, 10h e 18h', function (string $agora, string $proxima) {
    $this->travelTo(CarbonImmutable::parse($agora, 'America/Sao_Paulo'));

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classificacao.proxima', $proxima)
            ->where('classificacao.ultima', null));
})->with([
    'às 04h vai para as 05h' => ['2026-10-17 04:00', '05:00'],
    'às 10h vai para as 17h' => ['2026-10-17 10:00', '17:00'],
    'às 18h vai para as 05h do dia seguinte' => ['2026-10-17 18:00', '05:00'],
]);

it('mostra a última rodada com as contagens dela', function () {
    RodadaDeClassificacao::create([
        'iniciada_em' => now()->subHours(3),
        'terminada_em' => now()->subHours(3)->addMinutes(4),
        'conversas_lidas' => 12,
        'resumidas' => 3,
        'temas_novos' => 0,
        'falhas' => 0,
        'tokens_jev' => 900,
        'custo_jev' => '0.000038',
        'custo_haiku' => '0.001200',
    ]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classificacao.ultima.conversas_lidas', 12)
            ->where('classificacao.ultima.resumidas', 3)
            ->where('classificacao.proxima', '17:00'));
});

it('não mostra alertas quando nada está fora do lugar', function () {
    $ana = User::factory()->create(['admin' => false]);
    classificacaoDaParoquia($ana, ['resultado' => ResultadoDaConversa::Resolvida->value]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page->where('alertas', []));
});

it('alerta sobre erros do assistente quando ontem passa do dobro da média', function () {
    $ana = User::factory()->create(['admin' => false]);
    $conversa = Conversa::factory()->for($ana)->create();

    Mensagem::factory()->for($conversa)->count(4)->create([
        'papel' => PapelDaMensagem::Assistente,
        'erro' => true,
        'created_at' => CarbonImmutable::parse('2026-10-16 10:00', 'America/Sao_Paulo')->utc(),
    ]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index', ['periodo' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alertas.0.tipo', 'erros_do_assistente')
            ->where('alertas.0.nivel', 'alto')
            ->where('alertas.0.titulo', '4 respostas com erro ontem'));
});

it('não alerta sobre erros do assistente quando ontem não passa do dobro da média', function () {
    $ana = User::factory()->create(['admin' => false]);
    $conversa = Conversa::factory()->for($ana)->create();

    // Quatro erros no período, um deles ontem: a média é de 0,6 por dia e o dobro, 1,1, não é ultrapassado.
    Mensagem::factory()->for($conversa)->count(3)->create([
        'papel' => PapelDaMensagem::Assistente,
        'erro' => true,
        'created_at' => CarbonImmutable::parse('2026-10-12 10:00', 'America/Sao_Paulo')->utc(),
    ]);
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Assistente,
        'erro' => true,
        'created_at' => CarbonImmutable::parse('2026-10-16 10:00', 'America/Sao_Paulo')->utc(),
    ]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index', ['periodo' => 7]))
        ->assertInertia(fn (Assert $page) => $page->where('alertas', []));
});

it('alerta sobre cliente parado com conversa não resolvida antes de sumir', function () {
    $bruno = User::factory()->create(['admin' => false, 'name' => 'Bruno']);
    classificacaoDaParoquia($bruno, [
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'mensagens_ate' => now()->subDays(20),
        'classificada_em' => now()->subDays(19),
    ]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alertas.0.tipo', 'cliente_parado')
            ->where('alertas.0.nivel', 'alto')
            ->where('alertas.0.titulo', 'Bruno parou de usar o app')
            ->where('alertas.0.link', route('gestao.clientes.show', $bruno)));
});

it('não alerta sobre cliente parado quando a conversa sem resolver é recente', function () {
    $bruno = User::factory()->create(['admin' => false]);
    classificacaoDaParoquia($bruno, [
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'mensagens_ate' => now()->subDays(3),
    ]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page->where('alertas', []));
});

it('alerta sobre o tema mais citado, com as frustradas no texto', function () {
    $coral = TemaDePedido::factory()->create(['nome' => 'Coral', 'situacao' => SituacaoDoTema::Acompanhando]);
    $ana = User::factory()->create(['admin' => false]);

    classificacaoDaParoquia($ana, ['tema_id' => $coral->id, 'frustracao' => 2.0]);
    classificacaoDaParoquia($ana, ['tema_id' => $coral->id, 'frustracao' => 0.5]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alertas.0.tipo', 'tema_mais_citado')
            ->where('alertas.0.nivel', 'alto')
            ->where('alertas.0.titulo', 'Mais citado: Coral')
            ->where('alertas.0.texto', '2 conversas de 1 paróquia no período, 1 com frustração alta.')
            ->where('alertas.0.link', route('gestao.temas.index', ['tema' => $coral->id])));
});

it('alerta sobre tema novo só a partir de cinco conversas em sete dias', function (int $conversas, bool $alerta) {
    $tema = TemaDePedido::factory()->create(['nome' => 'Festa junina', 'situacao' => SituacaoDoTema::Novo]);
    $ana = User::factory()->create(['admin' => false]);

    for ($i = 0; $i < $conversas; $i++) {
        classificacaoDaParoquia($ana, ['tema_id' => $tema->id, 'classificada_em' => now()->subDays(2)]);
    }

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('alertas', fn ($alertas) => $alerta
                ? $alertas->contains(fn ($alerta) => $alerta['tipo'] === 'tema_novo'
                    && $alerta['titulo'] === 'Pedido novo: Festa junina'
                    && $alerta['link'] === route('gestao.temas.index', ['tema' => $tema->id]))
                : $alertas->doesntContain(fn ($alerta) => $alerta['tipo'] === 'tema_novo')));
})->with([
    'quatro conversas: sem alerta' => [4, false],
    'cinco conversas: alerta' => [5, true],
]);

it('alerta sobre a fila de revisão aberta', function () {
    $ana = User::factory()->create(['admin' => false]);
    classificacaoDaParoquia($ana, ['a_revisar' => true]);

    $this->actingAs(gestorDoPainel())
        ->get(route('gestao.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('resumo.a_revisar', 1)
            ->where('alertas.0.tipo', 'a_revisar')
            ->where('alertas.0.titulo', '1 conversa para revisar'));
});
