<?php

namespace App\Enums;

/**
 * Estado de uma solicitação feita pelo formulário público.
 */
enum StatusSolicitacao: string
{
    case Pendente = 'pendente';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
}
