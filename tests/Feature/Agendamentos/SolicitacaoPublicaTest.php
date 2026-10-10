<?php

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Hoje é sábado, 10/10/2026, 10:00 no fuso da agenda. A segunda de referência é 12/10/2026.
    $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));

    $this->dono = User::factory()->create(['name' => 'Paróquia São José']);
    $this->slug = $this->dono->agendaSlug();
    $this->grupo = Grupo::factory()->for($this->dono)->create(['nome' => 'Catequese']);
    $this->salao = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Salão paroquial', 'capacidade' => 80]);
    $this->projetor = Equipamento::factory()->for($this->dono)->create(['nome' => 'Projetor', 'quantidade' => 2]);

    $this->pedido = [
        'grupo_id' => $this->grupo->id,
        'nome' => 'Maria Silva',
        'telefone' => '(11) 98765-4321',
        'titulo' => 'Ensaio do coral',
        'data' => '2026-10-12',
        'inicio' => '19:00',
        'fim' => '21:00',
        'espaco_id' => $this->salao->id,
        'pessoas' => 20,
        'equipamentos' => [],
        'observacoes' => null,
        'site' => '',
    ];
});

describe('envio', function () {
    it('envia um pedido válido, cria a solicitação pendente e redireciona para o aviso de envio', function () {
        $this->post(route('reservar.store', $this->slug), [...$this->pedido, 'equipamentos' => [$this->projetor->id]])
            ->assertRedirect(route('reservar.enviado', $this->slug));

        $solicitacao = Solicitacao::query()->sole();

        expect($solicitacao->user_id)->toBe($this->dono->id)
            ->and($solicitacao->status)->toBe(StatusSolicitacao::Pendente)
            ->and($solicitacao->telefone)->toBe('11987654321')
            ->and($solicitacao->grupo_id)->toBe($this->grupo->id)
            ->and($solicitacao->espaco_id)->toBe($this->salao->id)
            ->and($solicitacao->pessoas)->toBe(20)
            ->and($solicitacao->equipamentos->first()->pivot->quantidade)->toBe(1);
    });

    it('aceita espaço nulo, que quer dizer qualquer espaço disponível', function () {
        $this->post(route('reservar.store', $this->slug), [...$this->pedido, 'espaco_id' => null])
            ->assertRedirect(route('reservar.enviado', $this->slug));

        expect(Solicitacao::query()->sole()->espaco_id)->toBeNull();
    });

    it('finge sucesso e não grava nada quando o campo honeypot vem preenchido, mesmo com outros dados inválidos', function () {
        $this->post(route('reservar.store', $this->slug), [...$this->pedido, 'site' => 'http://spam.test', 'telefone' => '123'])
            ->assertRedirect(route('reservar.enviado', $this->slug));

        expect(Solicitacao::query()->count())->toBe(0);
    });

    it('responde 429 no sexto envio dentro do mesmo minuto', function () {
        foreach (range(1, 5) as $envio) {
            $this->post(route('reservar.store', $this->slug), $this->pedido)->assertRedirect();
        }

        $this->post(route('reservar.store', $this->slug), $this->pedido)->assertStatus(429);

        expect(Solicitacao::query()->count())->toBe(5);
    });

    it('limita a 20 envios por hora, mesmo quando o minuto muda', function () {
        foreach (range(1, 4) as $lote) {
            foreach (range(1, 5) as $envio) {
                $this->post(route('reservar.store', $this->slug), $this->pedido)->assertRedirect();
            }

            $this->travel(2)->minutes();
        }

        $this->post(route('reservar.store', $this->slug), $this->pedido)->assertStatus(429);

        expect(Solicitacao::query()->count())->toBe(20);
    });
});

describe('slug', function () {
    it('responde 404 para um slug que não existe, sem criar slug para ninguém', function () {
        $this->get(route('reservar.create', 'naoexiste01'))->assertNotFound();
        $this->get(route('reservar.enviado', 'naoexiste01'))->assertNotFound();
        $this->postJson(route('reservar.store', 'naoexiste01'), $this->pedido)->assertNotFound();

        expect(User::query()->whereNotNull('agenda_slug')->count())->toBe(1)
            ->and(Solicitacao::query()->count())->toBe(0);
    });
});

