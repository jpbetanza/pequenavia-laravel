<?php

namespace App\Models;

use Database\Factories\ReservaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Reserva de um espaco em uma data e horário, feita pela secretaria.
 *
 * @property string $id
 * @property string $user_id
 * @property string $espaco_id
 * @property string|null $grupo_id
 * @property string $titulo
 * @property Carbon $data
 * @property string $inicio
 * @property string $fim
 * @property int|null $pessoas
 * @property string|null $observacoes
 * @property string|null $serie_id
 * @property string|null $solicitacao_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('reservas')]
#[Fillable([
    'espaco_id',
    'grupo_id',
    'titulo',
    'data',
    'inicio',
    'fim',
    'pessoas',
    'observacoes',
    'serie_id',
    'solicitacao_id',
])]
class Reserva extends Model
{
    /** @use HasFactory<ReservaFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Espaco, $this> */
    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class, 'espaco_id');
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /** @return BelongsTo<Solicitacao, $this> */
    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    /** @return BelongsToMany<Equipamento, $this, Pivot> */
    public function equipamentos(): BelongsToMany
    {
        return $this->belongsToMany(Equipamento::class, 'reserva_equipamento', 'reserva_id', 'equipamento_id')
            ->withPivot(['quantidade', 'entregue_em', 'recolhido_em']);
    }
}
