<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use Illuminate\Database\UniqueConstraintViolationException;

it('guarda o email em minúsculas e sem espaços nas pontas', function () {
    $compartilhamento = CompartilhamentoModelo::factory()->create(['email' => '  Joao@Example.COM ']);

    expect($compartilhamento->email)->toBe('joao@example.com');
});

it('recusa o mesmo email duas vezes no mesmo modelo, ignorando maiúsculas', function () {
    $modelo = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'Joao@Example.com']);

    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'joao@example.com']);
})->throws(UniqueConstraintViolationException::class);
