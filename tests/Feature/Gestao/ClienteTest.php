<?php

use App\Enums\CasoDeUso;
use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Enums\StatusDaAcao;
use App\Enums\StatusSolicitacao;
use App\Models\AcaoProposta;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Mensagem;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // As páginas React ainda não existem nesta parte; o teste confere só o componente e as props.
    config(['inertia.testing.ensure_pages_exist' => false]);

    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

/** Classificação de uma conversa do cliente, classificada agora, com os valores padrão da factory. */
function classificacaoDoCliente(User $cliente, array $atributos = []): ClassificacaoDeConversa
{
    $conversa = Conversa::factory()->for($cliente)->create(['titulo' => $atributos['titulo'] ?? 'Conversa']);
    unset($atributos['titulo']);

    return ClassificacaoDeConversa::factory()->for($conversa)->create(array_merge([
        'user_id' => $cliente->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
    ], $atributos));
}

it('lista os clientes com busca por nome ou e-mail e deixa de fora os gestores', function () {
    $maria = User::factory()->create(['admin' => false, 'name' => 'Maria Souza', 'email' => 'maria@paroquia.test']);
    User::factory()->create(['admin' => false, 'name' => 'João Lima', 'email' => 'joao@paroquia.test']);
    User::factory()->create(['admin' => true, 'name' => 'Gestora Lima', 'email' => 'gestora@paroquia.test']);
    Espaco::factory()->for($maria)->create();

    $gestor = User::factory()->create(['admin' => true]);

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/clientes')
            ->where('periodo', 30)
            ->where('busca', null)
            ->has('clientes', 2));

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.index', ['busca' => 'lima']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('busca', 'lima')
            ->has('clientes', 1)
            ->where('clientes.0.nome', 'João Lima'));

    $this->actingAs($gestor)
        ->get(route('gestao.clientes.index', ['busca' => 'MARIA@paroquia', 'periodo' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('periodo', 7)
            ->has('clientes', 1)
            ->where('clientes.0.nome', 'Maria Souza')
            ->where('clientes.0.email', 'maria@paroquia.test')
            ->where('clientes.0.configuracao.feitos', 1)
            ->where('clientes.0.configuracao.total', fn (int $total) => $total > 0)
            ->where('clientes.0.ultimo_acesso', null));
});

it('ordena a lista pela atividade do período', function () {
    $quieta = User::factory()->create(['admin' => false, 'name' => 'Quieta']);
    $ativa = User::factory()->create(['admin' => false, 'name' => 'Ativa']);

    Reserva::factory()->for($ativa)->for(Espaco::factory()->for($ativa))->count(2)->create();

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('clientes.0.nome', 'Ativa')
            ->where('clientes.0.reservas', 2)
            ->where('clientes.1.nome', 'Quieta'));
});

it('mostra o detalhe do cliente com reservas pelo assistente, pelo formulário e à mão', function () {
    $cliente = User::factory()->create(['admin' => false, 'name' => 'Paróquia Santo Antônio']);
    $espaco = Espaco::factory()->for($cliente)->create(['nome' => 'Salão']);
    $conversa = Conversa::factory()->for($cliente)->create();

    // Reserva confirmada pelo assistente: a ação aponta para ela.
    $peloAssistente = Reserva::factory()->for($cliente)->for($espaco)->create();
    AcaoProposta::factory()->for($conversa)->create([
        'user_id' => $cliente->id,
        'status' => StatusDaAcao::Confirmada,
        'resultado_id' => $peloAssistente->id,
        'confirmada_em' => now(),
    ]);

    // Reserva vinda do formulário público.
    $solicitacao = Solicitacao::factory()->for($cliente)->create(['status' => StatusSolicitacao::Aprovada]);
    Reserva::factory()->for($cliente)->for($espaco)->create(['solicitacao_id' => $solicitacao->id]);

    // Reserva feita à mão, na agenda, e uma reserva antiga que não entra no período.
    Reserva::factory()->for($cliente)->for($espaco)->create();
    Reserva::factory()->for($cliente)->for($espaco)->create(['created_at' => now()->subDays(40)]);

    // Proposta nunca confirmada não conta como reserva do assistente.
    AcaoProposta::factory()->for($conversa)->create(['user_id' => $cliente->id, 'status' => StatusDaAcao::Proposta]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/cliente')
            ->where('periodo', 30)
            ->where('cliente.id', $cliente->id)
            ->where('cliente.nome', 'Paróquia Santo Antônio')
            ->where('cliente.desde', '2026-10-17')
            // A reserva de quarenta dias atrás fica fora do período de 30 dias.
            ->where('reservas.total', 3)
            ->where('reservas.pelo_assistente', 1)
            ->where('reservas.pelo_formulario', 1)
            ->where('cadastros.espacos', 1)
            ->where('cadastros.equipamentos', 0)
            ->where('cadastros.grupos', 0)
            ->where('assistente.conversas', 1)
            ->where('assistente.custo', null)
            ->has('conversas', 0)
            ->has('linha_do_tempo')
            ->has('configuracao'));
});

