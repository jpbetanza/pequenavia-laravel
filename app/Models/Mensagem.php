<?php

namespace App\Models;

use App\Enums\PapelDaMensagem;
use Database\Factories\MensagemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma mensagem da conversa: do usuário, do assistente ou o resultado de uma ferramenta.
 *
 * @property string $id
 * @property string $conversa_id
 * @property PapelDaMensagem $papel
 * @property string|null $conteudo
 * @property array<int, array<string, mixed>> $cartoes
 * @property array<int, array<string, mixed>>|null $chamadas
 * @property string|null $ferramenta_id
 * @property int|null $tokens_entrada
 * @property int|null $tokens_saida
 * @property bool $erro
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('mensagens')]
#[Fillable([
    'conversa_id',
    'papel',
    'conteudo',
    'cartoes',
    'chamadas',
    'ferramenta_id',
    'tokens_entrada',
    'tokens_saida',
    'erro',
])]
class Mensagem extends Model
{
    /** @use HasFactory<MensagemFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'papel' => PapelDaMensagem::class,
            'cartoes' => 'array',
            'chamadas' => 'array',
            'erro' => 'boolean',
        ];
    }

    /** @return BelongsTo<Conversa, $this> */
    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
