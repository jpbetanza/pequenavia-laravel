<?php

namespace App\Models;

use Database\Factories\EspacoBloqueioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Período em que um espaço fica indisponível, com motivo.
 *
 * @property string $id
 * @property string $espaco_id
 * @property Carbon $inicio
 * @property Carbon $fim
 * @property string $motivo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('espaco_bloqueios')]
#[Fillable(['inicio', 'fim', 'motivo'])]
class EspacoBloqueio extends Model
{
    /** @use HasFactory<EspacoBloqueioFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    /** @return BelongsTo<Espaco, $this> */
    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class, 'espaco_id');
    }
}
