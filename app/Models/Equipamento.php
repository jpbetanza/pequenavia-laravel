<?php

namespace App\Models;

use Database\Factories\EquipamentoFactory;
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
 * Equipamento da paróquia (cadeiras, projetor etc.) com unidades que podem ser reservadas.
 *
 * @property string $id
 * @property string $user_id
 * @property string $nome
 * @property string|null $descricao
 * @property int $quantidade
 * @property string|null $local_guarda
 * @property Carbon|null $manutencao_ate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('equipamentos')]
#[Fillable(['nome', 'descricao', 'quantidade', 'local_guarda', 'manutencao_ate'])]
class Equipamento extends Model
{
    /** @use HasFactory<EquipamentoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['manutencao_ate' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Reserva, $this, Pivot> */
    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'reserva_equipamento', 'equipamento_id', 'reserva_id')
            ->withPivot(['quantidade', 'entregue_em', 'recolhido_em']);
    }

    /** @return BelongsToMany<Solicitacao, $this, Pivot> */
    public function solicitacoes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitacao::class, 'solicitacao_equipamento', 'equipamento_id', 'solicitacao_id')
            ->withPivot(['quantidade']);
    }
}
