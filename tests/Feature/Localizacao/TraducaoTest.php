<?php

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

beforeEach(fn () => App::setLocale('pt_BR'));

it('traduz as mensagens de autenticação', function () {
    expect(__('auth.failed'))->toBe('Essas credenciais não correspondem aos nossos registros.')
        ->and(__('auth.password'))->toBe('A senha informada está incorreta.')
        ->and(__('auth.throttle', ['seconds' => 30]))->toBe('Muitas tentativas de login. Tente novamente em 30 segundos.');
});

it('traduz as mensagens de redefinição de senha', function () {
    expect(__('passwords.sent'))->toBe('Enviamos por e-mail o link de redefinição de senha.')
        ->and(__('passwords.reset'))->toBe('Sua senha foi redefinida.')
        ->and(__('passwords.user'))->toBe('Não encontramos um usuário com esse endereço de e-mail.');
});

it('traduz a validação usando o nome amigável dos campos', function () {
    $erros = Validator::make(['email' => '', 'password' => 'curta'], [
        'email' => 'required',
        'password' => 'min:8',
    ])->errors();

    expect($erros->first('email'))->toBe('O campo e-mail é obrigatório.')
        ->and($erros->first('password'))->toBe('O campo senha deve ter pelo menos 8 caracteres.');
});

it('traduz as frases dos e-mails do Laravel', function () {
    expect(__('Verify Email Address'))->toBe('Verificar endereço de e-mail')
        ->and(__('Reset Password'))->toBe('Redefinir senha')
        ->and(__('Hello!'))->toBe('Olá!')
        ->and(__('Regards,'))->toBe('Atenciosamente,');
});

it('traduz as mensagens de validação dos formulários do app (sem texto em inglês)', function () {
    $erros = Validator::make(['label' => str_repeat('a', 200), 'tamanho_fonte' => 'x'], [
        'label' => 'max:150',
        'tamanho_fonte' => 'integer',
    ])->errors();

    expect($erros->first('label'))->not->toMatch('/\\bmay not\\b|\\bmust\\b|\\bcharacters\\b/')
        ->and($erros->first('tamanho_fonte'))->not->toMatch('/\\bmust\\b/');
});

it('mostra em português o erro de login com credenciais erradas', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'senha-errada'])
        ->assertSessionHasErrors(['email' => 'Essas credenciais não correspondem aos nossos registros.']);
});
