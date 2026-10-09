<?php

namespace App\Models;

use Database\Factories\CampoCertificadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $modelo_id
 * @property string $nome
 * @property string|null $label
 * @property int $x
 * @property int $y
 * @property string $cor
 * @property int $tamanho_fonte
 * @property string $fonte_familia
 * @property int $peso_fonte
 * @property float $espacamento_letras
 * @property bool $centralizado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('campos_certificado')]
#[Fillable([
    'nome', 'label', 'x', 'y', 'cor', 'tamanho_fonte', 'fonte_familia',
    'peso_fonte', 'espacamento_letras', 'centralizado',
])]
class CampoCertificado extends Model
{
    /** @use HasFactory<CampoCertificadoFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'tamanho_fonte' => 'integer',
            'peso_fonte' => 'integer',
            'espacamento_letras' => 'float',
            'centralizado' => 'boolean',
        ];
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
