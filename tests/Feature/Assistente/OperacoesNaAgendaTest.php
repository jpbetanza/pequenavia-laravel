<?php

use App\Enums\StatusDaAcao;
use App\Enums\StatusSolicitacao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Assistente\CaixaDeFerramentas;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\ConsultarAgenda;
use App\Services\Assistente\Ferramentas\MarcarEquipamento;
use App\Services\Assistente\Ferramentas\ProporMudancaNaReserva;
use App\Services\Assistente\Ferramentas\ResponderSolicitacao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['assistente.chave' => 'chave-de-teste']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));

    $this->usuario = User::factory()->create();
    $this->conversa = Conversa::factory()->for($this->usuario)->create();
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->usuario)->create(['nome' => 'Salão paroquial']);
    $this->capela = Espaco::factory()->comHorarioComercial()->for($this->usuario)->create(['nome' => 'Capela']);
    $this->projetor = Equipamento::factory()->for($this->usuario)->create(['nome' => 'Projetor', 'quantidade' => 1]);
});

function executarFerramenta(string $classe, array $argumentos): mixed
{
    return app($classe)->executar(test()->usuario, test()->conversa, $argumentos);
}

function confirmarAcao(AcaoProposta $acao)
{
    return test()->actingAs(test()->usuario)->postJson(route('assistente.acoes.confirmar', $acao));
}

describe('responder_solicitacao', function () {
    beforeEach(function () {
        $this->pedido = Solicitacao::factory()->pendente()->for($this->usuario)->create([
            'nome' => 'Maria Silva', 'telefone' => '11987654321', 'titulo' => 'Ensaio do coral',
            'data' => '2026-10-16', 'inicio' => '19:00', 'fim' => '21:00', 'espaco_id' => $this->salao->id,
        ]);
        $this->pedido->equipamentos()->attach($this->projetor->id, ['quantidade' => 1]);
    });

    it('propõe a aprovação sem gravar e, confirmada, cria a reserva com os equipamentos', function () {
        $resultado = executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']);

        expect(Reserva::count())->toBe(0)
            ->and($this->pedido->fresh()->status)->toBe(StatusSolicitacao::Pendente)
            ->and($resultado->cartao['dados'])->toMatchArray(['tipo' => 'aprovar_solicitacao', 'status' => 'proposta', 'chave' => "solicitacao:{$this->pedido->id}"])
            ->and($resultado->cartao['dados']['operacao'])->toMatchArray([
                'titulo' => 'Aprovar solicitação: Ensaio do coral',
                'botao' => 'Aprovar solicitação',
                'destrutiva' => false,
            ])
            ->and(collect($resultado->cartao['dados']['operacao']['linhas'])->pluck('depois', 'rotulo')->all())->toMatchArray([
                'Pedido por' => 'Maria Silva · 11987654321',
                'Data' => 'sex 16/10/2026',
                'Horário' => '19:00–21:00',
                'Espaço' => 'Salão paroquial',
                'Equipamentos' => 'Projetor (1)',
            ]);

        confirmarAcao(AcaoProposta::sole())
            ->assertOk()
            ->assertJsonPath('acao.status', 'confirmada')
            ->assertJsonPath('acao.operacao.feito', 'Aprovada')
            ->assertJsonPath('acao.resultado.link', '/agendamentos/calendario?data=2026-10-16');

        $reserva = Reserva::sole();

        expect($this->pedido->fresh())->status->toBe(StatusSolicitacao::Aprovada)->reserva_id->toBe($reserva->id)
            ->and($reserva->espaco_id)->toBe($this->salao->id)
            ->and($reserva->equipamentos->pluck('id')->all())->toBe([$this->projetor->id])
            ->and(AcaoProposta::sole()->resultado_id)->toBe($reserva->id);
    });

    it('aprova em outro espaço e grava a troca só na confirmação', function () {
        $this->pedido->update(['espaco_id' => null]);

        expect(fn () => executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']))
            ->toThrow(ArgumentoInvalido::class, 'não tem espaço');

        $resultado = executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar', 'espaco' => 'capela']);

        expect($this->pedido->fresh()->espaco_id)->toBeNull()
            ->and(collect($resultado->cartao['dados']['operacao']['linhas'])->firstWhere('rotulo', 'Espaço'))->toBe(['rotulo' => 'Espaço', 'antes' => null, 'depois' => 'Capela']);

        confirmarAcao(AcaoProposta::sole())->assertOk();

        expect(Reserva::sole()->espaco_id)->toBe($this->capela->id)
            ->and($this->pedido->fresh()->espaco_id)->toBe($this->capela->id);
    });

    it('com conflito não propõe e devolve os conflitos ao modelo', function () {
        Reserva::factory()->for($this->usuario)->create(['espaco_id' => $this->salao->id, 'data' => '2026-10-16', 'inicio' => '18:00', 'fim' => '20:00']);

        $resultado = executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']);

        expect($resultado->paraOModelo['criada'])->toBeFalse()
            ->and($resultado->paraOModelo['conflitos'][0]['tipo'])->toBe('sala_ocupada')
            ->and($resultado->cartao)->toBeNull()
            ->and(AcaoProposta::count())->toBe(0);
    });

    it('se a solicitação foi editada depois da proposta, a confirmação responde 422 e não aprova', function () {
        executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']);
        $this->pedido->update(['inicio' => '20:00']);

        confirmarAcao(AcaoProposta::sole())->assertUnprocessable()->assertJsonPath('message', 'A solicitação mudou desde a proposta. Peça de novo.');

        expect(Reserva::count())->toBe(0)
            ->and(AcaoProposta::sole()->status)->toBe(StatusDaAcao::Proposta);
    });

    it('com um conflito novo na hora de confirmar, responde 422 e não cria a reserva', function () {
        executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']);
        Reserva::factory()->for($this->usuario)->create(['espaco_id' => $this->salao->id, 'data' => '2026-10-16', 'inicio' => '18:00', 'fim' => '20:00']);

        confirmarAcao(AcaoProposta::sole())->assertUnprocessable()->assertJsonPath('message', fn (string $mensagem) => str_starts_with($mensagem, 'Há conflito'));

        expect($this->pedido->fresh()->status)->toBe(StatusSolicitacao::Pendente)
            ->and(Reserva::count())->toBe(1);
    });

    it('recusa com motivo, e a recusa substitui a aprovação proposta antes', function () {
        executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']);
        $resultado = executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'recusar', 'motivo' => 'Salão em reforma']);

        expect(AcaoProposta::where('tipo', 'aprovar_solicitacao')->sole()->status)->toBe(StatusDaAcao::Expirada)
            ->and($resultado->cartao['dados']['operacao'])->toMatchArray(['botao' => 'Confirmar recusa', 'destrutiva' => true]);

        confirmarAcao(AcaoProposta::where('tipo', 'recusar_solicitacao')->sole())->assertOk()->assertJsonPath('acao.operacao.feito', 'Recusada');

        expect($this->pedido->fresh())->status->toBe(StatusSolicitacao::Recusada)->motivo_recusa->toBe('Salão em reforma')
            ->and(Reserva::count())->toBe(0);
    });

    it('não responde solicitação já respondida nem de outra agenda', function () {
        $this->pedido->update(['status' => StatusSolicitacao::Recusada]);
        $deOutro = Solicitacao::factory()->pendente()->create();

        expect(fn () => executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $this->pedido->id, 'resposta' => 'aprovar']))
            ->toThrow(ArgumentoInvalido::class, 'Esta solicitação já foi recusada.')
            ->and(fn () => executarFerramenta(ResponderSolicitacao::class, ['solicitacao' => $deOutro->id, 'resposta' => 'recusar']))
            ->toThrow(ArgumentoInvalido::class, 'Não encontrei essa solicitação');
    });
});

