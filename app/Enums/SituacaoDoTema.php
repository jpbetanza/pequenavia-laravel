<?php

namespace App\Enums;

/**
 * Andamento de um tema de pedido no painel de gestão.
 */
enum SituacaoDoTema: string
{
    case Novo = 'novo';
    case Acompanhando = 'acompanhando';
    case Planejado = 'planejado';
    case Descartado = 'descartado';

    /**
     * Texto mostrado no painel de gestão.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Acompanhando => 'Acompanhando',
            self::Planejado => 'Planejado',
            self::Descartado => 'Descartado',
        };
    }
}
