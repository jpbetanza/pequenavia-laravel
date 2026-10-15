<?php

use App\Enums\CasoDeUso;
use App\Enums\PapelDaMensagem;
use App\Enums\ResultadoDaConversa;
use App\Models\AcessoAConversa;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\Grupo;
use App\Models\Mensagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // As páginas React ainda não existem nesta parte; o teste confere só o componente e as props.
    config(['inertia.testing.ensure_pages_exist' => false]);

    // 17/10/2026 15h30 em São Paulo.
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

function gestorDasConversas(): User
{
    $gestor = User::factory()->create(['name' => 'Gestora Lia']);
    $gestor->forceFill(['admin' => true])->save();

    return $gestor;
}

/** Conversa do cliente com classificação. Os atributos sobrescrevem os padrões da classificação. */
function conversaClassificada(User $cliente, array $atributos = [], array $conversa = []): ClassificacaoDeConversa
{
    $conversa = Conversa::factory()->for($cliente)->create(array_merge(['titulo' => 'Salão no sábado'], $conversa));

    return ClassificacaoDeConversa::factory()->for($conversa)->create(array_merge([
        'user_id' => $cliente->id,
        'classificada_em' => now(),
        'mensagens_ate' => now(),
    ], $atributos));
}

it('lista as conversas classificadas do período, 25 por página, mantendo os filtros na URL', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);

    foreach (range(1, 30) as $i) {
        conversaClassificada($ana, ['classificada_em' => now()->subMinutes($i)]);
    }

    // Fora do período não entra.
    conversaClassificada($ana, ['classificada_em' => now()->subDays(40)]);

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['periodo' => 30, 'caso' => 'reserva']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gestao/conversas')
            ->where('periodo', 30)
            ->where('filtros.caso', 'reserva')
            ->where('filtros.frustrada', false)
            ->where('conversas.total', 30)
            ->where('conversas.per_page', 25)
            ->has('conversas.data', 25)
            ->where('conversas.data.0.caso', 'reserva')
            ->where('conversas.data.0.cliente', 'Ana')
            ->where('selecionada', null));

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['periodo' => 30, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversas.data', 5)
            ->where('conversas.current_page', 2));
});

it('filtra por caso, resultado, frustração, fila de revisão e cliente', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana']);
    $bruno = User::factory()->create(['admin' => false, 'name' => 'Bruno']);

    conversaClassificada($ana, ['caso_de_uso' => 'reserva', 'resultado' => 'resolvida', 'frustracao' => 0.5]);
    conversaClassificada($ana, ['caso_de_uso' => 'cadastro', 'resultado' => 'nao_resolvida', 'frustracao' => 2.5, 'a_revisar' => true]);
    conversaClassificada($bruno, ['caso_de_uso' => 'reserva', 'resultado' => 'nao_resolvida', 'frustracao' => 2.0]);

    $gestor = gestorDasConversas();

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['resultado' => 'nao_resolvida']))
        ->assertInertia(fn (Assert $page) => $page->where('conversas.total', 2));

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['frustrada' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.frustrada', true)
            ->where('conversas.total', 2));

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['a_revisar' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.a_revisar', true)
            ->where('conversas.total', 1)
            ->where('conversas.data.0.a_revisar', true));

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['cliente' => $bruno->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.cliente', $bruno->id)
            ->where('conversas.total', 1)
            ->where('conversas.data.0.cliente', 'Bruno'));

    // Filtro que não existe na lista não quebra a página: vira "sem filtro".
    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['caso' => 'inventado', 'cliente' => 'nao-e-uuid']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.caso', null)
            ->where('filtros.cliente', null)
            ->where('conversas.total', 3));
});

it('usa a correção do gestor nos filtros e na lista', function () {
    $ana = User::factory()->create(['admin' => false]);

    conversaClassificada($ana, [
        'caso_de_uso' => 'duvida_app',
        'resultado' => 'nao_resolvida',
        'correcao' => ['caso_de_uso' => 'reserva', 'resultado' => 'resolvida'],
    ]);

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['caso' => 'reserva', 'resultado' => 'resolvida']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversas.total', 1)
            ->where('conversas.data.0.caso', 'reserva')
            ->where('conversas.data.0.resultado', 'resolvida'));
});

it('conta a matriz de casos com a correção e compara com o período anterior', function () {
    $ana = User::factory()->create(['admin' => false]);

    // Dois de reserva no período: um resolvido por correção (o JEV dizia não resolvida) e um abandonado.
    conversaClassificada($ana, [
        'caso_de_uso' => 'reserva',
        'resultado' => 'nao_resolvida',
        'correcao' => ['resultado' => 'resolvida'],
    ]);
    conversaClassificada($ana, ['caso_de_uso' => 'reserva', 'resultado' => 'nao_resolvida', 'abandonada' => true]);

    // Um de reserva no período anterior de mesmo tamanho (7 dias antes do início).
    conversaClassificada($ana, ['caso_de_uso' => 'reserva', 'classificada_em' => now()->subDays(10)]);

    // Cadastro só no período atual: tendência nula, porque não havia nada antes.
    conversaClassificada($ana, ['caso_de_uso' => 'cadastro', 'resultado' => 'parcial']);

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['periodo' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('matriz', 2)
            ->where('matriz.0.caso', 'reserva')
            ->where('matriz.0.rotulo', CasoDeUso::Reserva->rotulo())
            ->where('matriz.0.total', 2)
            ->where('matriz.0.resolvida', 1)
            ->where('matriz.0.parcial', 0)
            ->where('matriz.0.nao_resolvida', 1)
            ->where('matriz.0.abandonada', 1)
            ->where('matriz.0.tendencia_pct', 100)
            ->where('matriz.1.caso', 'cadastro')
            ->where('matriz.1.parcial', 1)
            ->where('matriz.1.tendencia_pct', null));
});

