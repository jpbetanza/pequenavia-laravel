<?php

return [
    'chave' => env('OPENROUTER_API_KEY'),
    'modelo' => env('ASSISTENTE_MODELO', 'anthropic/claude-haiku-5.5'),
    'url' => env('ASSISTENTE_URL', 'https://openrouter.ai/api/v1/chat/completions'),
    'max_rodadas_de_ferramenta' => 5,
    'mensagens_no_contexto' => 30,
    // A janela anda em blocos, não mensagem a mensagem: o começo do histórico fica igual por vários pedidos e o cache vale.
    'passo_da_janela' => 15,
    // Resultado de ferramenta de um pedido já respondido vai para o modelo só até este tamanho.
    'limite_do_resultado_antigo' => 600,
    // Marcações de cache de prompt (cache_control) para modelos da Anthropic no OpenRouter.
    'cache_de_prompt' => (bool) env('ASSISTENTE_CACHE_DE_PROMPT', true),
    'max_caracteres_mensagem' => 2000,
    'timeout_segundos' => 60,
    // Anexos: planilhas e .txt chegam já convertidos em texto pelo navegador; imagem e PDF sobem como arquivo.
    'max_anexos' => 5,
    'max_kb_arquivo' => 5 * 1024,
    'max_caracteres_anexo' => 60000,
];
