<?php

return [
    'chave' => env('OPENROUTER_API_KEY'),
    'modelo' => env('ASSISTENTE_MODELO', 'anthropic/claude-haiku-5.5'),
    'url' => env('ASSISTENTE_URL', 'https://openrouter.ai/api/v1/chat/completions'),
    'max_rodadas_de_ferramenta' => 5,
    'mensagens_no_contexto' => 30,
    'max_caracteres_mensagem' => 2000,
    'timeout_segundos' => 60,
    // Anexos: planilhas e .txt chegam já convertidos em texto pelo navegador; imagem e PDF sobem como arquivo.
    'max_anexos' => 5,
    'max_kb_arquivo' => 5 * 1024,
    'max_caracteres_anexo' => 60000,
];