it('mostra o detalhe com rótulos efetivos, mensagens mascaradas e registra o acesso', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana Souza']);
    $gestor = gestorDasConversas();

    $classificacao = conversaClassificada($ana, [
        'caso_de_uso' => 'duvida_app',
        'caso_confianca' => 0.7,
        'resultado' => 'nao_resolvida',
        'resultado_confianca' => 0.9,
        'frustracao' => 2.5,
        'confusa' => 0.8,
        'erro_do_assistente' => 0.1,
        'tema_id' => null,
        'resumo' => 'Queria reservar o salão.',
        'ferramentas' => ['consultar_agenda'],
        'tokens_assistente_entrada' => 1000,
        'tokens_assistente_saida' => 200,
        'correcao' => ['caso_de_uso' => 'reserva'],
    ], ['titulo' => 'Reserva do salão']);

    $conversa = $classificacao->conversa;

    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Usuario,
        'conteudo' => 'Oi, sou a Ana Souza, meu e-mail é ana@paroquia.test',
        'created_at' => now()->subMinutes(5),
    ]);
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Ferramenta,
        'conteudo' => '{"ok":true}',
        'created_at' => now()->subMinutes(4),
    ]);
    Mensagem::factory()->for($conversa)->create([
        'papel' => PapelDaMensagem::Assistente,
        'conteudo' => 'Pronto, segue o link.',
        'created_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($gestor)
        ->get(route('gestao.conversas.index', ['conversa' => $conversa->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selecionada.id', $conversa->id)
            ->where('selecionada.titulo', 'Reserva do salão')
            ->where('selecionada.cliente.nome', 'Ana Souza')
            ->where('selecionada.caso.valor', 'reserva')
            ->where('selecionada.caso.rotulo', CasoDeUso::Reserva->rotulo())
            ->where('selecionada.caso.confianca', null)
            ->where('selecionada.resultado.valor', 'nao_resolvida')
            ->where('selecionada.resultado.confianca', 0.9)
            ->where('selecionada.frustracao.nota', 2.5)
            ->where('selecionada.confusa', true)
            ->where('selecionada.erro_do_assistente', false)
            ->where('selecionada.tema', null)
            ->where('selecionada.resumo', 'Queria reservar o salão.')
            ->where('selecionada.ferramentas', ['consultar_agenda'])
            ->where('selecionada.tokens.entrada', 1000)
            ->where('selecionada.tokens.saida', 200)
            ->where('selecionada.corrigida', true)
            ->where('selecionada.a_revisar', false)
            ->has('selecionada.mensagens', 2)
            ->where('selecionada.mensagens.0.de', 'secretaria')
            ->where('selecionada.mensagens.0.texto', 'Oi, sou a [nome] [nome], meu e-mail é [e-mail]')
            ->where('selecionada.mensagens.1.de', 'assistente')
            ->where('selecionada.mensagens.1.texto', 'Pronto, segue o link.'));

    expect(AcessoAConversa::query()
        ->where('user_id', $gestor->id)
        ->where('conversa_id', $conversa->id)
        ->count())->toBe(1);

    // Abrir de novo registra outro acesso.
    $this->actingAs($gestor)->get(route('gestao.conversas.index', ['conversa' => $conversa->id]))->assertOk();

    expect(AcessoAConversa::query()->where('conversa_id', $conversa->id)->count())->toBe(2);
});

it('abre a conversa mesmo sem classificação, com os rótulos vazios', function () {
    $ana = User::factory()->create(['admin' => false]);
    $conversa = Conversa::factory()->for($ana)->create(['titulo' => 'Ainda sem classificar']);

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['conversa' => $conversa->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selecionada.titulo', 'Ainda sem classificar')
            ->where('selecionada.caso', null)
            ->where('selecionada.resultado', null)
            ->where('selecionada.corrigida', false)
            ->where('selecionada.a_revisar', false));
});

it('responde 404 para conversa de gestor e para id que não existe', function () {
    $outroGestor = User::factory()->create(['admin' => true]);
    $conversaDoGestor = Conversa::factory()->for($outroGestor)->create();

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['conversa' => $conversaDoGestor->id]))
        ->assertNotFound();

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['conversa' => 'nao-e-uuid']))
        ->assertNotFound();
});

