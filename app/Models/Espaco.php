<?php

namespace App\Models;

use App\Enums\CorDoEspaco;
use Database\Factories\EspacoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sala ou espaço da paróquia que pode ser reservado.
 *
 * @property string $id
 * @property string $user_id
 * @property string $nome
 * @property int|null $capacidade
 * @property string|null $localizacao
 * @property CorDoEspaco $cor
 * @property array<int, string> $recursos_fixos
 * @property bool $aceita_solicitacoes
 * @property int $antecedencia_min_horas
 * @property int|null $duracao_max_min
 * @property int $intervalo_min
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('espacos')]
#[Fillable([
    'nome',
    'capacidade',
    'localizacao',
    'cor',
    'recursos_fixos',
    'aceita_solicitacoes',
    'antecedencia_min_horas',
    'duracao_max_min',
    'intervalo_min',
    'ativo',
])]
class Espaco extends Model
{
    /** @use HasFactory<EspacoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cor' => CorDoEspaco::class,
            'recursos_fixos' => 'array',
            'aceita_solicitacoes' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EspacoHorario, $this> */
    public function horarios(): HasMany
    {
        return $this->hasMany(EspacoHorario::class, 'espaco_id')->orderBy('dia_semana');
    }

    /** @return HasMany<EspacoBloqueio, $this> */
    public function bloqueios(): HasMany
    {
        return $this->hasMany(EspacoBloqueio::class, 'espaco_id')->orderBy('inicio');
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'espaco_id');
    }

    /** @return HasMany<Solicitacao, $this> */
    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'espaco_id');
    }
}
