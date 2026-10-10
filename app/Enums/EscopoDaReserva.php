<?php

namespace App\Enums;

/**
 * Alcance de uma edição ou exclusão de reserva: só esta ocorrência, ou esta e as próximas da mesma série.
 */
enum EscopoDaReserva: string
{
    case SoEsta = 'so_esta';
    case EstaEProximas = 'esta_e_proximas';
}