it('corrige caso e resultado, mantendo o que já tinha corrigido, e tira a conversa da fila de revisão', function () {
    $ana = User::factory()->create(['admin' => false]);
    $classificacao = conversaClassificada($ana, ['caso_de_uso' => 'outro', 'resultado' => 'parcial', 'a_revisar' => true]);
    $gestor = gestorDasConversas();

    $this->actingAs($gestor)
        ->patch(route('gestao.conversas.corrigir', $classificacao->conversa), ['caso_de_uso' => 'reserva'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($gestor)
        ->patch(route('gestao.conversas.corrigir', $classificacao->conversa), ['resultado' => 'resolvida'])
        ->assertRedirect();

    $classificacao->refresh();

    expect($classificacao->correcao)->toEqualCanonicalizing(['caso_de_uso' => 'reserva', 'resultado' => 'resolvida'])
        ->and($classificacao->a_revisar)->toBeFalse()
        ->and($classificacao->casoEfetivo())->toBe(CasoDeUso::Reserva)
        ->and($classificacao->resultadoEfetivo())->toBe(ResultadoDaConversa::Resolvida)
        ->and($classificacao->caso_de_uso)->toBe('outro');
});

it('aceita nulo em caso ou resultado para voltar ao valor do JEV, e nulo em tema para nenhum tema', function () {
    $ana = User::factory()->create(['admin' => false]);
    $classificacao = conversaClassificada($ana, [
        'caso_de_uso' => 'outro',
        'correcao' => ['caso_de_uso' => 'reserva', 'resultado' => 'resolvida'],
    ]);

    $this->actingAs(gestorDasConversas())
        ->patch(route('gestao.conversas.corrigir', $classificacao->conversa), ['caso_de_uso' => null, 'tema_id' => null])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $classificacao->refresh();

    expect($classificacao->casoEfetivo())->toBe(CasoDeUso::Outro)
        ->and($classificacao->resultadoEfetivo())->toBe(ResultadoDaConversa::Resolvida)
        ->and(array_key_exists('tema_id', $classificacao->correcao))->toBeTrue()
        ->and($classificacao->temaEfetivoId())->toBeNull();
});

it('valida a correção: enum, tema que existe e pelo menos um campo', function () {
    $ana = User::factory()->create(['admin' => false]);
    $classificacao = conversaClassificada($ana);
    $gestor = gestorDasConversas();
    $url = route('gestao.conversas.corrigir', $classificacao->conversa);

    $this->actingAs($gestor)->patch($url, ['caso_de_uso' => 'inventado'])->assertSessionHasErrors('caso_de_uso');
    $this->actingAs($gestor)->patch($url, ['resultado' => 'quase'])->assertSessionHasErrors('resultado');
    $this->actingAs($gestor)->patch($url, ['tema_id' => 'nao-e-uuid'])->assertSessionHasErrors('tema_id');
    $this->actingAs($gestor)->patch($url, ['tema_id' => '0190a7c2-0000-7000-8000-000000000000'])->assertSessionHasErrors('tema_id');
    $this->actingAs($gestor)->patch($url, [])->assertSessionHasErrors('caso_de_uso');

    expect($classificacao->refresh()->correcao)->toBeNull();
});

it('responde 404 ao corrigir uma conversa sem classificação', function () {
    $ana = User::factory()->create(['admin' => false]);
    $conversa = Conversa::factory()->for($ana)->create();

    $this->actingAs(gestorDasConversas())
        ->patch(route('gestao.conversas.corrigir', $conversa), ['caso_de_uso' => 'reserva'])
        ->assertNotFound();
});

it('responde 403 a quem não é gestor em conversas e na correção', function () {
    $ana = User::factory()->create(['admin' => false]);
    $classificacao = conversaClassificada($ana);
    $secretaria = User::factory()->create(['admin' => false]);

    $this->actingAs($secretaria)
        ->get(route('gestao.conversas.index'))
        ->assertForbidden();

    $this->actingAs($secretaria)
        ->patch(route('gestao.conversas.corrigir', $classificacao->conversa), ['caso_de_uso' => 'reserva'])
        ->assertForbidden();

    expect($classificacao->refresh()->correcao)->toBeNull();
});

it('mascara o título da conversa na lista e no detalhe, com o nome do cliente e dos grupos', function () {
    $ana = User::factory()->create(['admin' => false, 'name' => 'Ana Souza']);
    Grupo::factory()->for($ana)->create(['nome' => 'Pastoral Esperança']);

    $classificacao = conversaClassificada($ana, [], ['titulo' => 'Ana Souza da Pastoral Esperança, ana@paroquia.test']);
    $conversa = $classificacao->conversa;

    $this->actingAs(gestorDasConversas())
        ->get(route('gestao.conversas.index', ['conversa' => $conversa->id]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            foreach ([$props['conversas']['data'][0]['titulo'], $props['selecionada']['titulo']] as $texto) {
                expect($texto)->not->toContain('Souza')
                    ->and($texto)->not->toContain('Esperança')
                    ->and($texto)->not->toContain('ana@paroquia.test');
            }
        });
});
