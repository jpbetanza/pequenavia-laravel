<?php

namespace App\Enums;

/**
 * Quem escreveu uma mensagem da conversa com o assistente.
 */
enum PapelDaMensagem: string
{
    case Usuario = 'usuario';
    case Assistente = 'assistente';
    case Ferramenta = 'ferramenta';
}
