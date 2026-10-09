<?php

namespace App\Models;

use Database\Factories\GeracaoCertificadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $modelo_id
 * @property string $modelo_title
 * @property array<string, string> $campos_valores
 * @property Carbon|null $created_at
 */
#[Table('geracoes_certificado')]
#[Fillable(['user_id', 'modelo_id', 'modelo_title', 'campos_valores'])]
class GeracaoCertificado extends Model
{
    /** @use HasFactory<GeracaoCertificadoFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['campos_valores' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
