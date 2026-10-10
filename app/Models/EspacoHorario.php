<?php

namespace App\Models;

use Database\Factories\EspacoHorarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Horário em que um espaço abre em um dia da semana. Dia sem linha significa fechado.
 *
 * @property string $id
 * @property string $espaco_id
 * @property int $dia_semana 0 é domingo e 6 é sábado
 * @property string $abre
 * @property string $fecha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('espaco_horarios')]
#[Fillable(['dia_semana', 'abre', 'fecha'])]
class EspacoHorario extends Model
{
    /** @use HasFactory<EspacoHorarioFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<Espaco, $this> */
    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class, 'espaco_id');
    }
}
