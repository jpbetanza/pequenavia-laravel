<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\Conflito;
use App\Services\Agenda\VerificadorDeDisponibilidade;
use Carbon\CarbonImmutable;

/*
 * 2026-10-12 é segunda-feira (horário comercial: 08:00–22:00).
 * 2026-10-11 é domingo (fechado).
 */

function segunda(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-12');
}

function tiposDosConflitos(array $conflitos): array
{
    return array_map(fn (Conflito $conflito) => $conflito->tipo, $conflitos);
}

function reservaNo(Espaco $espaco, string $inicio, string $fim, array $atributos = []): Reserva
{
    return Reserva::factory()->create(array_merge([
        'user_id' => $espaco->user_id,
        'espaco_id' => $espaco->id,
        'titulo' => 'Encontro de noivos',
        'data' => '2026-10-12',
        'inicio' => $inicio,
        'fim' => $fim,
    ], $atributos));
}

beforeEach(function () {
    $this->dono = User::factory()->create();
    $this->espaco = Espaco::factory()->comHorarioComercial()->create([
        'user_id' => $this->dono->id,
        'nome' => 'Salão paroquial',
    ]);
    $this->verificador = new VerificadorDeDisponibilidade;
});

describe('verificar', function () {
    test('sem reservas e dentro do horário não há conflito', function () {
        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:00', '12:00', []);

        expect($conflitos)->toBe([]);
    });

    test('reservas que se encostam na borda não conflitam com intervalo 0', function () {
        reservaNo($this->espaco, '12:00', '13:00');

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '10:00', '12:00', []);

        expect($conflitos)->toBe([]);
    });

    test('reservas que se encostam na borda conflitam quando o intervalo é 30 minutos', function () {
        $this->espaco->update(['intervalo_min' => 30]);
        reservaNo($this->espaco, '12:00', '13:00');

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '10:00', '12:00', []);

        expect(tiposDosConflitos($conflitos))->toBe(['sala_ocupada']);
    });

    test('sala ocupada cita a reserva conflitante com o horário', function () {
        reservaNo($this->espaco, '09:00', '12:00');

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '10:00', '11:00', []);

        expect($conflitos)->toHaveCount(1)
            ->and($conflitos[0]->tipo)->toBe('sala_ocupada')
            ->and($conflitos[0]->mensagem)->toBe('Já reservado para “Encontro de noivos”, 09:00 – 12:00.');
    });

    test('ignorarReservaId deixa a própria reserva fora do conflito', function () {
        $reserva = reservaNo($this->espaco, '09:00', '12:00');

        $semIgnorar = $this->verificador->verificar($this->espaco, segunda(), '09:00', '12:00', []);
        $comIgnorar = $this->verificador->verificar($this->espaco, segunda(), '09:00', '12:00', [], $reserva->id);

        expect(tiposDosConflitos($semIgnorar))->toBe(['sala_ocupada'])
            ->and($comIgnorar)->toBe([]);
    });

    test('espaço inativo é conflito', function () {
        $this->espaco->update(['ativo' => false]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:00', '10:00', []);

        expect(tiposDosConflitos($conflitos))->toBe(['espaco_inativo']);
    });

    test('dia da semana sem horário é fora do horário (domingo fechado)', function () {
        $conflitos = $this->verificador->verificar($this->espaco, CarbonImmutable::parse('2026-10-11'), '09:00', '10:00', []);

        expect(tiposDosConflitos($conflitos))->toBe(['fora_do_horario']);
    });

    test('horário que sai do intervalo de abertura é fora do horário', function () {
        $antes = $this->verificador->verificar($this->espaco, segunda(), '07:00', '09:00', []);
        $depois = $this->verificador->verificar($this->espaco, segunda(), '21:00', '23:00', []);

        expect(tiposDosConflitos($antes))->toBe(['fora_do_horario'])
            ->and(tiposDosConflitos($depois))->toBe(['fora_do_horario']);
    });

    test('bloqueio cobre as duas datas da borda', function () {
        EspacoBloqueio::factory()->create([
            'espaco_id' => $this->espaco->id,
            'inicio' => '2026-10-12',
            'fim' => '2026-10-14',
            'motivo' => 'Reforma',
        ]);

        $naBorda = $this->verificador->verificar($this->espaco, CarbonImmutable::parse('2026-10-14'), '09:00', '10:00', []);
        $depois = $this->verificador->verificar($this->espaco, CarbonImmutable::parse('2026-10-15'), '09:00', '10:00', []);

        expect(tiposDosConflitos($naBorda))->toBe(['bloqueio'])
            ->and($naBorda[0]->mensagem)->toContain('Reforma')
            ->and($depois)->toBe([]);
    });

    test('equipamento com 2 unidades: 2 reservas simultâneas usam tudo e a 3ª conflita', function () {
        $cadeiras = Equipamento::factory()->create(['user_id' => $this->dono->id, 'nome' => 'Cadeiras', 'quantidade' => 2]);
        $outroSalao = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id]);
        $terceiroSalao = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id]);
        $umaUnidade = ['id' => $cadeiras->id, 'quantidade' => 1];

        reservaNo($outroSalao, '09:00', '12:00')->equipamentos()->attach($cadeiras->id, ['quantidade' => 1]);
        reservaNo($terceiroSalao, '10:00', '11:00')->equipamentos()->attach($cadeiras->id, ['quantidade' => 1]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:30', '10:30', [$umaUnidade]);

        expect(tiposDosConflitos($conflitos))->toBe(['equipamento_indisponivel'])
            ->and($conflitos[0]->mensagem)->toContain('Cadeiras');
    });

    test('equipamento com unidade livre não conflita', function () {
        $cadeiras = Equipamento::factory()->create(['user_id' => $this->dono->id, 'quantidade' => 2]);
        $outroSalao = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id]);
        reservaNo($outroSalao, '09:00', '12:00')->equipamentos()->attach($cadeiras->id, ['quantidade' => 1]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:30', '10:30', [
            ['id' => $cadeiras->id, 'quantidade' => 1],
        ]);

        expect($conflitos)->toBe([]);
    });

    test('equipamento que termina exatamente quando a reserva começa não conta como uso simultâneo', function () {
        $cadeiras = Equipamento::factory()->create(['user_id' => $this->dono->id, 'quantidade' => 1]);
        $outroSalao = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id]);
        reservaNo($outroSalao, '09:00', '12:00')->equipamentos()->attach($cadeiras->id, ['quantidade' => 1]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '12:00', '13:00', [
            ['id' => $cadeiras->id, 'quantidade' => 1],
        ]);

        expect($conflitos)->toBe([]);
    });

    test('equipamento em manutenção na data conta como zero disponível', function () {
        $projetor = Equipamento::factory()->create([
            'user_id' => $this->dono->id,
            'quantidade' => 1,
            'manutencao_ate' => '2026-10-20',
        ]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:00', '10:00', [
            ['id' => $projetor->id, 'quantidade' => 1],
        ]);

        expect(tiposDosConflitos($conflitos))->toBe(['equipamento_indisponivel'])
            ->and($conflitos[0]->mensagem)->toContain('manutenção');
    });

    test('manutenção que já acabou antes da data não conflita', function () {
        $projetor = Equipamento::factory()->create([
            'user_id' => $this->dono->id,
            'quantidade' => 1,
            'manutencao_ate' => '2026-10-01',
        ]);

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '09:00', '10:00', [
            ['id' => $projetor->id, 'quantidade' => 1],
        ]);

        expect($conflitos)->toBe([]);
    });

    test('fim igual ou anterior ao início é erro de uso e devolve só fora_do_horario', function () {
        reservaNo($this->espaco, '09:00', '12:00');

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '10:00', '10:00', []);

        expect($conflitos)->toHaveCount(1)
            ->and($conflitos[0]->tipo)->toBe('fora_do_horario')
            ->and($conflitos[0]->mensagem)->toContain('depois do horário inicial');
    });

    test('aceita horários com segundos', function () {
        reservaNo($this->espaco, '09:00:00', '12:00:00');

        $conflitos = $this->verificador->verificar($this->espaco, segunda(), '10:00:00', '11:00:00', []);

        expect(tiposDosConflitos($conflitos))->toBe(['sala_ocupada']);
    });

    test('todas as regras são reportadas, não só a primeira', function () {
        $this->espaco->update(['ativo' => false]);
        EspacoBloqueio::factory()->create([
            'espaco_id' => $this->espaco->id,
            'inicio' => '2026-10-11',
            'fim' => '2026-10-11',
            'motivo' => 'Manutenção do telhado',
        ]);

        $conflitos = $this->verificador->verificar($this->espaco, CarbonImmutable::parse('2026-10-11'), '09:00', '10:00', []);

        expect(tiposDosConflitos($conflitos))->toBe(['espaco_inativo', 'fora_do_horario', 'bloqueio']);
    });
});

