<?php

it('serve o arquivo da fonte sem exigir login, com cache longo', function () {
    $resposta = $this->get(route('fontes.show', ['pasta' => 'roboto', 'peso' => 600]));

    $resposta->assertOk()->assertHeader('Content-Type', 'font/ttf');
    expect($resposta->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
    $this->assertFileEquals(resource_path('fonts/roboto/600.ttf'), $resposta->baseResponse->getFile()->getPathname());
});

it('responde 404 para fonte fora do catálogo, peso inexistente ou caminho forjado', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'pasta fora do catálogo' => ['/fontes/inexistente/400.ttf'],
    'peso que a família não tem' => ['/fontes/lato/600.ttf'],
    'peso fora da escala' => ['/fontes/roboto/650.ttf'],
    'subida de diretório' => ['/fontes/%2e%2e/400.ttf'],
    'barra codificada' => ['/fontes/roboto%2F..%2Froboto/600.ttf'],
    'extensão errada' => ['/fontes/roboto/600.woff'],
]);
