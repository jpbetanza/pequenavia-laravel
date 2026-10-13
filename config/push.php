<?php

return [
    // Chaves VAPID das notificações no celular (Web Push). Gere com `php artisan push:gerar-chaves`.
    // Sem as duas chaves, o envio fica desligado e a tela de notificações avisa que não está configurado.
    'chave_publica' => env('VAPID_PUBLIC_KEY'),
    'chave_privada' => env('VAPID_PRIVATE_KEY'),
    // Contato do remetente que o serviço de push do Google e da Apple guarda: um mailto: ou a URL do site.
    'assunto' => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
    // Por quanto tempo o serviço de push guarda a notificação de um aparelho desligado.
    'validade_segundos' => 24 * 60 * 60,
];
