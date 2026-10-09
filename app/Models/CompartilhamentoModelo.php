<?php

namespace App\Models;

use Database\Factories\CompartilhamentoModeloFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $modelo_id
 * @property string $email
 * @property bool $pode_editar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('compartilhamentos_modelo')]
#[Fillable(['email', 'pode_editar'])]
class CompartilhamentoModelo extends Model
{
    /** @use HasFactory<CompartilhamentoModeloFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pode_editar' => 'boolean'];
    }

    /**
     * O email é guardado em minúsculas para casar com o email do usuário.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower(trim($value)));
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
