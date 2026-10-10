<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // Hoje é sábado, 10/10/2026, 10:00 no fuso da agenda. A segunda de referência é 12/10/2026.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));

    $this->dono = User::factory()->create();
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Salão paroquial']);
    $this->projetor = Equipamento::factory()->for($this->dono)->create(['nome' => 'Projetor', 'quantidade' => 2]);
});

/**
 * Corpo de uma reserva de segunda à noite no salão, com os campos que a secretaria envia.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function corpoDeReservaNoSalao(Espaco $espaco, array $extra = []): array
{
    return array_merge([
        'titulo' => 'Ensaio do coral',
        'grupo_id' => null,
        'pessoas' => 20,
        'espaco_id' => $espaco->id,
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
        'observacoes' => null,
        'equipamentos' => [],
        'repetir' => 'nao',
    ], $extra);
}

/**
 * Três ocorrências semanais (12, 19 e 26/10) do mesmo dono, com a mesma série.
 *
 * @return array{0: Reserva, 1: Reserva, 2: Reserva}
 */
function serieDoCoral(User $dono, Espaco $espaco): array
{
    $serieId = (string) Str::uuid();

    return collect(['2026-10-12', '2026-10-19', '2026-10-26'])
        ->map(fn (string $data) => Reserva::factory()->for($dono)->for($espaco, 'espaco')->create([
            'titulo' => 'Ensaio do coral',
            'data' => $data,
            'inicio' => '19:00',
            'fim' => '21:00',
            'serie_id' => $serieId,
        ]))
        ->all();
}