describe('sugestoes', function () {
    test('sugere espaços livres do mesmo dono, sem o atual e só ativos, ordenados por capacidade e no máximo 3', function () {
        reservaNo($this->espaco, '09:00', '12:00');

        $cem = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Cem', 'capacidade' => 100]);
        $cinquenta = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Cinquenta', 'capacidade' => 50]);
        $vinte = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Vinte', 'capacidade' => 20]);
        $dez = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Dez', 'capacidade' => 10]);
        Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Sem capacidade', 'capacidade' => null]);
        Espaco::factory()->comHorarioComercial()->inativo()->create(['user_id' => $this->dono->id, 'capacidade' => 1]);
        Espaco::factory()->comHorarioComercial()->create(['capacidade' => 1]);

        $sugestoes = $this->verificador->sugestoes($this->espaco, segunda(), '09:00', '12:00', []);

        expect(array_column($sugestoes['espacos'], 'nome'))->toBe(['Dez', 'Vinte', 'Cinquenta'])
            ->and($sugestoes['espacos'][0])->toBe(['id' => $dez->id, 'nome' => 'Dez', 'capacidade' => 10]);
    });

    test('sugestões descartam espaços com conflito no mesmo horário', function () {
        $ocupado = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'capacidade' => 10]);
        $livre = Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'capacidade' => 20]);
        reservaNo($ocupado, '09:00', '12:00');
        reservaNo($this->espaco, '09:00', '12:00');

        $sugestoes = $this->verificador->sugestoes($this->espaco, segunda(), '09:00', '12:00', []);

        expect(array_column($sugestoes['espacos'], 'id'))->toBe([$livre->id]);
    });

    test('com pessoas informadas, só sugere espaços com capacidade suficiente', function () {
        Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Pequeno', 'capacidade' => 20]);
        Espaco::factory()->comHorarioComercial()->create(['user_id' => $this->dono->id, 'nome' => 'Grande', 'capacidade' => 40]);

        $sugestoes = $this->verificador->sugestoes($this->espaco, segunda(), '09:00', '12:00', [], null, 30);

        expect(array_column($sugestoes['espacos'], 'nome'))->toBe(['Grande']);
    });

    test('horário sugerido é o primeiro livre do mesmo espaço, com a mesma duração', function () {
        reservaNo($this->espaco, '09:00', '12:00');

        $sugestoes = $this->verificador->sugestoes($this->espaco, segunda(), '09:00', '11:00', []);

        expect($sugestoes['horario'])->toBe(['inicio' => '12:00', 'fim' => '14:00']);
    });

    test('horário sugerido é null quando o dia inteiro está ocupado', function () {
        reservaNo($this->espaco, '08:00', '22:00');

        $sugestoes = $this->verificador->sugestoes($this->espaco, segunda(), '09:00', '10:00', []);

        expect($sugestoes['horario'])->toBeNull();
    });
});

