<?php

use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use Illuminate\Database\UniqueConstraintViolationException;

it('recusa dois campos com o mesmo nome no mesmo modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);

    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);
})->throws(UniqueConstraintViolationException::class);

it('aceita o mesmo nome de campo em modelos diferentes', function () {
    CampoCertificado::factory()->count(2)->create(['nome' => 'Nome']);

    $this->assertDatabaseCount('campos_certificado', 2);
});
