<?php

namespace App\Enums;

/**
 * Se a secretaria conseguiu o que pediu até o fim da conversa.
 */
enum ResultadoDaConversa: string
{
    case Resolvida = 'resolvida';
    case Parcial = 'parcial';
    case NaoResolvida = 'nao_resolvida';

    /**
     * Texto mostrado no painel de gestão.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Resolvida => 'Resolvida',
            self::Parcial => 'Parcial',
            self::NaoResolvida => 'Não resolvida',
        };
    }
}
