<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JEV (TypeSafe, via OpenRouter)
    |--------------------------------------------------------------------------
    |
    | Faz as perguntas fechadas sobre cada conversa. A chave padrão é a do
    | OpenRouter, a mesma do assistente.
    |
    */
    'jev_url' => env('GESTAO_JEV_URL', 'https://openrouter.ai/api/v1/systemone'),
    'jev_modelo' => env('GESTAO_JEV_MODELO', '~typesafe/jev-latest'),
    'jev_chave' => env('GESTAO_JEV_CHAVE', env('OPENROUTER_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Haiku (resumos e nomes de pedidos)
    |--------------------------------------------------------------------------
    */
    'haiku_modelo' => env('GESTAO_HAIKU_MODELO', env('ASSISTENTE_MODELO', 'anthropic/claude-haiku-5.5')),

    /*
    |--------------------------------------------------------------------------
    | Custos (dólares por milhão de tokens)
    |--------------------------------------------------------------------------
    |
    | O preço do assistente não é fixo: sem valor configurado, o painel não
    | mostra o custo do assistente.
    |
    */
    'preco_jev_por_milhao' => 0.042,
    'preco_assistente_entrada_por_milhao' => env('GESTAO_PRECO_ASSISTENTE_ENTRADA'),
    'preco_assistente_saida_por_milhao' => env('GESTAO_PRECO_ASSISTENTE_SAIDA'),

    /*
    |--------------------------------------------------------------------------
    | Classificação
    |--------------------------------------------------------------------------
    */
    'confianca_minima' => 0.6,
    'ociosidade_minutos' => 60,
    'max_conversas_por_rodada' => 500,
    'max_resumos_por_rodada' => 100,
    'max_mensagens_no_estado' => 40,
    'max_caracteres_por_mensagem' => 1200,

    /*
    |--------------------------------------------------------------------------
    | Alertas do painel
    |--------------------------------------------------------------------------
    */
    'tema_novo_alerta_semana' => 5,

    // Conversa que parou esperando a secretaria fica "aguardando" por estes dias; depois, conta como abandonada.
    'dias_aguardando' => 3,
    'dias_sem_acesso_alerta' => 14,

    // Mesmo fuso da agenda (config/agendamentos.php), lido direto do ambiente para não depender da ordem de carga.
    'fuso' => env('AGENDAMENTOS_FUSO', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Chaves do OAuth do MCP
    |--------------------------------------------------------------------------
    |
    | Sem PASSPORT_PRIVATE_KEY/PASSPORT_PUBLIC_KEY no ambiente, o comando
    | gestao:chaves-oauth gera as chaves uma vez e guarda uma cópia
    | criptografada (com a APP_KEY) neste disco, para sobreviver aos deploys.
    |
    */
    'disco_das_chaves_oauth' => env('GESTAO_DISCO_CHAVES_OAUTH', 's3'),
];
