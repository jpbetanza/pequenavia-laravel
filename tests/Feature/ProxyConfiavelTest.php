<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('gera URLs https quando o proxy informa X-Forwarded-Proto', function () {
    $resposta = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'certificados.pequenavia.app.br',
        'X-Forwarded-For' => '203.0.113.7',
    ])->get('/login');

    $resposta->assertOk();

    expect(url('/login'))->toStartWith('https://');
});

it('confia no IP encaminhado pelo proxy', function () {
    Route::get('/_ip', fn (Request $request) => $request->ip());

    $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('/_ip')
        ->assertOk()
        ->assertSee('203.0.113.7');
});