describe('propor_mudanca_na_reserva', function () {
    beforeEach(function () {
        $this->catequese = Grupo::factory()->for($this->usuario)->create(['nome' => 'Catequese']);
        $this->reserva = Reserva::factory()->for($this->usuario)->create([
            'titulo' => 'Encontro', 'espaco_id' => $this->salao->id, 'grupo_id' => $this->catequese->id,
            'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '16:00',
        ]);
    });

    it('propõe a alteração mostrando só o que muda e, confirmada, grava', function () {
        $resultado = executarFerramenta(ProporMudancaNaReserva::class, [
            'reserva' => $this->reserva->id, 'operacao' => 'alterar', 'inicio' => '15:00', 'fim' => '17:00', 'grupo' => '',
        ]);

        expect($this->reserva->fresh()->inicio)->toStartWith('14:00')
            ->and($resultado->cartao['dados']['operacao']['titulo'])->toBe('Alterar reserva: Encontro')
            ->and($resultado->cartao['dados']['operacao']['linhas'])->toBe([
                ['rotulo' => 'Grupo', 'antes' => 'Catequese', 'depois' => null],
                ['rotulo' => 'Horário', 'antes' => '14:00–16:00', 'depois' => '15:00–17:00'],
            ]);

        confirmarAcao(AcaoProposta::sole())->assertOk()->assertJsonPath('acao.operacao.feito', 'Alterada');

        expect($this->reserva->fresh())
            ->inicio->toStartWith('15:00')
            ->fim->toStartWith('17:00')
            ->grupo_id->toBeNull()
            ->titulo->toBe('Encontro');
    });

    it('troca os equipamentos pela lista enviada e mostra o contexto quando data e horário ficam', function () {
        $resultado = executarFerramenta(ProporMudancaNaReserva::class, [
            'reserva' => $this->reserva->id, 'operacao' => 'alterar', 'equipamentos' => [['equipamento' => 'projetor', 'quantidade' => 1]],
        ]);

        expect($resultado->cartao['dados']['operacao']['linhas'])->toBe([
            ['rotulo' => 'Quando', 'antes' => null, 'depois' => 'sex 16/10/2026, 14:00–16:00'],
            ['rotulo' => 'Equipamentos', 'antes' => '—', 'depois' => 'Projetor (1)'],
        ]);

        confirmarAcao(AcaoProposta::sole())->assertOk();

        expect($this->reserva->fresh()->equipamentos->pluck('id')->all())->toBe([$this->projetor->id]);
    });

    it('com conflito não propõe; sem mudança nem horário válido, avisa o modelo', function () {
        Reserva::factory()->for($this->usuario)->create(['espaco_id' => $this->capela->id, 'data' => '2026-10-16', 'inicio' => '13:00', 'fim' => '15:00']);

        $resultado = executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $this->reserva->id, 'operacao' => 'alterar', 'espaco' => 'Capela']);

        expect($resultado->paraOModelo['criada'])->toBeFalse()
            ->and($resultado->paraOModelo['conflitos'][0]['data'])->toBe('2026-10-16')
            ->and(AcaoProposta::count())->toBe(0)
            ->and(fn () => executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $this->reserva->id, 'operacao' => 'alterar', 'titulo' => 'Encontro']))
            ->toThrow(ArgumentoInvalido::class, 'Nada para alterar')
            ->and(fn () => executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $this->reserva->id, 'operacao' => 'alterar', 'fim' => '13:00']))
            ->toThrow(ArgumentoInvalido::class, 'O horário final precisa ser depois do horário inicial.')
            ->and(fn () => executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => Str::uuid()->toString(), 'operacao' => 'cancelar']))
            ->toThrow(ArgumentoInvalido::class, 'Não encontrei essa reserva');
    });

    it('cancela só a reserva, avisando do equipamento entregue e não recolhido', function () {
        $this->reserva->equipamentos()->attach($this->projetor->id, ['quantidade' => 1, 'entregue_em' => now()]);

        $resultado = executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $this->reserva->id, 'operacao' => 'cancelar']);

        expect($resultado->cartao['dados']['operacao'])->toMatchArray([
            'titulo' => 'Cancelar reserva: Encontro',
            'botao' => 'Confirmar cancelamento',
            'destrutiva' => true,
            'avisos' => ['Projetor foi entregue e ainda não foi recolhido.'],
        ]);

        confirmarAcao(AcaoProposta::sole())->assertOk()->assertJsonPath('acao.operacao.feito', 'Cancelada');

        expect(Reserva::count())->toBe(0);
    });

    it('numa série, esta e as próximas: muda o horário de todas a partir dela e cancela todas a partir dela', function () {
        $serie = (string) Str::uuid();
        $datas = ['2026-10-14', '2026-10-21', '2026-10-28', '2026-11-04'];
        $reservas = collect($datas)->map(fn (string $data) => Reserva::factory()->for($this->usuario)->create([
            'titulo' => 'Catequese', 'espaco_id' => $this->salao->id, 'data' => $data, 'inicio' => '14:00', 'fim' => '16:00', 'serie_id' => $serie,
        ]));

        $resultado = executarFerramenta(ProporMudancaNaReserva::class, [
            'reserva' => $reservas[1]->id, 'operacao' => 'alterar', 'escopo' => 'esta_e_proximas', 'inicio' => '09:00', 'fim' => '11:00',
        ]);

        expect($resultado->cartao['dados']['operacao']['avisos'])->toBe(['Vale também para as próximas 2 reservas da série, até qua 04/11/2026.']);

        confirmarAcao(AcaoProposta::sole())->assertOk();

        expect($reservas->map(fn (Reserva $reserva) => substr($reserva->fresh()->inicio, 0, 5))->all())->toBe(['14:00', '09:00', '09:00', '09:00']);

        executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $reservas[2]->id, 'operacao' => 'cancelar', 'escopo' => 'esta_e_proximas']);
        confirmarAcao(AcaoProposta::latest('created_at')->where('tipo', 'cancelar_reserva')->sole())->assertOk();

        expect(Reserva::orderBy('data')->pluck('data')->map->toDateString()->all())->toBe(['2026-10-14', '2026-10-16', '2026-10-21']);
    });

    it('se a reserva mudou depois da proposta, a confirmação responde 422 e não grava', function () {
        executarFerramenta(ProporMudancaNaReserva::class, ['reserva' => $this->reserva->id, 'operacao' => 'cancelar']);
        $this->reserva->update(['titulo' => 'Outro nome']);

        confirmarAcao(AcaoProposta::sole())->assertUnprocessable()->assertJsonPath('message', 'A reserva mudou desde a proposta. Peça de novo.');

        expect(Reserva::count())->toBe(1);
    });
});

