<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de que um gestor abriu o detalhe de uma conversa. Só tem data de criação.
 *
 * @property int $id
 * @property string $user_id
 * @property string $conversa_id
 * @property Carbon $created_at
 */
#[Table('acessos_a_conversa')]
#[Fillable(['user_id', 'conversa_id'])]
class AcessoAConversa extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Conversa, $this> */
    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
