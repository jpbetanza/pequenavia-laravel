<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Uma execução do classificador de conversas (duas vezes ao dia), com os totais e os custos.
 *
 * @property string $id
 * @property Carbon $iniciada_em
 * @property Carbon|null $terminada_em
 * @property int $conversas_lidas
 * @property int $resumidas
 * @property int $temas_novos
 * @property int $falhas
 * @property int $tokens_jev
 * @property string $custo_jev
 * @property string $custo_haiku
 * @property string|null $erro
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('rodadas_de_classificacao')]
#[Fillable(['iniciada_em', 'terminada_em', 'conversas_lidas', 'resumidas', 'temas_novos', 'falhas', 'tokens_jev', 'custo_jev', 'custo_haiku', 'erro'])]
class RodadaDeClassificacao extends Model
{
    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iniciada_em' => 'datetime',
            'terminada_em' => 'datetime',
            'conversas_lidas' => 'integer',
            'resumidas' => 'integer',
            'temas_novos' => 'integer',
            'falhas' => 'integer',
            'tokens_jev' => 'integer',
            'custo_jev' => 'decimal:6',
            'custo_haiku' => 'decimal:6',
        ];
    }

    /** @return HasMany<ClassificacaoDeConversa, $this> */
    public function classificacoes(): HasMany
    {
        return $this->hasMany(ClassificacaoDeConversa::class, 'rodada_id');
    }
}
