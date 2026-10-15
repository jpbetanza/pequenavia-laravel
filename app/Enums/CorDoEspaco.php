<?php

namespace App\Enums;

/**
 * Cor de identificação de um espaço. A paleta de hex fica no front-end (`resources/js/lib/agenda/cores.ts`).
 */
enum CorDoEspaco: string
{
    case Bordo = 'bordo';
    case Azul = 'azul';
    case Verde = 'verde';
    case Roxo = 'roxo';
    case Dourado = 'dourado';
    case Terracota = 'terracota';
    case Petroleo = 'petroleo';
    case Oliva = 'oliva';
    case Rosa = 'rosa';
    case Grafite = 'grafite';
}
