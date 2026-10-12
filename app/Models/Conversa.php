<?php

namespace App\Models;

use Database\Factories\ConversaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conversa do usuário com o assistente de IA, guardada para ser retomada depois.
 *
 * @property string $id
 * @property string $user_id
 * @property string $titulo
 * @property Carbon|null $ultima_mensagem_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('conversas')]
#[Fillable(['titulo', 'ultima_mensagem_em'])]
class Conversa extends Model
{
    /** @use HasFactory<ConversaFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ultima_mensagem_em' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Mensagem, $this> */
    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class, 'conversa_id');
    }

    /** @return HasMany<AcaoProposta, $this> */
    public function acoes(): HasMany
    {
        return $this->hasMany(AcaoProposta::class, 'conversa_id');
    }
}
