<?php

use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;

it('mantém a geração no histórico quando o modelo é excluído', function () {
    $modelo = ModeloCertificado::factory()->create(['title' => 'Crisma 2024']);
    $geracao = GeracaoCertificado::factory()->for($modelo, 'modelo')->create(['modelo_title' => 'Crisma 2024']);

    $modelo->delete();

    $this->assertModelExists($geracao);
    expect($geracao->fresh())
        ->modelo_id->toBeNull()
        ->modelo_title->toBe('Crisma 2024');
});

it('devolve os valores gerados como array, sem garantir a ordem das chaves', function () {
    $geracao = GeracaoCertificado::factory()->create(['campos_valores' => ['Nome' => 'Maria', 'Data' => '01/05/2024']]);

    expect($geracao->fresh()->campos_valores)->toEqual(['Nome' => 'Maria', 'Data' => '01/05/2024']);
});
