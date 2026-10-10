<?php

namespace App\Models;

use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pastoral ou grupo da paróquia, dono da própria lista na agenda.
 *
 * @property string $id
 * @property string $user_id
 * @property string $nome
 * @property string|null $coordenacao
 * @property string|null $contato
 * @property string|null $observacao
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('grupos')]
#[Fillable(['nome', 'coordenacao', 'contato', 'observacao', 'ativo'])]
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'grupo_id');
    }

    /** @return HasMany<Solicitacao, $this> */
    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'grupo_id');
    }
}
