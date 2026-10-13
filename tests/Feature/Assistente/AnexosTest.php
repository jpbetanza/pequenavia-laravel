<?php

use App\Enums\PapelDaMensagem;
use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Espaco;
use App\Models\Mensagem;
use App\Services\Assistente\Ferramentas\ProporReserva;
use App\Services\Assistente\MontadorDeContexto;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['assistente.chave' => 'chave-de-teste']);
});

/** Corpo da chamada ao OpenRouter: a mensagem do usuário mais recente que foi enviada ao modelo. */
function ultimoPedidoEnviado(): mixed
{
    $enviada = null;

    Http::assertSent(function (Request $request) use (&$enviada) {
        $usuarios = array_values(array_filter($request->data()['messages'], fn (array $m) => $m['role'] === 'user'));
        $enviada = end($usuarios)['content'];

        return true;
    });

    return $enviada;
}

/** PNG de 1×1 pixel. */
const PNG_MINIMO = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

const CSV_DE_ESPACOS = "## Aba: Salas\nNome,Capacidade\nSalão paroquial,120\nSala 3,20";

test('a planilha convertida vai ao modelo como bloco de anexo e fica gravada na mensagem', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Li 2 salas.')]))]);
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), [
            'texto' => 'Cadastre as salas da planilha',
            'anexos' => [['nome' => 'salas.xlsx', 'tipo' => 'planilha', 'conteudo' => CSV_DE_ESPACOS, 'truncado' => '0']],
        ])
        ->assertOk()
        ->streamedContent();

    $pedido = Mensagem::query()->where('papel', PapelDaMensagem::Usuario)->sole();
    $conteudo = ultimoPedidoEnviado();

    expect($pedido->conteudo)->toBe('Cadastre as salas da planilha')
        ->and($pedido->anexos)->toHaveCount(1)
        ->and($pedido->anexos[0])->toMatchArray(['nome' => 'salas.xlsx', 'tipo' => 'planilha', 'texto' => CSV_DE_ESPACOS, 'truncado' => false])
        ->and($conteudo[0])->toBe(['type' => 'text', 'text' => 'Cadastre as salas da planilha'])
        ->and($conteudo[1]['text'])->toBe("<anexo nome=\"salas.xlsx\" tipo=\"planilha\">\n".CSV_DE_ESPACOS."\n</anexo>");
});

test('a mensagem pode ir só com anexos, e o título da conversa vem do arquivo', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Ok')]))]);
    $conversa = Conversa::factory()->create(['titulo' => 'Nova conversa']);

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), [
            'anexos' => [
                ['nome' => 'salas.csv', 'tipo' => 'planilha', 'conteudo' => CSV_DE_ESPACOS],
                ['nome' => 'leia.txt', 'tipo' => 'texto', 'conteudo' => 'Horários de inverno'],
            ],
        ])
        ->assertOk()
        ->streamedContent();

    expect($conversa->fresh()->titulo)->toBe('salas.csv e mais 1')
        ->and(ultimoPedidoEnviado()[0]['text'])->toBe('(Mensagem sem texto, só com anexos.)');
});

test('planilha cortada no navegador avisa o modelo', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Ok')]))]);
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), [
            'texto' => 'Importe',
            'anexos' => [['nome' => 'grande.xlsx', 'tipo' => 'planilha', 'conteudo' => CSV_DE_ESPACOS, 'truncado' => '1']],
        ])
        ->assertOk()
        ->streamedContent();

    expect(ultimoPedidoEnviado()[1]['text'])->toStartWith('<anexo nome="grande.xlsx" tipo="planilha" truncado="sim">');
});

test('imagem e PDF sobem para o bucket da conversa e vão ao modelo como data URL', function () {
    Http::fake(['openrouter.ai/*' => Http::response(sseDoOpenRouter([chunkDeTexto('Vi a foto.')]))]);
    $conversa = Conversa::factory()->create();
    $png = base64_decode(PNG_MINIMO);

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), [
            'texto' => 'O que tem aqui?',
            'anexos' => [
                ['nome' => 'quadro.png', 'tipo' => 'imagem', 'arquivo' => UploadedFile::fake()->createWithContent('quadro.png', $png)],
                ['nome' => 'oficio.pdf', 'tipo' => 'pdf', 'arquivo' => UploadedFile::fake()->createWithContent('oficio.pdf', "%PDF-1.4\n%%EOF\n")],
            ],
        ])
        ->assertOk()
        ->streamedContent();

    $anexos = Mensagem::query()->where('papel', PapelDaMensagem::Usuario)->sole()->anexos;
    $conteudo = ultimoPedidoEnviado();

    expect($anexos[0])->toMatchArray(['nome' => 'quadro.png', 'tipo' => 'imagem', 'mime' => 'image/png', 'tamanho' => strlen($png)])
        ->and($anexos[0]['caminho'])->toBe("assistente/{$conversa->id}/{$anexos[0]['id']}.png")
        ->and(Storage::disk('s3')->get($anexos[0]['caminho']))->toBe($png)
        ->and($anexos[1]['caminho'])->toEndWith('.pdf')
        ->and($conteudo[1])->toBe(['type' => 'text', 'text' => '<anexo nome="quadro.png" tipo="imagem"/>'])
        ->and($conteudo[2])->toBe(['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($png)]])
        ->and($conteudo[4]['type'])->toBe('file')
        ->and($conteudo[4]['file']['filename'])->toBe('oficio.pdf')
        ->and($conteudo[4]['file']['file_data'])->toStartWith('data:application/pdf;base64,');
});

