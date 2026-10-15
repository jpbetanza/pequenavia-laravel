<?php

namespace App\Models;

use App\Enums\SituacaoDoTema;
use Database\Factories\TemaDePedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Necessidade que a secretaria teve e o app não cobre. Vem do JEV (opções fixas) ou do Haiku (nome novo).
 *
 * @property string $id
 * @property string $nome
 * @property string|null $descricao
 * @property SituacaoDoTema $situacao
 * @property string $criado_por `haiku` ou `gestor`
 * @property string|null $juntado_em_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('temas_de_pedido')]
#[Fillable(['nome', 'descricao', 'situacao', 'criado_por', 'juntado_em_id'])]
class TemaDePedido extends Model
{
    /** @use HasFactory<TemaDePedidoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['situacao' => SituacaoDoTema::class];
    }

    /** @return BelongsTo<TemaDePedido, $this> */
    public function juntadoEm(): BelongsTo
    {
        return $this->belongsTo(TemaDePedido::class, 'juntado_em_id');
    }

    /** @return HasMany<ClassificacaoDeConversa, $this> */
    public function classificacoes(): HasMany
    {
        return $this->hasMany(ClassificacaoDeConversa::class, 'tema_id');
    }
}
