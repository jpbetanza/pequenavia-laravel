<?php

namespace App\Services\Push;

use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Canal real: assina com as chaves VAPID, criptografa para cada aparelho (aes128gcm) e entrega pelo
 * serviço de push de cada navegador, com a biblioteca minishlink/web-push.
 */
final class CanalWebPush implements CanalDePush
{
    public function enviar(array $inscricoes, array $mensagem): array
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('push.assunto'),
                'publicKey' => (string) config('push.chave_publica'),
                'privateKey' => (string) config('push.chave_privada'),
            ],
        ], ['TTL' => (int) config('push.validade_segundos'), 'urgency' => 'normal'],
            // Com o logger, os avisos da biblioteca (ex.: "instale GMP") vão para o log; sem ele, viram trigger_error,
            // que o Laravel transforma em exceção e derrubaria o envio.
            logger: app(LoggerInterface::class));

        $corpo = (string) json_encode($mensagem, JSON_UNESCAPED_UNICODE);

        foreach ($inscricoes as $inscricao) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $inscricao->endpoint,
                'publicKey' => $inscricao->chave_p256dh,
                'authToken' => $inscricao->chave_auth,
                'contentEncoding' => 'aes128gcm',
            ]), $corpo);
        }

        $resultados = [];

        /** @var MessageSentReport $relatorio */
        foreach ($webPush->flush() as $relatorio) {
            $resultados[] = new ResultadoDoPush(
                endpoint: $relatorio->getEndpoint(),
                entregue: $relatorio->isSuccess(),
                expirada: $relatorio->isSubscriptionExpired(),
                motivo: $relatorio->isSuccess() ? null : $relatorio->getReason(),
            );
        }

        return $resultados;
    }
}