test('anexos inválidos respondem 422 sem chamar o modelo nem gravar nada', function (array $anexos, string $erro) {
    Http::fake();
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)
        ->post(route('assistente.mensagens.store', $conversa), ['texto' => 'Oi', 'anexos' => $anexos], ['Accept' => 'application/json'])
        ->assertInvalid($erro);

    Http::assertNothingSent();
    expect(Mensagem::count())->toBe(0)
        ->and(Storage::disk('s3')->allFiles())->toBe([]);
})->with([
    'mais de 5 arquivos' => [array_fill(0, 6, ['nome' => 'a.txt', 'tipo' => 'texto', 'conteudo' => 'a']), 'anexos'],
    'tipo desconhecido' => [[['nome' => 'a.exe', 'tipo' => 'programa', 'conteudo' => 'a']], 'anexos.0.tipo'],
    'planilha sem conteúdo' => [[['nome' => 'a.xlsx', 'tipo' => 'planilha']], 'anexos.0.conteudo'],
    'texto grande demais' => [[['nome' => 'a.txt', 'tipo' => 'texto', 'conteudo' => str_repeat('a', 60001)]], 'anexos.0.conteudo'],
    'imagem sem arquivo' => [[['nome' => 'a.png', 'tipo' => 'imagem']], 'anexos.0.arquivo'],
    'imagem que não é imagem' => [[['nome' => 'a.png', 'tipo' => 'imagem', 'arquivo' => UploadedFile::fake()->createWithContent('a.txt', 'texto puro')]], 'anexos.0.arquivo'],
    'PDF que é imagem' => [[['nome' => 'a.pdf', 'tipo' => 'pdf', 'arquivo' => UploadedFile::fake()->createWithContent('a.png', base64_decode(PNG_MINIMO))]], 'anexos.0.arquivo'],
    'arquivo maior que 5 MB' => [[['nome' => 'a.pdf', 'tipo' => 'pdf', 'arquivo' => UploadedFile::fake()->create('a.pdf', 5 * 1024 + 1, 'application/pdf')]], 'anexos.0.arquivo'],
]);

test('sem texto e sem anexos continua 422', function () {
    Http::fake();
    $conversa = Conversa::factory()->create();

    $this->actingAs($conversa->user)
        ->postJson(route('assistente.mensagens.store', $conversa), ['texto' => ''])
        ->assertInvalid(['texto' => 'Escreva uma mensagem ou anexe um arquivo.']);
});

test('a conversa aberta mostra os anexos sem o conteúdo', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Usuario,
        'conteudo' => 'Importe',
        'anexos' => [['id' => 'a1', 'nome' => 'salas.xlsx', 'tipo' => 'planilha', 'tamanho' => 54, 'texto' => CSV_DE_ESPACOS, 'truncado' => true]],
    ]);

    $this->actingAs($conversa->user)
        ->getJson(route('assistente.conversas.show', $conversa))
        ->assertOk()
        ->assertJsonPath('mensagens.0.anexos', [['id' => 'a1', 'nome' => 'salas.xlsx', 'tipo' => 'planilha', 'tamanho' => 54, 'truncado' => true]])
        ->assertJsonMissingPath('mensagens.0.anexos.0.texto');
});

test('anexo que sumiu do bucket vai ao modelo como indisponível', function () {
    $conversa = Conversa::factory()->create();
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Usuario,
        'conteudo' => 'Veja',
        'anexos' => [['id' => 'a1', 'nome' => 'foto.jpg', 'tipo' => 'imagem', 'tamanho' => 10, 'caminho' => "assistente/{$conversa->id}/a1.jpg", 'mime' => 'image/jpeg']],
    ]);

    $mensagens = app(MontadorDeContexto::class)->montar($conversa->user, $conversa);

    expect($mensagens[1]['content'][1])->toBe(['type' => 'text', 'text' => '<anexo nome="foto.jpg" tipo="imagem" indisponivel="sim"/>']);
});

test('excluir a conversa apaga os arquivos dela no bucket', function () {
    $conversa = Conversa::factory()->create();
    $outra = Conversa::factory()->for($conversa->user)->create();
    Storage::disk('s3')->put("assistente/{$conversa->id}/a.png", 'x');
    Storage::disk('s3')->put("assistente/{$outra->id}/b.png", 'y');

    $this->actingAs($conversa->user)
        ->deleteJson(route('assistente.conversas.destroy', $conversa))
        ->assertNoContent();

    expect(Storage::disk('s3')->allFiles())->toBe(["assistente/{$outra->id}/b.png"]);
});

test('propostas do mesmo pedido convivem; um pedido novo expira as anteriores com a mesma chave', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00', 'America/Sao_Paulo'));
    $conversa = Conversa::factory()->create();
    $usuario = $conversa->user;
    $salao = Espaco::factory()->comHorarioComercial()->for($usuario)->create(['nome' => 'Salão paroquial']);
    $reserva = fn (string $inicio, string $fim) => app(ProporReserva::class)->executar($usuario, $conversa, [
        'titulo' => 'Catequese', 'espaco' => $salao->id, 'data' => '2026-10-16', 'inicio' => $inicio, 'fim' => $fim,
    ]);

    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Importe a planilha']);
    $reserva('09:00', '10:00');
    $reserva('14:00', '15:00');

    expect(AcaoProposta::where('status', StatusDaAcao::Proposta)->count())->toBe(2);

    $this->travel(1)->minutes();
    Mensagem::factory()->for($conversa)->create(['papel' => PapelDaMensagem::Usuario, 'conteudo' => 'Mude para as 16h']);
    $reserva('16:00', '17:00');

    expect(AcaoProposta::where('status', StatusDaAcao::Expirada)->count())->toBe(2)
        ->and(AcaoProposta::where('status', StatusDaAcao::Proposta)->sole()->dados['inicio'])->toBe('16:00');
});