describe('marcar_equipamento', function () {
    beforeEach(function () {
        $this->reserva = Reserva::factory()->for($this->usuario)->create([
            'titulo' => 'Encontro', 'espaco_id' => $this->salao->id, 'data' => '2026-10-13', 'inicio' => '14:00', 'fim' => '16:00',
        ]);
        $this->reserva->equipamentos()->attach($this->projetor->id, ['quantidade' => 1]);
    });

    function pivotDoProjetor(): object
    {
        return DB::table('reserva_equipamento')->where('reserva_id', test()->reserva->id)->where('equipamento_id', test()->projetor->id)->first();
    }

    it('propõe a entrega e, confirmada, grava o horário; depois a devolução', function () {
        $resultado = executarFerramenta(MarcarEquipamento::class, ['reserva' => $this->reserva->id, 'equipamento' => 'Projetor', 'acao' => 'entregue']);

        expect(pivotDoProjetor()->entregue_em)->toBeNull()
            ->and($resultado->cartao['dados']['operacao'])->toMatchArray(['titulo' => 'Entregar Projetor (1): Encontro', 'botao' => 'Marcar como entregue']);

        confirmarAcao(AcaoProposta::sole())->assertOk()->assertJsonPath('acao.operacao.feito', 'Entregue')->assertJsonPath('acao.resultado.link', '/agendamentos/equipamentos');

        expect(pivotDoProjetor()->entregue_em)->not->toBeNull()
            ->and(fn () => executarFerramenta(MarcarEquipamento::class, ['reserva' => $this->reserva->id, 'equipamento' => 'Projetor', 'acao' => 'entregue']))
            ->toThrow(ArgumentoInvalido::class, 'Projetor já foi marcado como entregue em 13/10 às 10:00.');

        executarFerramenta(MarcarEquipamento::class, ['reserva' => $this->reserva->id, 'equipamento' => 'Projetor', 'acao' => 'recolhido']);
        confirmarAcao(AcaoProposta::where('tipo', 'recolher_equipamento')->sole())->assertOk();

        expect(pivotDoProjetor()->recolhido_em)->not->toBeNull();
    });

    it('recusa equipamento que não é da reserva e confirmação de algo já marcado', function () {
        Equipamento::factory()->for($this->usuario)->create(['nome' => 'Caixa de som']);

        expect(fn () => executarFerramenta(MarcarEquipamento::class, ['reserva' => $this->reserva->id, 'equipamento' => 'Caixa de som', 'acao' => 'entregue']))
            ->toThrow(ArgumentoInvalido::class, 'A reserva “Encontro” não tem o equipamento “Caixa de som”.');

        executarFerramenta(MarcarEquipamento::class, ['reserva' => $this->reserva->id, 'equipamento' => 'Projetor', 'acao' => 'entregue']);
        DB::table('reserva_equipamento')->update(['entregue_em' => now()]);

        confirmarAcao(AcaoProposta::sole())->assertUnprocessable()->assertJsonPath('message', 'Já estava marcado como entregue.');
    });
});

test('consultar_agenda dá ao modelo o id, se é série e a situação dos equipamentos', function () {
    $reserva = Reserva::factory()->for($this->usuario)->create(['espaco_id' => $this->salao->id, 'data' => '2026-10-16', 'inicio' => '14:00', 'fim' => '16:00']);
    $reserva->equipamentos()->attach($this->projetor->id, ['quantidade' => 1, 'entregue_em' => now()]);

    $resultado = executarFerramenta(ConsultarAgenda::class, ['inicio' => '2026-10-16', 'fim' => '2026-10-16']);

    expect($resultado->paraOModelo['reservas'][0])->toMatchArray([
        'id' => $reserva->id,
        'serie' => false,
        'equipamentos' => [['nome' => 'Projetor', 'quantidade' => 1, 'entregue' => true, 'recolhido' => false]],
    ]);
});

test('as ferramentas novas estão na caixa do assistente', function () {
    $nomes = collect(app(CaixaDeFerramentas::class)->definicoes())->pluck('function.name');

    expect($nomes)->toContain('responder_solicitacao', 'propor_mudanca_na_reserva', 'marcar_equipamento');
});
