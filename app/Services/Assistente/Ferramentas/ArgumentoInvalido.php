<?php

namespace App\Services\Assistente\Ferramentas;

use InvalidArgumentException;

/**
 * Argumento que o modelo mandou errado. A mensagem em pt-BR volta para o modelo corrigir a chamada.
 */
class ArgumentoInvalido extends InvalidArgumentException {}