describe('POST agendamentos/disponibilidade', function () {
    test('exige login', function () {
        $this->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($this->espaco->id))
            ->assertUnauthorized();
    });

    test('devolve conflitos e sugestões só nas datas com conflito', function () {
        reservaNo($this->espaco, '09:00', '12:00');

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($this->espaco->id, [
                'datas' => ['2026-10-12', '2026-10-13'],
                'fim' => '10:00',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'datas')
            ->assertJsonPath('datas.0.data', '2026-10-12')
            ->assertJsonPath('datas.0.conflitos.0.tipo', 'sala_ocupada')
            ->assertJsonPath('datas.0.sugestoes.horario.inicio', '08:00')
            ->assertJsonPath('datas.0.sugestoes.horario.fim', '09:00')
            ->assertJsonPath('datas.1.data', '2026-10-13')
            ->assertJsonPath('datas.1.conflitos', [])
            ->assertJsonPath('datas.1.sugestoes', ['espacos' => [], 'horario' => null]);
    });

    test('ignorar_reserva_id tira a própria reserva do conflito', function () {
        $reserva = reservaNo($this->espaco, '09:00', '12:00');

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($this->espaco->id, [
                'datas' => ['2026-10-12'],
                'fim' => '12:00',
                'ignorar_reserva_id' => $reserva->id,
            ]))
            ->assertOk()
            ->assertJsonPath('datas.0.conflitos', []);
    });

    test('espaço de outro dono responde 404', function () {
        $alheio = Espaco::factory()->comHorarioComercial()->create();

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($alheio->id))
            ->assertNotFound();
    });

    test('equipamento de outro dono responde 404', function () {
        $alheio = Equipamento::factory()->create();

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($this->espaco->id, [
                'equipamentos' => [['id' => $alheio->id, 'quantidade' => 1]],
            ]))
            ->assertNotFound();
    });

    test('reserva ignorada de outro dono responde 404', function () {
        $alheia = Reserva::factory()->create();

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), corpoDaBusca($this->espaco->id, [
                'ignorar_reserva_id' => $alheia->id,
            ]))
            ->assertNotFound();
    });

    test('valida o formato da busca com mensagens em português', function () {
        $erros = $this->actingAs($this->dono)
            ->postJson(route('agendamentos.disponibilidade'), [
                'espaco_id' => $this->espaco->id,
                'datas' => [],
                'inicio' => '09:00',
                'fim' => '10:00',
                'equipamentos' => [['id' => 'nao-e-uuid', 'quantidade' => 0]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['datas', 'equipamentos.0.id', 'equipamentos.0.quantidade'])
            ->json('errors');

        expect($erros['datas'][0])->toBe('Informe pelo menos uma data.')
            ->and($erros['equipamentos.0.quantidade'][0])->toBe('A quantidade deve ser de pelo menos 1.');
    });
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function corpoDaBusca(string $espacoId, array $extra = []): array
{
    return array_merge([
        'espaco_id' => $espacoId,
        'datas' => ['2026-10-12'],
        'inicio' => '09:00',
        'fim' => '10:00',
        'equipamentos' => [],
    ], $extra);
}
