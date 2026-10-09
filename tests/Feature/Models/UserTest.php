<?php

use App\Models\User;

it('usa uuid como id e guarda o email em minúsculas', function () {
    $user = User::factory()->create(['email' => 'Ana.Souza@Example.COM']);

    expect($user->id)->toBeUuid()
        ->and($user->email)->toBe('ana.souza@example.com');
});

it('marca como sem senha quem entra só pelo Google', function () {
    $googleUser = User::factory()->google()->create();
    $passwordUser = User::factory()->create();

    expect($googleUser->password)->toBeNull()
        ->and($googleUser->tem_senha)->toBeFalse()
        ->and($passwordUser->tem_senha)->toBeTrue();
});

it('serializa tem_senha sem expor a senha', function () {
    $user = User::factory()->create();

    expect($user->toArray())->toHaveKey('tem_senha', true)
        ->not->toHaveKey('password');
});