it('marca o cliente como parado quando não acessa há duas semanas ou mais', function () {
    $cliente = User::factory()->create(['admin' => false]);
    $conversa = Conversa::factory()->for($cliente)->create();
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Usuario,
        'created_at' => now()->subDays(20),
    ]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('situacao.nivel', 'parado')
            ->where('situacao.texto', 'Sem acesso há 20 dias.')
            ->where('cliente.ultimo_acesso', '2026-09-27'));
});

it('marca o cliente em atenção quando mais de 25% das conversas do período não foram resolvidas', function () {
    $cliente = User::factory()->create(['admin' => false]);
    Mensagem::factory()->for(Conversa::factory()->for($cliente))->create(['papel' => PapelDaMensagem::Usuario]);

    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::NaoResolvida->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::Parcial->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::NaoResolvida->value]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('situacao.nivel', 'atencao')
            ->where('situacao.texto', '75% das conversas do período não foram resolvidas.'));
});

it('marca o cliente como bem quando acessa e as conversas são resolvidas', function () {
    $cliente = User::factory()->create(['admin' => false]);
    Mensagem::factory()->for(Conversa::factory()->for($cliente))->create(['papel' => PapelDaMensagem::Usuario]);

    // Um quarto não resolvido fica em 25%, o limite exato: não é atenção.
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDoCliente($cliente, ['resultado' => ResultadoDaConversa::NaoResolvida->value]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page->where('situacao.nivel', 'bem'));
});

it('mostra a configuração do roteiro e os cadastros do cliente', function () {
    $cliente = User::factory()->create(['admin' => false]);
    Espaco::factory()->for($cliente)->count(2)->create();
    Equipamento::factory()->for($cliente)->create();

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('configuracao.feitos', 2)
            ->where('cadastros.espacos', 2)
            ->where('cadastros.equipamentos', 1)
            ->where('cadastros.grupos', 0));
});

it('mostra a trava: o tema com mais conversas sem resolver', function () {
    $coral = TemaDePedido::factory()->create(['nome' => 'Coral']);
    $horarios = TemaDePedido::factory()->create(['nome' => 'Horários']);
    $cliente = User::factory()->create(['admin' => false]);

    classificacaoDoCliente($cliente, ['tema_id' => $coral->id, 'resultado' => ResultadoDaConversa::NaoResolvida->value]);
    classificacaoDoCliente($cliente, ['tema_id' => $coral->id, 'resultado' => ResultadoDaConversa::Parcial->value]);
    classificacaoDoCliente($cliente, ['tema_id' => $horarios->id, 'resultado' => ResultadoDaConversa::NaoResolvida->value]);
    classificacaoDoCliente($cliente, ['tema_id' => $horarios->id, 'resultado' => ResultadoDaConversa::Resolvida->value]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trava.tema', 'Coral')
            ->where('trava.conversas', 2));
});

it('usa o tema corrigido na trava, e correção sem tema quer dizer nenhum tema', function () {
    $coral = TemaDePedido::factory()->create(['nome' => 'Coral']);
    $horarios = TemaDePedido::factory()->create(['nome' => 'Horários']);
    $cliente = User::factory()->create(['admin' => false]);

    // Corrigida para outro tema: conta para Horários.
    classificacaoDoCliente($cliente, ['tema_id' => $coral->id, 'resultado' => ResultadoDaConversa::NaoResolvida->value, 'correcao' => ['tema_id' => $horarios->id]]);
    // Corrigida para nenhum tema: não conta para ninguém.
    classificacaoDoCliente($cliente, ['tema_id' => $coral->id, 'resultado' => ResultadoDaConversa::NaoResolvida->value, 'correcao' => ['tema_id' => null]]);
    // Correção que não toca no tema: vale o tema do JEV.
    classificacaoDoCliente($cliente, ['tema_id' => $coral->id, 'resultado' => ResultadoDaConversa::NaoResolvida->value, 'correcao' => ['caso_de_uso' => CasoDeUso::Certificados->value]]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trava.tema', 'Coral')
            ->where('trava.conversas', 1));
});

