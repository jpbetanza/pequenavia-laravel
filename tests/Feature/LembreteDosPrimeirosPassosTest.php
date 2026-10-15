<?php

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Espaco;
use App\Models\InscricaoPush;
use App\Models\Mensagem;
use App\Models\User;
use App\Notifications\ContinuarPrimeirosPassos;
use App\Services\Push\CanalDePush;
use App\Services\Push\ResultadoDoPush;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-16 09:30', 'America/Sao_Paulo'));
    config(['push.chave_publica' => 'BPublica', 'push.chave_privada' => 'privada']);
    Notification::fake();

    // Canal de push que só guarda o que seria enviado.
    $this->push = new class implements CanalDePush
    {
        /** @var list<array<string, mixed>> */
        public array $mensagens = [];

        public function enviar(array $inscricoes, array $mensagem): array
        {
            $this->mensagens[] = $mensagem;

            return array_map(fn (InscricaoPush $inscricao) => new ResultadoDoPush($inscricao->endpoint, true), $inscricoes);
        }
    };
    $this->app->instance(CanalDePush::class, $this->push);
});

/** Secretária que começou o roteiro e mandou a última mensagem ao assistente há tantas horas. */
function secretariaParada(int $horas, array $guardado = []): User
{
    $usuario = User::factory()->create(['primeiros_passos' => [
        'iniciado_em' => now()->subDays(3)->toIso8601String(),
        ...$guardado,
    ]]);
    $conversa = Conversa::factory()->for($usuario)->create();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'created_at' => now()->subHours($horas)]);
    InscricaoPush::factory()->for($usuario)->create();

    return $usuario;
}

it('lembra por push e e-mail quem parou no meio, com o próximo passo', function () {
    $usuario = secretariaParada(24);

    $this->artisan('primeiros-passos:lembrar')->expectsOutputToContain('Lembretes enviados: 1.')->assertSuccessful();

    expect($this->push->mensagens)->toHaveCount(1)
        ->and($this->push->mensagens[0]['titulo'])->toBe('Sua agenda está quase pronta')
        ->and($this->push->mensagens[0]['corpo'])->toContain('Falta cadastrar os espaços');

    Notification::assertSentTo($usuario, ContinuarPrimeirosPassos::class, fn ($aviso) => str_contains($aviso->proximoPasso, 'espaços'));

    $guardado = $usuario->fresh()->primeiros_passos;
    expect($guardado['lembretes'])->toBe(1)->and($guardado)->toHaveKey('lembrado_em');
});

it('fala do passo em que ela parou', function () {
    $usuario = secretariaParada(24);
    Espaco::factory()->for($usuario)->create();

    $this->artisan('primeiros-passos:lembrar')->assertSuccessful();

    expect($this->push->mensagens[0]['corpo'])->toContain('Falta cadastrar os equipamentos');
});

it('não lembra quem está usando agora, quem sumiu há muito tempo, quem adiou, dispensou ou terminou', function () {
    secretariaParada(3);
    secretariaParada(24 * 20, ['iniciado_em' => now()->subDays(25)->toIso8601String()]);
    secretariaParada(24, ['adiado_ate' => now()->addHours(5)->toIso8601String()]);
    secretariaParada(24, ['dispensado_em' => now()->subDay()->toIso8601String()]);
    User::factory()->create(['admin' => true, 'primeiros_passos' => ['iniciado_em' => now()->subDays(3)->toIso8601String()]]);

    $this->artisan('primeiros-passos:lembrar')->expectsOutputToContain('Lembretes enviados: 0.')->assertSuccessful();

    expect($this->push->mensagens)->toBe([]);
    Notification::assertNothingSent();
});

it('manda no máximo dois lembretes, com três dias entre eles', function () {
    $usuario = secretariaParada(24);

    $this->artisan('primeiros-passos:lembrar');
    $this->travel(1)->days();
    $this->artisan('primeiros-passos:lembrar');
    expect($this->push->mensagens)->toHaveCount(1);

    $this->travel(3)->days();
    $this->artisan('primeiros-passos:lembrar');
    expect($this->push->mensagens)->toHaveCount(2);

    $this->travel(4)->days();
    $this->artisan('primeiros-passos:lembrar');
    expect($this->push->mensagens)->toHaveCount(2)
        ->and($usuario->fresh()->primeiros_passos['lembretes'])->toBe(2);
});

it('não manda e-mail para endereço não verificado', function () {
    $usuario = secretariaParada(24);
    $usuario->forceFill(['email_verified_at' => null])->save();

    $this->artisan('primeiros-passos:lembrar');

    Notification::assertNothingSentTo($usuario);
    expect($this->push->mensagens)->toHaveCount(1);
});
