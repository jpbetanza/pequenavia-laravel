<?php

namespace App\Services\Push;

use App\Models\InscricaoPush;

/**
 * Entrega mensagens aos serviços de push dos navegadores. Fica atrás de uma interface para os testes não irem à rede.
 */
interface CanalDePush
{
    /**
     * Envia a mesma mensagem a cada inscrição e diz o que aconteceu com cada uma.
     *
     * @param  list<InscricaoPush>  $inscricoes
     * @param  array<string, mixed>  $mensagem
     * @return list<ResultadoDoPush>
     */
    public function enviar(array $inscricoes, array $mensagem): array;
}