it('agrupa os casos do cliente pelo caso efetivo e conta cada resultado', function () {
    $cliente = User::factory()->create(['admin' => false]);

    classificacaoDoCliente($cliente, ['caso_de_uso' => CasoDeUso::Reserva->value, 'resultado' => ResultadoDaConversa::Resolvida->value]);
    classificacaoDoCliente($cliente, ['caso_de_uso' => CasoDeUso::Reserva->value, 'resultado' => ResultadoDaConversa::NaoResolvida->value]);
    classificacaoDoCliente($cliente, [
        'caso_de_uso' => CasoDeUso::Outro->value,
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'correcao' => ['caso_de_uso' => CasoDeUso::Certificados->value, 'resultado' => ResultadoDaConversa::Parcial->value],
    ]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('casos.0.rotulo', 'Criar ou mudar reserva')
            ->where('casos.0.total', 2)
            ->where('casos.0.resolvida', 1)
            ->where('casos.0.parcial', 0)
            ->where('casos.0.nao_resolvida', 1)
            ->where('casos.1.rotulo', 'Certificados')
            ->where('casos.1.total', 1)
            ->where('casos.1.resolvida', 0)
            ->where('casos.1.parcial', 1)
            ->where('casos.1.nao_resolvida', 0));
});

it('monta a linha do tempo com marcos, cadastros pelo assistente e conversas sem resolver', function () {
    $cliente = User::factory()->create(['admin' => false]);
    Espaco::factory()->for($cliente)->create(['nome' => 'Salão paroquial']);
    $conversa = Conversa::factory()->for($cliente)->create();
    AcaoProposta::factory()->for($conversa)->create([
        'user_id' => $cliente->id,
        'tipo' => 'criar_espaco',
        'status' => StatusDaAcao::Confirmada,
        'confirmada_em' => now(),
    ]);
    classificacaoDoCliente($cliente, [
        'titulo' => 'Reserva do coral',
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'mensagens_ate' => now()->subDays(2),
    ]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('linha_do_tempo', fn (Collection $linha) => $linha->count() <= 8
                && $linha->pluck('texto')->contains('Conta criada')
                && $linha->pluck('texto')->contains('Primeiro espaço cadastrado: Salão paroquial')
                && $linha->pluck('texto')->contains('Confirmou 1 cadastro pelo assistente')
                && $linha->pluck('texto')->contains('Conversa sem resolver: Reserva do coral')
                && $linha->firstWhere('texto', 'Conversa sem resolver: Reserva do coral')['tom'] === 'ruim'));
});

it('lista até dez conversas recentes, com o caso e o resultado efetivos', function () {
    $cliente = User::factory()->create(['admin' => false]);

    for ($i = 0; $i < 11; $i++) {
        $atributos = ['classificada_em' => now()->subMinutes($i)];

        if ($i === 0) {
            $atributos += [
                'caso_de_uso' => CasoDeUso::Reserva->value,
                'correcao' => ['caso_de_uso' => CasoDeUso::Certificados->value],
            ];
        }

        classificacaoDoCliente($cliente, $atributos);
    }

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversas', 10)
            ->where('conversas.0.caso', 'certificados')
            ->where('conversas.0.caso_rotulo', 'Certificados')
            ->where('conversas.0.resultado', 'resolvida')
            ->where('conversas.0.resultado_rotulo', ResultadoDaConversa::Resolvida->rotulo()));
});

it('mostra o cliente sem atividade como parado, com configuração ainda não iniciada', function () {
    $cliente = User::factory()->create(['admin' => false]);

    $this->actingAs(User::factory()->create(['admin' => true]))
        ->get(route('gestao.clientes.show', $cliente))
        ->assertInertia(fn (Assert $page) => $page
            ->where('situacao.nivel', 'parado')
            ->where('situacao.texto', 'Ainda não usou o app.')
            ->where('cliente.ultimo_acesso', null)
            ->where('assistente.resolvidas_pct', null)
            ->where('trava', null)
            ->where('conversas', []));
});
