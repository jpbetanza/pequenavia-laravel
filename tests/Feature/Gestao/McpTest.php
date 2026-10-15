<?php

use App\Console\Commands\GestaoChavesOauth;
use App\Enums\SituacaoDoTema;
use App\Mcp\Prompts\AnalisarMelhorias;
use App\Mcp\Resources\ManualDaGestao as RecursoDoManual;
use App\Mcp\Servers\GestaoServer;
use App\Mcp\Tools\Gestao\AtualizarTema;
use App\Mcp\Tools\Gestao\CorrigirClassificacao;
use App\Mcp\Tools\Gestao\JuntarTemas;
use App\Mcp\Tools\Gestao\ListarClientes;
use App\Mcp\Tools\Gestao\ListarConversas;
use App\Mcp\Tools\Gestao\ListarTemas;
use App\Mcp\Tools\Gestao\ManualDaGestao;
use App\Mcp\Tools\Gestao\ResumoDoPainel;
use App\Mcp\Tools\Gestao\VerCliente;
use App\Mcp\Tools\Gestao\VerConversa;
use App\Models\AcessoAConversa;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\TemaDePedido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));

    // O guard do Passport precisa de chaves; uma par pequena, gerada uma vez por execução.
    static $chave = null;
    $chave ??= RSA::createKey(2048);
    config(['passport.private_key' => (string) $chave, 'passport.public_key' => (string) $chave->getPublicKey()]);
});

function gestorDoMcp(): User
{
    return User::factory()->create(['admin' => true, 'name' => 'Gestora Lia']);
}

/** Conversa classificada agora, do dono indicado. */
function conversaDoMcp(User $dono, array $atributos = []): ClassificacaoDeConversa
{
    $conversa = Conversa::factory()->for($dono)->create(['titulo' => 'Salão no sábado']);

    return ClassificacaoDeConversa::factory()->for($conversa)->create(array_merge([
        'user_id' => $dono->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
    ], $atributos));
}

it('pede login por OAuth em /mcp/gestao e anuncia o servidor de autorização', function () {
    $this->postJson('/mcp/gestao', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');

    $this->getJson('/.well-known/oauth-protected-resource/mcp/gestao')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp/gestao'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'));
});