describe('criação', function () {
    it('cria uma reserva simples com os equipamentos', function () {
        $this->actingAs($this->dono)
            ->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => 1]],
            ]))
            ->assertRedirect(route('agendamentos.calendario', ['data' => '2026-10-12']))
            ->assertSessionHasNoErrors();

        $reserva = Reserva::query()->sole();

        expect($reserva->user_id)->toBe($this->dono->id)
            ->and($reserva->serie_id)->toBeNull()
            ->and($reserva->data->toDateString())->toBe('2026-10-12')
            ->and($reserva->equipamentos->first()->pivot->quantidade)->toBe(1);
    });

    it('repete toda semana, pula as datas escolhidas e usa uma só série', function () {
        $this->actingAs($this->dono)
            ->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'repetir' => 'semanal',
                'ate' => '2026-10-26',
                'pular' => ['2026-10-19'],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reservas = Reserva::query()->orderBy('data')->get();

        expect($reservas->map(fn (Reserva $reserva) => $reserva->data->toDateString())->all())->toBe(['2026-10-12', '2026-10-26'])
            ->and($reservas->first()->serie_id)->not->toBeNull()
            ->and($reservas->pluck('serie_id')->unique()->count())->toBe(1);
    });

    it('repete todo mês no 2º sábado e pula o que não cai nele', function () {
        $this->actingAs($this->dono)
            ->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'data' => '2026-10-10',
                'inicio' => '09:00',
                'fim' => '10:00',
                'repetir' => 'mensal',
                'ate' => '2027-03-31',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Reserva::query()->orderBy('data')->get()->map(fn (Reserva $reserva) => $reserva->data->toDateString())->all())
            ->toBe(['2026-10-10', '2026-11-14', '2026-12-12', '2027-01-09', '2027-02-13', '2027-03-13']);
    });

    it('limita a série a 52 ocorrências', function () {
        $this->actingAs($this->dono)
            ->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'repetir' => 'semanal',
                'ate' => '2031-12-31',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Reserva::query()->count())->toBe(52);
    });

    it('não grava nada quando todas as datas são puladas', function () {
        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'pular' => ['2026-10-12'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pular']);

        expect(Reserva::query()->count())->toBe(0);
    });

    it('com conflito, responde 422 com os conflitos por data e não grava nenhuma ocorrência', function () {
        Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'titulo' => 'Encontro de noivos',
            'data' => '2026-10-19',
            'inicio' => '09:00',
            'fim' => '12:00',
        ]);

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'repetir' => 'semanal',
                'ate' => '2026-10-26',
                'inicio' => '10:00',
                'fim' => '11:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonCount(1, 'conflitos')
            ->assertJsonPath('conflitos.0.data', '2026-10-19')
            ->assertJsonPath('conflitos.0.conflitos.0.tipo', 'sala_ocupada')
            ->assertJsonPath('conflitos.0.conflitos.0.mensagem', 'Já reservado para “Encontro de noivos”, 09:00 – 12:00.');

        expect(Reserva::query()->count())->toBe(1);
    });

    it('com equipamento já todo em uso no horário, responde 422 e não grava nada', function () {
        $salaSaoJose = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Sala São José']);
        $existente = Reserva::factory()->for($this->dono)->for($salaSaoJose, 'espaco')->create([
            'data' => '2026-10-12',
            'inicio' => '10:00',
            'fim' => '12:00',
        ]);
        $existente->equipamentos()->attach($this->projetor, ['quantidade' => 2]);

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'inicio' => '10:00',
                'fim' => '11:00',
                'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => 1]],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('conflitos.0.data', '2026-10-12')
            ->assertJsonPath('conflitos.0.conflitos.0.tipo', 'equipamento_indisponivel');

        expect(Reserva::query()->count())->toBe(1);
    });

    it('a secretaria reserva fora da antecedência e da duração que o espaço permite ao público', function () {
        $this->salao->update(['antecedencia_min_horas' => 48, 'duracao_max_min' => 60]);

        $this->actingAs($this->dono)
            ->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'data' => '2026-10-10',
                'inicio' => '14:00',
                'fim' => '18:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Reserva::query()->count())->toBe(1);
    });

    it('exige que o fim seja depois do início', function () {
        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao, [
                'inicio' => '10:00',
                'fim' => '09:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim']);
    });

    it('responde 404 quando o espaço, o grupo ou um equipamento são de outro dono', function (string $alvo) {
        $outro = User::factory()->create();
        $espacoAlheio = Espaco::factory()->for($outro)->create();
        $grupoAlheio = Grupo::factory()->for($outro)->create();
        $equipamentoAlheio = Equipamento::factory()->for($outro)->create();

        $corpo = match ($alvo) {
            'espaco' => corpoDeReservaNoSalao($espacoAlheio),
            'grupo' => corpoDeReservaNoSalao($this->salao, ['grupo_id' => $grupoAlheio->id]),
            'equipamento' => corpoDeReservaNoSalao($this->salao, ['equipamentos' => [['id' => $equipamentoAlheio->id, 'quantidade' => 1]]]),
        };

        $this->actingAs($this->dono)
            ->postJson(route('agendamentos.reservas.store'), $corpo)
            ->assertNotFound();

        expect(Reserva::query()->count())->toBe(0);
    })->with(['espaco', 'grupo', 'equipamento']);
});

