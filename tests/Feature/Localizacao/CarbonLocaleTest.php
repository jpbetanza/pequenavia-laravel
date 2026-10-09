<?php

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(fn () => App::setLocale('pt_BR'));

it('gera datas relativas em português quando o locale da aplicação é pt_BR', function () {
    expect(now()->subDays(3)->diffForHumans())->toContain('há');
});

it('exibe as datas das chaves de acesso em português na página de segurança', function () {
    Features::passkeys(['confirmPassword' => true]);

    $user = User::factory()->create();
    $passkey = $user->passkeys()->create([
        'name' => 'Chave de teste',
        'credential_id' => 'credencial-de-teste',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    DB::table('passkeys')->where('id', $passkey->id)->update([
        'created_at' => now()->subDays(3),
        'last_used_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('passkeys.0.created_at_diff', 'há 3 dias')
            ->where('passkeys.0.last_used_at_diff', 'há 2 dias'),
        );
});
