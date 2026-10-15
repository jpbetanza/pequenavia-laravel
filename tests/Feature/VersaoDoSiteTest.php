<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('responde a versão configurada da imagem, sem cache e sem login', function () {
    config(['app.versao' => 'abc123']);

    $this->getJson(route('versao'))
        ->assertOk()
        ->assertExactJson(['versao' => 'abc123'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('sem versão configurada, usa a impressão do manifesto do Vite', function () {
    config(['app.versao' => null]);
    $manifesto = public_path('build/manifest.json');

    $this->getJson(route('versao'))->assertJsonPath('versao', is_file($manifesto) ? md5_file($manifesto) : 'dev');
});

it('as páginas levam a versão com que abriram', function () {
    config(['app.versao' => 'abc123']);

    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $pagina) => $pagina->where('versao', 'abc123'));
});
