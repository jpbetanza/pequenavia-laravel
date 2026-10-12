<?php

namespace App\Models;

use App\Enums\StatusDaAcao;
use Database\Factories\AcaoPropostaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ação que o assistente propôs (por exemplo, criar uma reserva). Só é executada depois da confirmação do usuário.
 *
 * @property string $id
 * @property string $conversa_id
 * @property string $user_id
 * @property string $tipo
 * @property array<string, mixed> $dados
 * @property StatusDaAcao $status
 * @property string|null $resultado_id
 * @property Carbon|null $confirmada_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('acoes_propostas')]
#[Fillable(['conversa_id', 'user_id', 'tipo', 'dados', 'status', 'resultado_id', 'confirmada_em'])]
class AcaoProposta extends Model
{
    /** @use HasFactory<AcaoPropostaFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'status' => StatusDaAcao::class,
            'confirmada_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversa, $this> */
    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
