<?php

namespace App\Enums;

/**
 * Estado de uma ação que o assistente propôs e aguarda confirmação do usuário.
 */
enum StatusDaAcao: string
{
    case Proposta = 'proposta';
    case Confirmada = 'confirmada';
    case Descartada = 'descartada';
    case Expirada = 'expirada';
}