describe('edição', function () {
    it('ignora a própria reserva ao conferir o conflito', function () {
        $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'data' => '2026-10-12',
            'inicio' => '09:00',
            'fim' => '12:00',
        ]);

        $this->actingAs($this->dono)
            ->put(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($this->salao, [
                'titulo' => 'Catequese',
                'data' => '2026-10-12',
                'inicio' => '10:00',
                'fim' => '11:00',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($reserva->fresh())
            ->titulo->toBe('Catequese')
            ->inicio->toBe('10:00:00');
    });

    it('com conflito de outra reserva, responde 422 e não altera nada', function () {
        $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'titulo' => 'Catequese',
            'data' => '2026-10-12',
            'inicio' => '09:00',
            'fim' => '12:00',
        ]);
        Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'titulo' => 'Missa',
            'data' => '2026-10-12',
            'inicio' => '13:00',
            'fim' => '15:00',
        ]);

        $this->actingAs($this->dono)
            ->putJson(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($this->salao, [
                'titulo' => 'Catequese nova',
                'data' => '2026-10-12',
                'inicio' => '14:00',
                'fim' => '16:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('conflitos.0.data', '2026-10-12');

        expect($reserva->fresh())
            ->titulo->toBe('Catequese')
            ->inicio->toBe('09:00:00');
    });

    it('edita o título de uma reserva em espaço inativo sem trocar o espaço', function () {
        $desativado = Espaco::factory()->inativo()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Sala desativada']);
        $reserva = Reserva::factory()->for($this->dono)->for($desativado, 'espaco')->create([
            'titulo' => 'Ensaio antigo',
            'data' => '2026-10-12',
            'inicio' => '19:00',
            'fim' => '21:00',
        ]);

        $this->actingAs($this->dono)
            ->put(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($desativado, [
                'titulo' => 'Ensaio renomeado',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($reserva->fresh())
            ->titulo->toBe('Ensaio renomeado')
            ->espaco_id->toBe($desativado->id);
    });

    it('trocar a reserva para um espaço inativo responde 422 com o conflito de espaço inativo', function () {
        $desativado = Espaco::factory()->inativo()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Sala desativada']);
        $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'data' => '2026-10-12',
            'inicio' => '19:00',
            'fim' => '21:00',
        ]);

        $this->actingAs($this->dono)
            ->putJson(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($desativado, [
                'data' => '2026-10-12',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('conflitos.0.conflitos.0.tipo', 'espaco_inativo');

        expect($reserva->fresh()->espaco_id)->toBe($this->salao->id);
    });

    it('no escopo padrão, altera só a reserva escolhida mesmo quando ela faz parte de uma série', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);

        $this->actingAs($this->dono)
            ->put(route('agendamentos.reservas.update', $dezenove), corpoDeReservaNoSalao($this->salao, [
                'titulo' => 'Ensaio extra',
                'data' => '2026-10-19',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($dezenove->fresh()->titulo)->toBe('Ensaio extra')
            ->and($doze->fresh()->titulo)->toBe('Ensaio do coral')
            ->and($vinteSeis->fresh()->titulo)->toBe('Ensaio do coral');
    });

    it('com "esta e as próximas", altera só as futuras da série e preserva as datas das outras', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);

        $this->actingAs($this->dono)
            ->put(route('agendamentos.reservas.update', $dezenove), corpoDeReservaNoSalao($this->salao, [
                'titulo' => 'Ensaio do coral (novo horário)',
                'data' => '2026-10-20',
                'inicio' => '18:00',
                'fim' => '20:00',
                'escopo' => 'esta_e_proximas',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($doze->fresh())
            ->titulo->toBe('Ensaio do coral')
            ->inicio->toBe('19:00:00');
        expect($doze->fresh()->data->toDateString())->toBe('2026-10-12');

        expect($dezenove->fresh())
            ->titulo->toBe('Ensaio do coral (novo horário)')
            ->inicio->toBe('18:00:00');
        expect($dezenove->fresh()->data->toDateString())->toBe('2026-10-20');

        expect($vinteSeis->fresh())
            ->titulo->toBe('Ensaio do coral (novo horário)')
            ->inicio->toBe('18:00:00');
        expect($vinteSeis->fresh()->data->toDateString())->toBe('2026-10-26');
    });

    it('com "esta e as próximas", é atômico: se uma futura conflita, nada é salvo', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);
        Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create([
            'titulo' => 'Batizado',
            'data' => '2026-10-26',
            'inicio' => '09:00',
            'fim' => '10:30',
        ]);

        $this->actingAs($this->dono)
            ->putJson(route('agendamentos.reservas.update', $dezenove), corpoDeReservaNoSalao($this->salao, [
                'titulo' => 'Novo título',
                'data' => '2026-10-19',
                'inicio' => '10:00',
                'fim' => '11:00',
                'escopo' => 'esta_e_proximas',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('conflitos.0.data', '2026-10-26');

        expect($dezenove->fresh())->titulo->toBe('Ensaio do coral')->inicio->toBe('19:00:00')
            ->and($vinteSeis->fresh())->titulo->toBe('Ensaio do coral')->inicio->toBe('19:00:00');
    });

    it('com "esta e as próximas", troca os equipamentos de todas as reservas da série', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);
        foreach ([$doze, $dezenove, $vinteSeis] as $reserva) {
            $reserva->equipamentos()->attach($this->projetor, ['quantidade' => 1]);
        }

        $this->actingAs($this->dono)
            ->put(route('agendamentos.reservas.update', $dezenove), corpoDeReservaNoSalao($this->salao, [
                'data' => '2026-10-19',
                'equipamentos' => [],
                'escopo' => 'esta_e_proximas',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($doze->fresh()->equipamentos)->toHaveCount(1)
            ->and($dezenove->fresh()->equipamentos)->toHaveCount(0)
            ->and($vinteSeis->fresh()->equipamentos)->toHaveCount(0);
    });

    it('responde 404 para a reserva de outro dono', function () {
        $alheia = Reserva::factory()->for(User::factory())->for(Espaco::factory(), 'espaco')->create();

        $this->actingAs($this->dono)
            ->putJson(route('agendamentos.reservas.update', $alheia), corpoDeReservaNoSalao($this->salao))
            ->assertNotFound();
    });

    it('responde 404 quando um equipamento da edição é de outro dono', function () {
        $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-12']);
        $equipamentoAlheio = Equipamento::factory()->for(User::factory())->create();

        $this->actingAs($this->dono)
            ->putJson(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($this->salao, [
                'equipamentos' => [['id' => $equipamentoAlheio->id, 'quantidade' => 1]],
            ]))
            ->assertNotFound();
    });
});

describe('exclusão', function () {
    it('no escopo padrão, exclui só a reserva e os equipamentos dela', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);
        $dezenove->equipamentos()->attach($this->projetor, ['quantidade' => 1]);

        $this->actingAs($this->dono)
            ->delete(route('agendamentos.reservas.destroy', $dezenove))
            ->assertRedirect(route('agendamentos.calendario', ['data' => '2026-10-19']))
            ->assertSessionHasNoErrors();

        expect(Reserva::query()->pluck('id')->all())->toEqualCanonicalizing([$doze->id, $vinteSeis->id])
            ->and(DB::table('reserva_equipamento')->count())->toBe(0);
    });

    it('com "esta e as próximas", exclui a partir desta data e só da mesma série', function () {
        [$doze, $dezenove, $vinteSeis] = serieDoCoral($this->dono, $this->salao);
        $avulsa = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create(['data' => '2026-10-26', 'titulo' => 'Avulsa']);

        $this->actingAs($this->dono)
            ->delete(route('agendamentos.reservas.destroy', $dezenove), ['escopo' => 'esta_e_proximas'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Reserva::query()->pluck('id')->all())->toEqualCanonicalizing([$doze->id, $avulsa->id]);
    });

    it('responde 404 para a reserva de outro dono', function () {
        $alheia = Reserva::factory()->for(User::factory())->for(Espaco::factory(), 'espaco')->create();

        $this->actingAs($this->dono)
            ->delete(route('agendamentos.reservas.destroy', $alheia))
            ->assertNotFound();

        expect(Reserva::query()->whereKey($alheia->id)->exists())->toBeTrue();
    });
});

it('redireciona o visitante para o login nas rotas de reservas', function () {
    $reserva = Reserva::factory()->for($this->dono)->for($this->salao, 'espaco')->create();

    $this->post(route('agendamentos.reservas.store'), corpoDeReservaNoSalao($this->salao))->assertRedirect(route('login'));
    $this->put(route('agendamentos.reservas.update', $reserva), corpoDeReservaNoSalao($this->salao))->assertRedirect(route('login'));
    $this->delete(route('agendamentos.reservas.destroy', $reserva))->assertRedirect(route('login'));
});
