<?php

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use App\Enums\SituacaoDoTema;
use App\Models\AcessoAConversa;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\TemaDePedido;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('o comando liga e desliga o acesso de gestor', function () {
    $usuario = User::factory()->create(['email' => 'secretaria@paroquia.test']);

    $this->artisan('gestao:admin', ['email' => 'SECRETARIA@paroquia.test'])
        ->expectsOutputToContain('ligado')
        ->assertSuccessful();
    expect($usuario->fresh()->admin)->toBeTrue();

    $this->artisan('gestao:admin', ['email' => 'secretaria@paroquia.test', '--remover' => true])
        ->expectsOutputToContain('desligado')
        ->assertSuccessful();
    expect($usuario->fresh()->admin)->toBeFalse();
});

test('o comando falha com mensagem clara quando o e-mail não existe', function () {
    $this->artisan('gestao:admin', ['email' => 'ninguem@paroquia.test'])
        ->expectsOutputToContain('Nenhum usuário com o e-mail ninguem@paroquia.test.')
        ->assertFailed();
});

test('o gate permite o gestor e nega os demais', function () {
    $gestor = User::factory()->create(['admin' => true]);
    $secretaria = User::factory()->create(['admin' => false]);

    expect(Gate::forUser($gestor)->allows('gestao'))->toBeTrue()
        ->and(Gate::forUser($secretaria)->allows('gestao'))->toBeFalse();
});

test('admin não é atribuível em massa', function () {
    expect((new User)->isFillable('admin'))->toBeFalse();

    $usuario = new User(['name' => 'Intruso', 'admin' => true]);

    expect($usuario->getAttributes())->not->toHaveKey('admin');
});

test('a correção do gestor se sobrepõe ao rótulo do JEV', function () {
    $temaJev = TemaDePedido::factory()->create();
    $temaCorrigido = TemaDePedido::factory()->create();
    $classificacao = ClassificacaoDeConversa::factory()->create([
        'caso_de_uso' => CasoDeUso::Outro->value,
        'resultado' => ResultadoDaConversa::NaoResolvida->value,
        'tema_id' => $temaJev->id,
        'correcao' => null,
    ]);

    expect($classificacao->casoEfetivo())->toBe(CasoDeUso::Outro)
        ->and($classificacao->resultadoEfetivo())->toBe(ResultadoDaConversa::NaoResolvida)
        ->and($classificacao->temaEfetivoId())->toBe($temaJev->id);

    $classificacao->update([
        'correcao' => [
            'caso_de_uso' => CasoDeUso::Cadastro->value,
            'resultado' => ResultadoDaConversa::Resolvida->value,
            'tema_id' => $temaCorrigido->id,
        ],
    ]);

    expect($classificacao->fresh()->casoEfetivo())->toBe(CasoDeUso::Cadastro)
        ->and($classificacao->fresh()->resultadoEfetivo())->toBe(ResultadoDaConversa::Resolvida)
        ->and($classificacao->fresh()->temaEfetivoId())->toBe($temaCorrigido->id);
});

test('a correção com tema nulo quer dizer nenhum tema', function () {
    $classificacao = ClassificacaoDeConversa::factory()->create([
        'tema_id' => TemaDePedido::factory()->create()->id,
        'correcao' => ['tema_id' => null],
    ]);

    expect($classificacao->temaEfetivoId())->toBeNull();
});

test('os rótulos e a situação do tema têm o texto em português', function () {
    expect(CasoDeUso::Cadastro->rotulo())->toBe('Cadastrar espaços, equipamentos ou grupos')
        ->and(CasoDeUso::Outro->rotulo())->toBe('Outro')
        ->and(ResultadoDaConversa::NaoResolvida->rotulo())->toBe('Não resolvida')
        ->and(SituacaoDoTema::Planejado->rotulo())->toBe('Planejado');
});

test('a conversa tem uma classificação e o acesso fica registrado', function () {
    $conversa = Conversa::factory()->for(User::factory())->create();
    $classificacao = ClassificacaoDeConversa::factory()->for($conversa)->create(['user_id' => $conversa->user_id]);
    $gestor = User::factory()->create(['admin' => true]);

    expect($conversa->fresh()->classificacao->is($classificacao))->toBeTrue();

    $acesso = AcessoAConversa::create(['user_id' => $gestor->id, 'conversa_id' => $conversa->id]);

    expect($acesso->created_at)->not->toBeNull()
        ->and($acesso->fresh()->conversa->is($conversa))->toBeTrue();
});
