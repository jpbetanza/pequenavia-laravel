<?php

namespace App\Models;

use Database\Factories\InscricaoPushFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Um aparelho (navegador) que aceitou receber notificações do usuário.
 *
 * @property string $id
 * @property string $user_id
 * @property string $endpoint Endereço do serviço de push do navegador (Google, Apple, Mozilla).
 * @property string $chave_p256dh Chave pública do aparelho, para criptografar a mensagem.
 * @property string $chave_auth Segredo de autenticação do aparelho.
 * @property string|null $dispositivo Descrição do aparelho, para a lista na tela.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('inscricoes_push')]
#[Fillable(['endpoint', 'chave_p256dh', 'chave_auth', 'dispositivo'])]
class InscricaoPush extends Model
{
    /** @use HasFactory<InscricaoPushFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
