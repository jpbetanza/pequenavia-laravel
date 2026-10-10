<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso da agenda
    |--------------------------------------------------------------------------
    |
    | Datas e horários de reserva são hora local, gravados como date + time. Este
    | fuso só define o "agora" da agenda (hoje, linha do horário atual). Não
    | mexe no app.timezone.
    |
    */

    'fuso' => env('AGENDAMENTOS_FUSO', 'America/Sao_Paulo'),

];
