<?php

use App\Jobs\RemoverImagemModelo;
use Illuminate\Support\Facades\Storage;

it('remove o objeto da imagem do bucket', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('modelos/dono/imagem.png', 'conteudo');

    (new RemoverImagemModelo('modelos/dono/imagem.png'))->handle();

    Storage::disk('s3')->assertMissing('modelos/dono/imagem.png');
});