it('recusa token de quem não é gestor', function () {
    Passport::actingAs(User::factory()->create(['admin' => false]), ['mcp:use'], 'api');

    $this->postJson('/mcp/gestao', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertForbidden();
});

it('aceita o registro de clientes só com retorno para o Claude ou localhost', function () {
    $this->postJson(route('mcp.oauth.register'), [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated();

    $this->postJson(route('mcp.oauth.register'), [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://localhost:43123/callback'],
    ])->assertCreated();

    $this->postJson(route('mcp.oauth.register'), [
        'client_name' => 'Outro',
        'redirect_uris' => ['https://malicioso.example/callback'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
});

it('só mostra as ferramentas para gestores', function () {
    GestaoServer::actingAs(gestorDoMcp())->tools()->assertRegistered([
        ResumoDoPainel::class, ListarConversas::class, VerConversa::class, ListarTemas::class,
        ListarClientes::class, VerCliente::class, CorrigirClassificacao::class, AtualizarTema::class, JuntarTemas::class,
    ]);

    GestaoServer::actingAs(User::factory()->create(['admin' => false]))->tools()->assertNotRegistered([
        ResumoDoPainel::class, CorrigirClassificacao::class,
    ]);
});

it('entrega o manual como ferramenta e recurso, e o roteiro de análise', function () {
    $gestor = gestorDoMcp();

    GestaoServer::actingAs($gestor)->tool(ManualDaGestao::class)
        ->assertOk()
        ->assertSee(['Glossário dos campos', 'Receitas', 'frustracao']);

    GestaoServer::actingAs($gestor)->resource(RecursoDoManual::class)
        ->assertOk()
        ->assertSee('Manual do MCP da gestão');

    GestaoServer::actingAs($gestor)->prompt(AnalisarMelhorias::class, ['periodo' => '7', 'foco' => 'certificados'])
        ->assertOk()
        ->assertSee(['últimos 7 dias', 'Foco desta análise: certificados.']);

    GestaoServer::actingAs(User::factory()->create(['admin' => false]))->resources()->assertNotRegistered(RecursoDoManual::class);
    GestaoServer::actingAs(User::factory()->create(['admin' => false]))->prompts()->assertNotRegistered(AnalisarMelhorias::class);
});

it('resume o painel e deixa as contas de gestão de fora por padrão', function () {
    $gestor = gestorDoMcp();
    conversaDoMcp($gestor);
    conversaDoMcp(User::factory()->create(['admin' => false]));

    GestaoServer::actingAs($gestor)->tool(ResumoDoPainel::class, ['periodo' => 7])
        ->assertOk()
        ->assertSee(['"periodo":7', '"conversas":1']);

    GestaoServer::actingAs($gestor)->tool(ResumoDoPainel::class, ['periodo' => 7, 'incluir_gestores' => true])
        ->assertOk()
        ->assertSee('"conversas":2');
});

it('lista conversas com filtros e abre uma registrando o acesso', function () {
    $gestor = gestorDoMcp();
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    $classificacao = conversaDoMcp($ana, ['caso_de_uso' => 'reserva']);
    conversaDoMcp($ana, ['caso_de_uso' => 'certificados']);

    GestaoServer::actingAs($gestor)->tool(ListarConversas::class, ['caso' => 'reserva'])
        ->assertOk()
        ->assertSee(['"total":1', $classificacao->conversa_id]);

    GestaoServer::actingAs($gestor)->tool(VerConversa::class, ['conversa_id' => $classificacao->conversa_id])
        ->assertOk()
        ->assertSee($classificacao->conversa_id);

    expect(AcessoAConversa::query()->where('conversa_id', $classificacao->conversa_id)->where('user_id', $gestor->id)->exists())->toBeTrue();
});

it('não abre a conversa de um gestor sem incluir_gestores', function () {
    $gestor = gestorDoMcp();
    $minha = conversaDoMcp($gestor);

    GestaoServer::actingAs($gestor)->tool(VerConversa::class, ['conversa_id' => $minha->conversa_id])
        ->assertHasErrors(['Conversa não encontrada']);

    GestaoServer::actingAs($gestor)->tool(VerConversa::class, ['conversa_id' => $minha->conversa_id, 'incluir_gestores' => true])
        ->assertOk();
});

it('mostra temas, clientes e o detalhe de um cliente', function () {
    $gestor = gestorDoMcp();
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    $tema = TemaDePedido::factory()->create(['nome' => 'Agenda de missas']);
    conversaDoMcp($ana, ['tema_id' => $tema->id]);

    GestaoServer::actingAs($gestor)->tool(ListarTemas::class, ['tema_id' => $tema->id])
        ->assertOk()
        ->assertSee('Agenda de missas');

    GestaoServer::actingAs($gestor)->tool(ListarClientes::class, ['busca' => 'Ana'])
        ->assertOk()
        ->assertSee($ana->id);

    GestaoServer::actingAs($gestor)->tool(VerCliente::class, ['cliente_id' => $ana->id])
        ->assertOk()
        ->assertSee('linha_do_tempo');

    GestaoServer::actingAs($gestor)->tool(VerCliente::class, ['cliente_id' => $gestor->id])
        ->assertHasErrors(['Cliente não encontrado']);
});

it('corrige a classificação e tira da fila de revisão', function () {
    $classificacao = conversaDoMcp(User::factory()->create(['admin' => false]), ['caso_de_uso' => 'reserva', 'a_revisar' => true]);

    GestaoServer::actingAs(gestorDoMcp())->tool(CorrigirClassificacao::class, [
        'conversa_id' => $classificacao->conversa_id,
        'caso_de_uso' => 'certificados',
    ])->assertOk()->assertSee('"caso_de_uso":"certificados"');

    $classificacao->refresh();
    expect($classificacao->correcao)->toBe(['caso_de_uso' => 'certificados'])
        ->and($classificacao->a_revisar)->toBeFalse();
});

it('recusa correção sem campos ou com valor fora da lista', function () {
    $classificacao = conversaDoMcp(User::factory()->create(['admin' => false]));

    GestaoServer::actingAs(gestorDoMcp())->tool(CorrigirClassificacao::class, ['conversa_id' => $classificacao->conversa_id])
        ->assertHasErrors(['Informe ao menos um campo']);

    GestaoServer::actingAs(gestorDoMcp())->tool(CorrigirClassificacao::class, [
        'conversa_id' => $classificacao->conversa_id,
        'caso_de_uso' => 'inventado',
    ])->assertHasErrors(['Escolha um caso de uso da lista.']);
});

it('atualiza e junta temas', function () {
    $origem = TemaDePedido::factory()->create(['nome' => 'Missas']);
    $destino = TemaDePedido::factory()->create(['nome' => 'Agenda de missas']);
    $gestor = gestorDoMcp();

    GestaoServer::actingAs($gestor)->tool(AtualizarTema::class, ['tema_id' => $destino->id, 'situacao' => 'planejado'])
        ->assertOk();
    expect($destino->refresh()->situacao)->toBe(SituacaoDoTema::Planejado);

    GestaoServer::actingAs($gestor)->tool(AtualizarTema::class, ['tema_id' => $origem->id, 'nome' => 'Agenda de missas'])
        ->assertHasErrors(['Já existe um tema com esse nome.']);

    GestaoServer::actingAs($gestor)->tool(JuntarTemas::class, ['tema_id' => $origem->id, 'destino_id' => $destino->id])
        ->assertOk()
        ->assertSee('juntado em');
    expect($origem->refresh()->juntado_em_id)->toBe($destino->id);

    GestaoServer::actingAs($gestor)->tool(JuntarTemas::class, ['tema_id' => $origem->id, 'destino_id' => $destino->id])
        ->assertHasErrors(['Este tema já foi juntado a outro.']);
});

it('gera as chaves do OAuth uma vez e recupera a cópia guardada depois', function () {
    Storage::fake('s3');
    config(['gestao.disco_das_chaves_oauth' => 's3', 'passport.private_key' => null, 'passport.public_key' => null]);
    $pasta = sys_get_temp_dir().'/chaves-'.uniqid();
    mkdir($pasta);
    Passport::loadKeysFrom($pasta);

    $this->artisan('gestao:chaves-oauth')->assertSuccessful();

    $privada = file_get_contents("{$pasta}/oauth-private.key");
    expect($privada)->toContain('PRIVATE KEY');
    Storage::disk('s3')->assertExists(GestaoChavesOauth::CAMINHO);
    expect(Storage::disk('s3')->get(GestaoChavesOauth::CAMINHO))->not->toContain('PRIVATE KEY')
        ->and(Crypt::decryptString(Storage::disk('s3')->get(GestaoChavesOauth::CAMINHO)))->toContain('PRIVATE KEY');

    // Num contêiner novo, sem os arquivos, as mesmas chaves voltam.
    unlink("{$pasta}/oauth-private.key");
    unlink("{$pasta}/oauth-public.key");
    $this->artisan('gestao:chaves-oauth')->expectsOutputToContain('recuperadas')->assertSuccessful();
    expect(file_get_contents("{$pasta}/oauth-private.key"))->toBe($privada);

    Passport::$keyPath = null;
});

it('mostra a tela de autorização em português e só deixa gestores autorizarem', function () {
    $cliente = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Claude',
        redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
        confidential: false,
    );

    $parametros = [
        'client_id' => $cliente->id,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ];

    $this->get('/oauth/authorize?'.http_build_query($parametros))->assertRedirect(route('login'));

    $this->actingAs(gestorDoMcp())
        ->get('/oauth/authorize?'.http_build_query($parametros))
        ->assertOk()
        ->assertSee('Conectar Claude')
        ->assertSee('Autorizar');

    $this->actingAs(User::factory()->create(['admin' => false]))
        ->get('/oauth/authorize?'.http_build_query($parametros))
        ->assertOk()
        ->assertSee('não tem acesso')
        ->assertDontSee('Autorizar');
});
