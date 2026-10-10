<?php

namespace App\Models;

use App\Enums\StatusSolicitacao;
use Database\Factories\SolicitacaoFactory;
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
 * Pedido de reserva feito pelo formulário público, à espera de aprovação da secretaria.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $grupo_id
 * @property string $nome
 * @property string $telefone
 * @property string $titulo
 * @property Carbon $data
 * @property string $inicio
 * @property string $fim
 * @property string|null $espaco_id
 * @property int|null $pessoas
 * @property string|null $observacoes
 * @property StatusSolicitacao $status
 * @property string|null $motivo_recusa
 * @property string|null $reserva_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('solicitacoes')]
#[Fillable([
    'grupo_id',
    'nome',
    'telefone',
    'titulo',
    'data',
    'inicio',
    'fim',
    'espaco_id',
    'pessoas',
    'observacoes',
    'status',
    'motivo_recusa',
    'reserva_id',
])]
class Solicitacao extends Model
{
    /** @use HasFactory<SolicitacaoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'status' => StatusSolicitacao::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /** @return BelongsTo<Espaco, $this> */
    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class, 'espaco_id');
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    /** @return BelongsToMany<Equipamento, $this, Pivot> */
    public function equipamentos(): BelongsToMany
    {
        return $this->belongsToMany(Equipamento::class, 'solicitacao_equipamento', 'solicitacao_id', 'equipamento_id')
            ->withPivot(['quantidade']);
    }
}
