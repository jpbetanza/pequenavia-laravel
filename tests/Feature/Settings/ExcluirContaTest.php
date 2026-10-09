<?php

use App\Jobs\RemoverImagemModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('exclui a conta com senha, os modelos dela e despacha a remoção das imagens', function () {
    $user = User::factory()->create();
    [$primeiro, $segundo] = ModeloCertificado::factory()->for($user)->count(2)->create();
    $deOutro = ModeloCertificado::factory()->create();
    Queue::fake([RemoverImagemModelo::class]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    $this->assertModelMissing($user);
    $this->assertModelMissing($primeiro);
    $this->assertModelMissing($segundo);
    $this->assertModelExists($deOutro);
    Queue::assertPushed(RemoverImagemModelo::class, 2);
    Queue::assertPushed(RemoverImagemModelo::class, fn (RemoverImagemModelo $job) => $job->path === $primeiro->image_path);
    Queue::assertPushed(RemoverImagemModelo::class, fn (RemoverImagemModelo $job) => $job->path === $segundo->image_path);
});

it('exclui a conta que só tem Google quando o usuário digita o próprio email', function () {
    $user = User::factory()->google()->create(['email' => 'ana@example.com']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirmacao' => '  ANA@example.com '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertModelMissing($user);
});

it('recusa a exclusão da conta só-Google sem a confirmação certa', function (array $dados) {
    $user = User::factory()->google()->create(['email' => 'ana@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), $dados)
        ->assertSessionHasErrors(['confirmacao' => 'Digite o seu email para confirmar.'])
        ->assertRedirect(route('profile.edit'));

    $this->assertModelExists($user);
})->with([
    'email de outra pessoa' => [['confirmacao' => 'outro@example.com']],
    'sem confirmação' => [[]],
]);

it('não aceita o email no lugar da senha de quem tem senha', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['confirmacao' => 'ana@example.com'])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    $this->assertModelExists($user);
});
