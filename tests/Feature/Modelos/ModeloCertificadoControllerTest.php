<?php

use App\Jobs\RemoverImagemModelo;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Cria um modelo cuja imagem existe no bucket. Chame Storage::fake('s3') antes.
 */
function modeloComImagem(?User $dono = null): ModeloCertificado
{
    $modelo = ModeloCertificado::factory()->for($dono ?? User::factory()->create())->create();
    Storage::disk('s3')->put($modelo->image_path, UploadedFile::fake()->image('modelo.png', 20, 20)->get());

    return $modelo;
}

it('redireciona o visitante para o login', function () {
    $this->get(route('meus-certificados.index'))->assertRedirect(route('login'));
});

it('lista os modelos próprios e, à parte, os compartilhados com o usuário', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $proprio = ModeloCertificado::factory()->for($user)->create(['title' => 'Batismo', 'description' => 'Matriz']);
    $compartilhamento = CompartilhamentoModelo::factory()->for($proprio, 'modelo')->create(['email' => 'joao@example.com']);
    $deOutro = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($deOutro, 'modelo')->editor()->create(['email' => 'ana@example.com']);
    ModeloCertificado::factory()->create();

    $this->actingAs($user)->get(route('meus-certificados.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/index')
            ->has('modelos', 1)
            ->has('modelos.0', fn ($modelo) => $modelo
                ->where('id', $proprio->id)
                ->where('title', 'Batismo')
                ->where('description', 'Matriz')
                ->where('imagem_url', route('modelos.imagem', $proprio))
                ->where('dono_nome', $user->name)
                ->where('eh_dono', true)
                ->where('pode_editar', true)
                ->has('compartilhamentos', 1, fn ($c) => $c
                    ->where('id', $compartilhamento->id)
                    ->where('email', 'joao@example.com')
                    ->where('pode_editar', false)))
            ->has('compartilhados', 1)
            ->has('compartilhados.0', fn ($modelo) => $modelo
                ->where('id', $deOutro->id)
                ->where('eh_dono', false)
                ->where('pode_editar', true)
                ->where('compartilhamentos', [])
                ->etc()));
});

it('cria o modelo do usuário e envia a imagem de 50 MB para o bucket', function () {
    Storage::fake('s3');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('modelos.store'), [
            'title' => 'Batismo',
            'description' => 'Paróquia São José',
            'imagem' => UploadedFile::fake()->image('batismo.png', 2000, 1400)->size(51200),
        ])
        ->assertRedirect(route('meus-certificados.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Modelo criado.']);

    $modelo = ModeloCertificado::sole();
    expect($modelo)
        ->user_id->toBe($user->id)
        ->title->toBe('Batismo')
        ->description->toBe('Paróquia São José')
        ->image_path->toStartWith("modelos/{$user->id}/")->toEndWith('.png');
    Storage::disk('s3')->assertExists($modelo->image_path);
});

it('exige título e imagem para criar o modelo', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('modelos.store'))
        ->assertSessionHasErrors(['title', 'imagem']);

    $this->assertDatabaseCount('modelos_certificado', 0);
});

it('rejeita o upload inválido e não cria o modelo', function (Closure $imagem, string $mensagem) {
    Storage::fake('s3');

    $this->actingAs(User::factory()->create())
        ->post(route('modelos.store'), ['title' => 'Batismo', 'imagem' => $imagem()])
        ->assertSessionHasErrors(['imagem' => $mensagem]);

    $this->assertDatabaseCount('modelos_certificado', 0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
})->with([
    'maior que 50 MB' => [
        fn () => UploadedFile::fake()->image('a.png', 100, 100)->size(51201),
        'A imagem pode ter no máximo 50 MB.',
    ],
    'PDF' => [
        fn () => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        'Envie uma imagem PNG, JPG ou WEBP.',
    ],
    'mais larga que 10000 px' => [
        fn () => UploadedFile::fake()->image('a.png', 10001, 10),
        'A imagem pode ter no máximo 10000 × 10000 pixels.',
    ],
    'mais alta que 10000 px' => [
        fn () => UploadedFile::fake()->image('a.png', 10, 10001),
        'A imagem pode ter no máximo 10000 × 10000 pixels.',
    ],
]);

it('deixa o editor compartilhado renomear o modelo e trocar a imagem, removendo a antiga', function () {
    Storage::fake('s3');
    $modelo = modeloComImagem();
    $imagemAntiga = $modelo->image_path;
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);

    $this->actingAs($editor)
        ->put(route('modelos.update', $modelo), [
            'title' => 'Crisma',
            'imagem' => UploadedFile::fake()->image('crisma.jpg', 800, 600),
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Modelo atualizado.']);

    $modelo->refresh();
    expect($modelo)
        ->title->toBe('Crisma')
        ->image_path->toStartWith("modelos/{$modelo->user_id}/")->toEndWith('.jpg');
    Storage::disk('s3')->assertMissing($imagemAntiga);
    Storage::disk('s3')->assertExists($modelo->image_path);
});

it('mantém a imagem quando a atualização é só do texto', function () {
    Storage::fake('s3');
    $modelo = modeloComImagem();
    $imagem = $modelo->image_path;

    $this->actingAs($modelo->user)
        ->put(route('modelos.update', $modelo), ['title' => 'Novo título', 'description' => 'Nova descrição'])
        ->assertRedirect();

    expect($modelo->fresh())
        ->title->toBe('Novo título')
        ->description->toBe('Nova descrição')
        ->image_path->toBe($imagem);
    Storage::disk('s3')->assertExists($imagem);
});

it('responde 403 ao leitor compartilhado que tenta alterar o modelo', function () {
    $modelo = ModeloCertificado::factory()->create(['title' => 'Batismo']);
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)
        ->put(route('modelos.update', $modelo), ['title' => 'Outro título'])
        ->assertForbidden();

    expect($modelo->fresh()->title)->toBe('Batismo');
});

it('exclui o modelo do dono e despacha a remoção da imagem', function () {
    $modelo = ModeloCertificado::factory()->create();
    Queue::fake([RemoverImagemModelo::class]);

    $this->actingAs($modelo->user)
        ->delete(route('modelos.destroy', $modelo))
        ->assertRedirect(route('meus-certificados.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Modelo excluído.']);

    $this->assertModelMissing($modelo);
    Queue::assertPushed(RemoverImagemModelo::class, fn (RemoverImagemModelo $job) => $job->path === $modelo->image_path);
});

it('responde 403 ao editor compartilhado que tenta excluir o modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);

    $this->actingAs($editor)->delete(route('modelos.destroy', $modelo))->assertForbidden();

    $this->assertModelExists($modelo);
});

it('serve a imagem do bucket a quem pode ver o modelo', function () {
    Storage::fake('s3');
    $modelo = modeloComImagem();

    $this->actingAs($modelo->user)
        ->get(route('modelos.imagem', $modelo))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=3600, private')
        ->assertStreamedContent(Storage::disk('s3')->get($modelo->image_path));
});

it('responde 404 a quem não pode ver o modelo, sem revelar que a imagem existe', function () {
    Storage::fake('s3');
    $modelo = modeloComImagem();

    $this->actingAs(User::factory()->create())
        ->get(route('modelos.imagem', $modelo))
        ->assertNotFound();
});

it('responde 404 quando a imagem do modelo não está no bucket', function () {
    Storage::fake('s3');
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->get(route('modelos.imagem', $modelo))
        ->assertNotFound();
});