describe('formulário', function () {
    it('mostra a paróquia, os grupos e espaços que aceitam pedidos e os equipamentos fora de manutenção', function () {
        Espaco::factory()->for($this->dono)->create(['nome' => 'Sala fechada', 'aceita_solicitacoes' => false]);
        Espaco::factory()->for($this->dono)->create(['nome' => 'Sala desativada', 'ativo' => false]);
        Grupo::factory()->for($this->dono)->create(['nome' => 'Grupo inativo', 'ativo' => false]);
        Equipamento::factory()->for($this->dono)->emManutencao()->create(['nome' => 'Caixa de som']);
        Equipamento::factory()->for(User::factory())->create(['nome' => 'Equipamento alheio']);

        $this->get(route('reservar.create', $this->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reservar/create')
                ->where('agenda.nome', 'Paróquia São José')
                ->where('agenda.slug', $this->slug)
                ->where('hoje', '2026-10-10')
                ->has('grupos', 1)
                ->where('grupos.0.nome', 'Catequese')
                ->has('espacos', 1)
                ->where('espacos.0.nome', 'Salão paroquial')
                ->has('equipamentos', 1)
                ->where('equipamentos.0.nome', 'Projetor'));
    });

    it('mostra a página de envio com o nome da paróquia', function () {
        $this->get(route('reservar.enviado', $this->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reservar/enviado')
                ->where('agenda.nome', 'Paróquia São José')
                ->where('agenda.slug', $this->slug));
    });
});

describe('validação', function () {
    it('recusa espaço de outro dono', function () {
        $alheio = Espaco::factory()->for(User::factory())->create();

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'espaco_id' => $alheio->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['espaco_id']);

        expect(Solicitacao::query()->count())->toBe(0);
    });

    it('recusa espaço que não aceita solicitações', function () {
        $fechado = Espaco::factory()->for($this->dono)->create(['aceita_solicitacoes' => false]);

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'espaco_id' => $fechado->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['espaco_id']);
    });

    it('recusa espaço desativado', function () {
        $desativado = Espaco::factory()->for($this->dono)->create(['ativo' => false]);

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'espaco_id' => $desativado->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['espaco_id']);
    });

    it('recusa grupo de outro dono', function () {
        $alheio = Grupo::factory()->for(User::factory())->create();

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'grupo_id' => $alheio->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grupo_id']);
    });

    it('recusa a antecedência mínima do espaço, contada no fuso da agenda', function () {
        $this->salao->update(['antecedencia_min_horas' => 24]);

        // Agora é sábado 10/10 10:00; o limite são 24h depois, domingo 11/10 10:00. Às 09:00 falha, às 11:00 passa.
        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'data' => '2026-10-11', 'inicio' => '09:00', 'fim' => '10:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inicio']);

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'data' => '2026-10-11', 'inicio' => '11:00', 'fim' => '12:00'])
            ->assertRedirect();
    });

    it('recusa uma duração acima do máximo do espaço', function () {
        $this->salao->update(['duracao_max_min' => 60]);

        $this->postJson(route('reservar.store', $this->slug), $this->pedido)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim']);

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'fim' => '20:00'])
            ->assertRedirect();
    });

    it('recusa telefone com menos de 10 ou mais de 13 dígitos', function (string $telefone) {
        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'telefone' => $telefone])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone']);
    })->with(['(11) 9876-54', '55 11 98765 432109']);

    it('recusa data no passado', function () {
        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'data' => '2026-10-09'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data']);
    });

    it('recusa fim que não é depois do início', function () {
        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'inicio' => '21:00', 'fim' => '19:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim']);
    });

    it('recusa equipamento em manutenção na data do pedido', function () {
        $emManutencao = Equipamento::factory()->for($this->dono)->emManutencao()->create();

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'equipamentos' => [$emManutencao->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipamentos']);
    });

    it('recusa equipamento de outro dono', function () {
        $alheio = Equipamento::factory()->for(User::factory())->create();

        $this->postJson(route('reservar.store', $this->slug), [...$this->pedido, 'equipamentos' => [$alheio->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipamentos.0']);
    });
});
