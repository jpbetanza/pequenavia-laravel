<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;

it('exclui modelos, campos e compartilhamentos junto com o dono', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();
    $compartilhamento = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $modelo->user->delete();

    $this->assertModelMissing($modelo);
    $this->assertModelMissing($campo);
    $this->assertModelMissing($compartilhamento);
});

it('lista os campos na ordem de criação', function () {
    $modelo = ModeloCertificado::factory()->create();
    [$primeiro, $segundo] = CampoCertificado::factory()
        ->for($modelo, 'modelo')
        ->count(2)
        ->sequence(
            ['created_at' => now()->subMinute()],
            ['created_at' => now()],
        )
        ->create();

    expect($modelo->campos->modelKeys())->toBe([$primeiro->id, $segundo->id]);
});
