<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

/**
 * Faz o Socialite devolver a conta Google indicada.
 *
 * @param  array<string, string>  $atributos
 */
function googleDevolve(array $atributos = [], bool $emailVerificado = true): void
{
    $googleUser = (new GoogleUser)
        ->map(array_merge([
            'id' => 'google-123',
            'name' => 'Ana Souza',
            'email' => 'Ana@Example.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/ana',
        ], $atributos))
        ->setRaw(['email_verified' => $emailVerificado]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redireciona para o Google', function () {
    config(['services.google.client_id' => 'id-de-teste', 'services.google.client_secret' => 'segredo-de-teste']);

    $this->get(route('auth.google'))->assertRedirectContains('accounts.google.com');
});

it('cria o usuário novo já verificado e sem senha', function () {
    googleDevolve();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $user = User::sole();
    expect($user)
        ->email->toBe('ana@example.com')
        ->google_id->toBe('google-123')
        ->avatar_url->toBe('https://lh3.googleusercontent.com/a/ana')
        ->password->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('vincula ao usuário verificado que já existe e mantém a senha dele', function () {
    $existente = User::factory()->create(['email' => 'ana@example.com']);
    googleDevolve();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertDatabaseCount('users', 1);
    expect($existente->fresh())
        ->google_id->toBe('google-123')
        ->tem_senha->toBeTrue();
    $this->assertAuthenticatedAs($existente);
});

it('vincula ao usuário que nunca verificou o email, remove a senha dele e encerra as sessões abertas', function () {
    $existente = User::factory()->unverified()->create(['email' => 'ana@example.com']);
    DB::table('sessions')->insert([
        'id' => 'sessao-do-cadastro-sem-verificar',
        'user_id' => $existente->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);
    googleDevolve();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    expect($existente->fresh())
        ->google_id->toBe('google-123')
        ->tem_senha->toBeFalse()
        ->and($existente->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'sessao-do-cadastro-sem-verificar']);
    $this->assertAuthenticatedAs($existente);
});

it('entra pelo google_id mesmo quando o email do Google mudou', function () {
    $existente = User::factory()->google()->create(['google_id' => 'google-123', 'email' => 'antigo@example.com']);
    googleDevolve(['email' => 'novo@example.com']);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($existente);
    expect($existente->fresh()->email)->toBe('antigo@example.com');
});

it('recusa entrar quando o email já está vinculado a outra conta Google', function () {
    $existente = User::factory()->google()->create(['google_id' => 'google-antigo', 'email' => 'ana@example.com']);
    googleDevolve(['id' => 'google-novo']);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Este email já está vinculado a outra conta Google.']);

    $this->assertGuest();
    expect($existente->fresh()->google_id)->toBe('google-antigo');
});

it('recusa o email que o Google não confirmou e não cria usuário', function () {
    googleDevolve(emailVerificado: false);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'O Google não confirmou o seu email. Confirme-o na sua conta Google e tente novamente.']);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

it('volta ao login com aviso quando o Google cancela ou o state é inválido', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Não foi possível entrar com o Google. Tente novamente.']);

    $this->assertGuest();
});
