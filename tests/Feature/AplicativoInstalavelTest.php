<?php

it('liga o manifesto do aplicativo e a cor do tema em toda página', function () {
    $html = $this->get('/')->getContent();

    expect($html)
        ->toContain('<link rel="manifest" href="/manifest.webmanifest">')
        ->toContain('<meta name="theme-color" content="#f5f0e6">')
        ->toContain('<meta name="apple-mobile-web-app-title" content="PequenaVia">');
});

it('o manifesto abre na tela inicial, em tela cheia, com os ícones que existem', function () {
    $manifesto = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifesto)->toMatchArray(['name' => 'PequenaVia', 'start_url' => '/inicio', 'display' => 'standalone', 'lang' => 'pt-BR'])
        ->and(collect($manifesto['icons'])->pluck('purpose')->all())->toContain('any', 'maskable');

    foreach ($manifesto['icons'] as $icone) {
        [$largura, $altura] = getimagesize(public_path(ltrim($icone['src'], '/')));

        expect("{$largura}x{$altura}")->toBe($icone['sizes']);
    }
});

it('o service worker e a página sem internet estão publicados', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(file_get_contents(public_path('sw.js')))->toContain("'/offline.html'")
        ->and(public_path('offline.html'))->toBeFile();
});
