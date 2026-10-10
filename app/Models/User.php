<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $avatar_url
 * @property string|null $agenda_slug
 * @property bool $tem_senha
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'google_id', 'avatar_url'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['tem_senha'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * O email é guardado em minúsculas: o compartilhamento de modelos casa por email.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * Quem entra só pelo Google não tem senha.
     *
     * @return Attribute<bool, never>
     */
    protected function temSenha(): Attribute
    {
        return Attribute::get(fn (): bool => $this->password !== null);
    }

    /**
     * Slug do formulário público de solicitações. É criado na primeira vez que alguém precisa dele.
     */
    public function agendaSlug(): string
    {
        if ($this->agenda_slug === null) {
            do {
                $slug = Str::lower(Str::random(10));
            } while (static::query()->where('agenda_slug', $slug)->exists());

            $this->forceFill(['agenda_slug' => $slug])->save();
        }

        return $this->agenda_slug;
    }

    /** @return HasMany<ModeloCertificado, $this> */
    public function modelos(): HasMany
    {
        return $this->hasMany(ModeloCertificado::class);
    }

    /** @return HasMany<GeracaoCertificado, $this> */
    public function geracoes(): HasMany
    {
        return $this->hasMany(GeracaoCertificado::class);
    }

    /** @return HasMany<Espaco, $this> */
    public function espacos(): HasMany
    {
        return $this->hasMany(Espaco::class);
    }

    /** @return HasMany<Equipamento, $this> */
    public function equipamentos(): HasMany
    {
        return $this->hasMany(Equipamento::class);
    }

    /** @return HasMany<Grupo, $this> */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    /** @return HasMany<Solicitacao, $this> */
    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class);
    }
}
