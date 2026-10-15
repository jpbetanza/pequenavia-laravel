<?php

namespace App\Enums;

/**
 * Se a secretaria conseguiu o que pediu até o fim da conversa. "Aguardando" é a conversa recente que parou
 * esperando uma resposta dela: não conta como resolvida nem como não resolvida até ela voltar ou o prazo passar.
 */
enum ResultadoDaConversa: string
{
    case Resolvida = 'resolvida';
    case Parcial = 'parcial';
    case NaoResolvida = 'nao_resolvida';
    case Aguardando = 'aguardando';

    /**
     * Texto mostrado no painel de gestão.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Resolvida => 'Resolvida',
            self::Parcial => 'Parcial',
            self::NaoResolvida => 'Não resolvida',
            self::Aguardando => 'Aguardando a secretaria',
        };
    }
}
