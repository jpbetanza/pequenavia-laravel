<?php

use App\Models\User;

it('renderiza a raiz HTML sem classe dark mesmo com o cookie appearance=dark', function () {
    $resposta = $this->withCookie('appearance', 'dark')->get('/');

    $resposta->assertOk();

    preg_match('/<html[^>]*>/', $resposta->getContent(), $tag);

    expect($tag)->toHaveCount(1)
        ->and($tag[0])->not->toContain('dark');
});

it('declara o site como somente claro para controles nativos', function () {
    $html = $this->get('/')->getContent();

    expect($html)
        ->toContain('<meta name="color-scheme" content="light">')
        ->not->toContain('prefers-color-scheme');
});

it('não possui mais a rota de aparência nas configurações', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertNotFound();
});
