<?php

return [
    /*
    | Gerações de certificado por usuário, por minuto. O lote do Excel faz uma requisição por linha,
    | então o cliente precisa esperar e tentar de novo ao receber 429.
    */
    'geracoes_por_minuto' => (int) env('CERTIFICADOS_GERACOES_POR_MINUTO', 60),
];
