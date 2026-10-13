<?php

use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\InscricaoPush;
use App\Models\User;
use App\Services\Push\CanalDePush;
use App\Services\Push\ResultadoDoPush;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Canal de teste: guarda o que seria enviado e responde o que cada teste mandar, sem ir à rede.
 */
class CanalDePushFalso implements CanalDePush
{
    /** @var list<array{endpoints: list<string>, mensagem: array<string, mixed>}> */
    public array $enviados = [];

    /** @var array<string, ResultadoDoPush> resposta por endpoint; o resto é entregue */
    public array $respostas = [];

    public function enviar(array $inscricoes, array $mensagem): array
    {
        $endpoints = array_map(fn (InscricaoPush $inscricao) => $inscricao->endpoint, $inscricoes);
        $this->enviados[] = ['endpoints' => $endpoints, 'mensagem' => $mensagem];

        return array_map(fn (string $endpoint) => $this->respostas[$endpoint] ?? new ResultadoDoPush($endpoint, true), $endpoints);
    }
}

beforeEach(function () {
    config(['push.chave_publica' => 'BPublica', 'push.chave_privada' => 'privada']);
    $this->canal = new CanalDePushFalso;
    $this->app->instance(CanalDePush::class, $this->canal);
});

describe('pedido pelo formulário público', function () {
    beforeEach(function () {
        $this->travelTo(Carbon::parse('2026-10-10 10:00', config('agendamentos.fuso')));
        $this->dono = User::factory()->create();
        $this->salao = Espaco::factory()->comHorarioComercial()->for($this->dono)->create(['nome' => 'Salão paroquial']);
        $this->pedido = [
            'grupo_id' => Grupo::factory()->for($this->dono)->create()->id,
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

    it('avisa os aparelhos da secretaria dona da agenda, e só os dela', function () {
        $celular = InscricaoPush::factory()->for($this->dono)->create();
        $computador = InscricaoPush::factory()->for($this->dono)->create();
        InscricaoPush::factory()->create();

        $this->post(route('reservar.store', $this->dono->agendaSlug()), $this->pedido)->assertRedirect();

        expect($this->canal->enviados)->toHaveCount(1)
            ->and($this->canal->enviados[0]['endpoints'])->toEqualCanonicalizing([$celular->endpoint, $computador->endpoint])
            ->and($this->canal->enviados[0]['mensagem'])->toMatchArray([
                'titulo' => 'Nova solicitação: Ensaio do coral',
                'corpo' => 'Maria Silva · seg 12/10, 19:00–21:00 · Salão paroquial',
                'url' => '/agendamentos/solicitacoes',
            ])
            ->and($this->canal->enviados[0]['mensagem']['tag'])->toStartWith('solicitacao-');
    });

    it('sem aparelhos ou sem as chaves VAPID, não tenta enviar e o pedido segue normal', function () {
        $this->post(route('reservar.store', $this->dono->agendaSlug()), $this->pedido)->assertRedirect();

        config(['push.chave_privada' => null]);
        InscricaoPush::factory()->for($this->dono)->create();
        $this->post(route('reservar.store', $this->dono->agendaSlug()), [...$this->pedido, 'inicio' => '15:00', 'fim' => '16:00'])->assertRedirect();

        expect($this->canal->enviados)->toBe([]);
    });

    it('remove o aparelho que cancelou a inscrição e mantém os outros', function () {
        $cancelado = InscricaoPush::factory()->for($this->dono)->create();
        $ativo = InscricaoPush::factory()->for($this->dono)->create();
        $this->canal->respostas[$cancelado->endpoint] = new ResultadoDoPush($cancelado->endpoint, false, expirada: true);

        $this->post(route('reservar.store', $this->dono->agendaSlug()), $this->pedido)->assertRedirect();

        expect(InscricaoPush::query()->pluck('id')->all())->toBe([$ativo->id]);
    });
});

describe('tela de notificações', function () {
    it('mostra se está configurado, a chave pública e os aparelhos do usuário', function () {
        $usuario = User::factory()->create();
        $aparelho = InscricaoPush::factory()->for($usuario)->create(['dispositivo' => 'Android · Chrome']);
        InscricaoPush::factory()->create();

        $this->actingAs($usuario)
            ->get(route('notificacoes.edit'))
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('settings/notificacoes')
                ->where('configurado', true)
                ->where('chavePublica', 'BPublica')
                ->has('aparelhos', 1)
                ->where('aparelhos.0.id', $aparelho->id)
                ->where('aparelhos.0.dispositivo', 'Android · Chrome'));
    });

    it('guarda a inscrição deste navegador e a passa para quem estiver logado agora', function () {
        $antes = User::factory()->create();
        $agora = User::factory()->create();
        $inscricao = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'chave', 'auth' => 'segredo'],
            'dispositivo' => 'Android · Chrome',
        ];

        $this->actingAs($antes)->postJson(route('notificacoes.inscricoes.store'), $inscricao)->assertNoContent();
        $this->actingAs($agora)->postJson(route('notificacoes.inscricoes.store'), [...$inscricao, 'keys' => ['p256dh' => 'nova', 'auth' => 'novo']])->assertNoContent();

        $guardada = InscricaoPush::query()->sole();

        expect($guardada->user_id)->toBe($agora->id)
            ->and($guardada->chave_p256dh)->toBe('nova')
            ->and($guardada->dispositivo)->toBe('Android · Chrome');
    });

    it('recusa endpoint que não é https e falta de chaves', function () {
        $this->actingAs(User::factory()->create())
            ->postJson(route('notificacoes.inscricoes.store'), ['endpoint' => 'http://exemplo.com/push', 'keys' => []])
            ->assertInvalid(['endpoint', 'keys.p256dh', 'keys.auth']);
    });

    it('sem as chaves VAPID, não aceita inscrição nem teste', function () {
        config(['push.chave_publica' => null]);
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->postJson(route('notificacoes.inscricoes.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'chave', 'auth' => 'segredo'],
        ])->assertServiceUnavailable();
        $this->actingAs($usuario)->postJson(route('notificacoes.teste'))->assertServiceUnavailable();
    });

    it('desativa só a inscrição do próprio usuário', function () {
        $usuario = User::factory()->create();
        $minha = InscricaoPush::factory()->for($usuario)->create();
        $deOutro = InscricaoPush::factory()->create();

        $this->actingAs($usuario)->deleteJson(route('notificacoes.inscricoes.destroy'), ['endpoint' => $minha->endpoint])->assertNoContent();
        $this->actingAs($usuario)->deleteJson(route('notificacoes.inscricoes.destroy'), ['endpoint' => $deOutro->endpoint])->assertNoContent();

        expect(InscricaoPush::query()->pluck('id')->all())->toBe([$deOutro->id]);
    });

    it('manda a notificação de teste para os aparelhos do usuário', function () {
        $usuario = User::factory()->create();
        InscricaoPush::factory()->for($usuario)->count(2)->create();

        $this->actingAs($usuario)->postJson(route('notificacoes.teste'))->assertOk()->assertJson(['entregues' => 2]);

        expect($this->canal->enviados[0]['mensagem'])->toMatchArray(['titulo' => 'Notificações ligadas', 'url' => '/settings/notificacoes', 'tag' => 'teste']);
    });

    it('teste sem nenhum aparelho responde 422', function () {
        $this->actingAs(User::factory()->create())->postJson(route('notificacoes.teste'))->assertUnprocessable();
    });

    it('excluir a conta apaga os aparelhos dela', function () {
        $usuario = User::factory()->create();
        InscricaoPush::factory()->for($usuario)->create();
        $usuario->delete();

        expect(InscricaoPush::count())->toBe(0);
    });
});

it('o comando gera um par de chaves VAPID utilizável', function () {
    $this->artisan('push:gerar-chaves')
        ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
        ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
        ->assertSuccessful();
});
