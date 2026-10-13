<?php

namespace App\Services\Push;

use App\Models\InscricaoPush;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Manda uma notificação para todos os aparelhos do usuário. Aparelho que cancelou a inscrição (o serviço de push
 * responde 404 ou 410) sai da lista; outras falhas só ficam no log.
 */
class Notificador
{
    public function __construct(private readonly CanalDePush $canal) {}

    /** As duas chaves VAPID estão configuradas? */
    public static function configurado(): bool
    {
        return filled(config('push.chave_publica')) && filled(config('push.chave_privada'));
    }

    /**
     * @param  string  $url  caminho aberto ao tocar na notificação (ex.: /agendamentos/solicitacoes)
     * @param  string|null  $tag  notificações com a mesma tag se substituem no aparelho
     * @return int quantos aparelhos receberam
     */
    public function notificar(User $usuario, string $titulo, string $corpo, string $url, ?string $tag = null): int
    {
        if (! self::configurado()) {
            return 0;
        }

        $inscricoes = $usuario->inscricoesPush()->get();

        if ($inscricoes->isEmpty()) {
            return 0;
        }

        $resultados = $this->canal->enviar(array_values($inscricoes->all()), array_filter([
            'titulo' => $titulo,
            'corpo' => $corpo,
            'url' => $url,
            'tag' => $tag,
        ], fn ($valor) => $valor !== null));

        $expiradas = [];
        $entregues = 0;

        foreach ($resultados as $resultado) {
            if ($resultado->entregue) {
                $entregues++;
            } elseif ($resultado->expirada) {
                $expiradas[] = $resultado->endpoint;
            } else {
                Log::warning('push.falha', ['user' => $usuario->id, 'motivo' => $resultado->motivo]);
            }
        }

        if ($expiradas !== []) {
            InscricaoPush::query()->whereIn('endpoint', $expiradas)->delete();
        }

        return $entregues;
    }
}
