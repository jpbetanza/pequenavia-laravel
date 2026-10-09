<?php

namespace App\Models;

use Database\Factories\ModeloCertificadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property string $image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('modelos_certificado')]
#[Fillable(['title', 'description', 'image_path'])]
class ModeloCertificado extends Model
{
    /** @use HasFactory<ModeloCertificadoFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CampoCertificado, $this> */
    public function campos(): HasMany
    {
        return $this->hasMany(CampoCertificado::class, 'modelo_id')->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<CompartilhamentoModelo, $this> */
    public function compartilhamentos(): HasMany
    {
        return $this->hasMany(CompartilhamentoModelo::class, 'modelo_id');
    }

    /** @return HasMany<GeracaoCertificado, $this> */
    public function geracoes(): HasMany
    {
        return $this->hasMany(GeracaoCertificado::class, 'modelo_id');
    }
}
