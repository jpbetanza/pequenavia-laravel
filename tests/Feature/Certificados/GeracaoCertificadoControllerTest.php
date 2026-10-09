<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\Storage;

/**
 * Cria um modelo com fundo branco no bucket falso e um campo "Nome". Chame Storage::fake('s3') antes.
 */
function modeloGeravel(?User $dono = null, string $titulo = 'Batismo'): ModeloCertificado
{
    $modelo = ModeloCertificado::factory()->for($dono ?? User::factory()->create())->create(['title' => $titulo]);
    Storage::disk('s3')->put($modelo->image_path, fundoBranco());
    CampoCertificado::factory()->for($modelo, 'modelo')->create([
        'nome' => 'Nome',
        'fonte_familia' => 'Roboto',
        'peso_fonte' => 600,
        'tamanho_fonte' => 40,
        'x' => 100,
        'y' => 100,
    ]);

    return $modelo;
}

it('gera o PNG do modelo e registra a geração no histórico', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria', 'Outro' => 'ignorado']]);

    $resposta->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($resposta->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    [, , $direita] = caixaDaTinta($resposta->getContent());
    expect($direita)->toBeGreaterThan(100);
    $geracao = $modelo->geracoes()->sole();
    expect($geracao)
        ->user_id->toBe($modelo->user_id)
        ->modelo_title->toBe('Batismo')
        ->and($geracao->campos_valores)->toBe(['Nome' => 'Maria']);
});

it('nomeia o download com o título do modelo e o primeiro valor', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: 'Batismo ção');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    expect($resposta->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('filename="Batismo cao - Maria.png"')
        ->toContain("filename*=utf-8''Batismo%20%C3%A7%C3%A3o%20-%20Maria.png");
});

it('tira do nome do arquivo os caracteres que não podem aparecer nele', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: 'Crisma "2024"/Turma');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    expect($resposta->headers->get('Content-Disposition'))->toContain('filename="Crisma 2024Turma - Maria.png"');
});

it('usa o primeiro valor preenchido no nome do arquivo quando o título não tem caracteres válidos', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: '/');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    expect($resposta->headers->get('Content-Disposition'))->toContain('filename=Maria.png');
});

it('nomeia o arquivo como certificado quando o título e o valor não sobram nada depois de limpos', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: '/');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => '/']]);

    $resposta->assertOk();
    expect($resposta->headers->get('Content-Disposition'))->toContain('filename=certificado.png');
});

it('guarda como texto os valores que chegam como número', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 2024]])
        ->assertOk();

    expect($modelo->geracoes()->sole()->campos_valores)->toBe(['Nome' => '2024']);
});

it('deixa o leitor compartilhado gerar o certificado', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertOk();

    expect($modelo->geracoes()->sole()->user_id)->toBe($leitor->id);
});

it('responde 404 a quem não pode ver o modelo e não registra a geração', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs(User::factory()->create())
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertNotFound();

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('redireciona o visitante para o login', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->post(route('certificados.store', $modelo), ['valores' => []])->assertRedirect(route('login'));
});

it('recusa valores inválidos e não gera nem registra', function (array $dados, string $mensagem) {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), $dados)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['valores' => $mensagem]);

    $this->assertDatabaseCount('geracoes_certificado', 0);
})->with([
    'sem valores' => [[], 'Informe os valores dos campos.'],
    'valores que não é lista' => [['valores' => 'texto'], 'Os valores dos campos devem ser uma lista.'],
    'valor com mais de 500 caracteres' => [['valores' => ['Nome' => str_repeat('a', 501)]], 'Cada valor pode ter no máximo 500 caracteres.'],
    'valor que não é texto' => [['valores' => ['Nome' => ['a']]], 'Cada valor deve ser um texto.'],
    'nenhum valor enviado' => [['valores' => []], 'Preencha ao menos um campo.'],
    'todos os valores em branco' => [['valores' => ['Nome' => '   ']], 'Preencha ao menos um campo.'],
    'valor nulo' => [['valores' => ['Nome' => null]], 'Preencha ao menos um campo.'],
    'só valor de campo que não existe no modelo' => [['valores' => ['Outro' => 'Maria']], 'Preencha ao menos um campo.'],
]);

it('responde 422 quando a imagem do modelo não está no bucket', function () {
    Storage::fake('s3');
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Não foi possível ler a imagem do modelo.']);

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('responde 422 quando o texto é grande demais para ser desenhado', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    $modelo->campos()->update(['tamanho_fonte' => 1000]);

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => str_repeat('W', 500)]])
        ->assertUnprocessable()
        ->assertJson(['message' => 'O texto do campo "Nome" é grande demais para ser desenhado.']);

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('não expõe a mensagem da exceção quando a geração falha', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    config(['app.debug' => false]);
    $this->mock(GeradorCertificado::class, fn ($mock) => $mock
        ->shouldReceive('gerar')
        ->andThrow(new RuntimeException('segredo interno')));

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    $resposta->assertInternalServerError();
    expect($resposta->getContent())->not->toContain('segredo interno');
    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('limita a geração por minuto a cada usuário', function () {
    Storage::fake('s3');
    config(['certificados.geracoes_por_minuto' => 2]);
    $modelo = modeloGeravel();
    $dados = ['valores' => ['Nome' => 'Maria']];

    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertOk();
    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertOk();
    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertTooManyRequests();

    $outro = modeloGeravel();
    $this->actingAs($outro->user)->postJson(route('certificados.store', $outro), $dados)->assertOk();
});

it('aceita campos cujo nome tem ponto', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    $modelo->campos()->update(['nome' => 'Nome.Completo']);

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome.Completo' => 'Maria']]);

    $resposta->assertOk();
    expect($modelo->geracoes()->sole()->campos_valores)->toBe(['Nome.Completo' => 'Maria']);
    [, , $direita] = caixaDaTinta($resposta->getContent());
    expect($direita)->toBeGreaterThan(100);
});

it('mostra a página de geração com o modelo, os campos em ordem e as fontes', function () {
    $modelo = ModeloCertificado::factory()->create(['title' => 'Batismo']);
    [$primeiro, $segundo] = CampoCertificado::factory()
        ->for($modelo, 'modelo')
        ->count(2)
        ->sequence(
            ['nome' => 'Nome', 'created_at' => now()->subMinute()],
            ['nome' => 'Data', 'created_at' => now()],
        )
        ->create();

    $this->actingAs($modelo->user)->get(route('meus-certificados.gerar', $modelo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/gerar')
            ->where('modelo.id', $modelo->id)
            ->where('modelo.title', 'Batismo')
            ->where('modelo.imagem_url', route('modelos.imagem', $modelo))
            ->has('campos', 2)
            ->where('campos.0.id', $primeiro->id)
            ->where('campos.1.id', $segundo->id)
            ->where('fontes.Sistema.Georgia', 'gelasio')
            ->where('pesos_das_fontes.lato', [100, 300, 400, 700, 900]));
});

it('mostra a página de geração ao leitor compartilhado', function () {
    $modelo = ModeloCertificado::factory()->create();
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)->get(route('meus-certificados.gerar', $modelo))->assertOk();
});

it('responde 404 na página de geração a quem não pode ver o modelo', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('meus-certificados.gerar', $modelo))
        ->assertNotFound();
});

it('redireciona o visitante da página de geração para o login', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->get(route('meus-certificados.gerar', $modelo))->assertRedirect(route('login'));
});
