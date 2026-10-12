<?php

namespace App\Services\Assistente;

use App\Models\Conversa;
use App\Models\User;
use App\Services\Assistente\Ferramentas\ArgumentoInvalido;
use App\Services\Assistente\Ferramentas\Ferramenta;
use App\Services\Assistente\Ferramentas\ResultadoDeFerramenta;

/**
 * Ferramentas que o assistente pode chamar, indexadas pelo nome. Nunca lança: qualquer erro de chamada
 * volta para o modelo como ['erro' => mensagem], para ele corrigir e tentar de novo. Falhas inesperadas são reportadas.
 */
final class CaixaDeFerramentas
{
    /** @var array<string, Ferramenta> */
    private array $ferramentas = [];

    /**
     * @param  iterable<Ferramenta>  $ferramentas
     */
    public function __construct(iterable $ferramentas)
    {
        foreach ($ferramentas as $ferramenta) {
            $this->ferramentas[$ferramenta->nome()] = $ferramenta;
        }
    }

    /**
     * Definições no formato de tools da OpenAI, que o OpenRouter aceita.
     *
     * @return list<array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function definicoes(): array
    {
        return array_values(array_map(fn (Ferramenta $ferramenta) => [
            'type' => 'function',
            'function' => [
                'name' => $ferramenta->nome(),
                'description' => $ferramenta->descricao(),
                'parameters' => $ferramenta->parametros(),
            ],
        ], $this->ferramentas));
    }

    public function statusDe(string $nome): string
    {
        return isset($this->ferramentas[$nome]) ? $this->ferramentas[$nome]->status() : 'Consultando…';
    }

    public function executar(User $usuario, Conversa $conversa, ChamadaDeFerramenta $chamada): ResultadoDeFerramenta
    {
        $ferramenta = $this->ferramentas[$chamada->nome] ?? null;

        if ($ferramenta === null) {
            return new ResultadoDeFerramenta(['erro' => "Ferramenta desconhecida: {$chamada->nome}."]);
        }

        $argumentos = json_decode(trim($chamada->argumentosJson) === '' ? '{}' : $chamada->argumentosJson, true);

        if (! is_array($argumentos)) {
            return new ResultadoDeFerramenta(['erro' => 'Argumentos inválidos: envie um objeto JSON.']);
        }

        try {
            return $ferramenta->executar($usuario, $conversa, $argumentos);
        } catch (ArgumentoInvalido $erro) {
            return new ResultadoDeFerramenta(['erro' => $erro->getMessage()]);
        } catch (\Throwable $erro) {
            report($erro);

            return new ResultadoDeFerramenta(['erro' => 'Não consegui executar essa consulta.']);
        }
    }
}
