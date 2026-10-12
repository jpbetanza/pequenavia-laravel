<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\User;
use App\Services\Assistente\Cadastros\CadastrosDoAssistente;
use App\Services\Assistente\Cadastros\PropostaDeCadastro;
use App\Services\Assistente\ResumoDaAcao;
use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Base das ferramentas que propõem criar, alterar ou excluir um cadastro (grupo, espaço, equipamento).
 * Nada é gravado aqui: a proposta vira uma AcaoProposta que só é executada depois do clique em Confirmar.
 * As regras e as mensagens são as mesmas da tela, vindas dos Regras* de cada cadastro.
 *
 * Cada subclasse descreve o seu cadastro; o fluxo (validar, comparar, montar linhas e avisos) fica aqui.
 */
abstract class PropoeCadastro implements Ferramenta
{
    use ValidaArgumentos;

    public function __construct(
        protected readonly ResolvedorDeCadastros $resolvedor,
        protected readonly ResumoDaAcao $resumo,
        protected readonly CadastrosDoAssistente $cadastros,
    ) {}

    /** Chave do cadastro nas ações: 'grupo', 'espaco' ou 'equipamento'. */
    abstract protected function entidade(): string;

    /** Nome do cadastro nos títulos do cartão: 'grupo', 'espaço' ou 'equipamento'. */
    abstract protected function nomeNaFrase(): string;

    /**
     * Campos que o modelo pode enviar. A chave é o campo; `tipo` decide a leitura do cartão e a normalização.
     *
     * @return array<string, array{rotulo: string, tipo: 'texto'|'inteiro'|'booleano'|'data'|'lista'}>
     */
    abstract protected function campos(): array;

    /**
     * Acha o cadastro do usuário pelo nome ou id.
     *
     * @throws ArgumentoInvalido
     */
    abstract protected function resolverAlvo(User $usuario, string $idOuNome): Model;

    /**
     * Avisos que a exclusão deixa para a secretaria. Lança ArgumentoInvalido quando a exclusão não pode acontecer.
     *
     * @return list<string>
     *
     * @throws ArgumentoInvalido
     */
    abstract protected function avisosDaExclusao(Model $alvo): array;

    /**
     * Estado final que a proposta grava. Por padrão, os campos atuais com os enviados por cima.
     *
     * @param  ?Model  $alvo  cadastro atual (null na criação)
     * @param  array<string, mixed>  $atual  campos atuais (vazio na criação)
     * @param  array<string, mixed>  $enviados  campos enviados, normalizados
     * @param  array<string, mixed>  $argumentos  argumentos crus da chamada, para o que não é campo simples
     * @return array<string, mixed>
     */
    protected function montarFinal(?Model $alvo, array $atual, array $enviados, array $argumentos): array
    {
        return array_replace($atual, $enviados);
    }

    /**
     * Linhas do cartão que não vêm de um campo simples (ex.: horários do espaço), comparando com o cadastro atual.
     *
     * @param  ?Model  $alvo  cadastro atual (null na criação)
     * @param  array<string, mixed>  $final
     * @return list<array{rotulo: string, antes: ?string, depois: ?string}>
     */
    protected function linhasExtras(?Model $alvo, array $final, bool $criacao): array
    {
        return [];
    }

    /**
     * @throws ArgumentoInvalido
     */
    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $controle = $this->validar($argumentos, [
            'operacao' => ['required', 'in:criar,alterar,excluir'],
            'alvo' => ['nullable', 'string'],
        ]);
        $operacao = $controle['operacao'];
        $criacao = $operacao === 'criar';

        if (! $criacao && trim((string) ($controle['alvo'] ?? '')) === '') {
            throw new ArgumentoInvalido("Informe qual {$this->nomeNaFrase()} quer {$this->verboMinusculo($operacao)}. Use listar_cadastros para ver os nomes.");
        }

        $alvo = $criacao ? null : $this->resolverAlvo($usuario, $controle['alvo']);
        $atual = $alvo === null ? [] : $this->atributosAtuais($alvo);
        $enviados = $this->normalizar(Arr::only($argumentos, array_keys($this->campos())));

        $final = [];
        $linhas = [];
        $avisos = [];

        if ($operacao === 'excluir') {
            $avisos = $this->avisosDaExclusao($alvo);
        } else {
            $final = $this->validarFinal($usuario, $this->montarFinal($alvo, $atual, $enviados, $argumentos), $alvo);
            $linhas = [
                ...$this->linhasDosCampos($atual, $final, $criacao),
                ...$this->linhasExtras($alvo, $final, $criacao),
            ];

            if (! $criacao && $linhas === []) {
                throw new ArgumentoInvalido('Nada para alterar: os valores informados já são os atuais.');
            }
        }

        $nome = $criacao ? (string) ($final['nome'] ?? '') : (string) $alvo->getAttribute('nome');
        $titulo = $this->verboDoTitulo($operacao)." {$this->nomeNaFrase()}: {$nome}";

        $acao = PropostaDeCadastro::criar(
            $usuario,
            $conversa,
            $this->entidade(),
            $operacao,
            $alvo,
            $final,
            $linhas,
            $avisos,
            $titulo,
            $alvo === null ? null : $this->cadastros->impressao($alvo),
        );

        return new ResultadoDeFerramenta(
            paraOModelo: [
                'criada' => true,
                'acao_id' => $acao->id,
                'mensagem' => 'Proposta pronta. A secretaria precisa clicar em Confirmar.',
            ],
            cartao: ['tipo' => 'acao_proposta', 'dados' => $this->resumo->paraCartao($acao)],
        );
    }

    /**
     * Valores vindos do modelo: texto vazio vira null e "false"/"0" viram booleano, antes de validar e comparar.
     *
     * @param  array<string, mixed>  $enviados
     * @return array<string, mixed>
     */
    private function normalizar(array $enviados): array
    {
        $normalizados = [];

        foreach ($enviados as $campo => $valor) {
            if ($valor === '') {
                $valor = null;
            }

            if ($valor !== null && ($this->campos()[$campo]['tipo'] ?? null) === 'booleano') {
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $valor;
            }

            $normalizados[$campo] = $valor;
        }

        return $normalizados;
    }

    /**
     * @return array<string, mixed>
     */
    private function atributosAtuais(Model $alvo): array
    {
        $atuais = [];

        foreach (array_keys($this->campos()) as $campo) {
            $valor = $alvo->getAttribute($campo);
            $atuais[$campo] = match (true) {
                $valor instanceof DateTimeInterface => Carbon::instance($valor)->toDateString(),
                $valor instanceof BackedEnum => $valor->value,
                default => $valor,
            };
        }

        return $atuais;
    }

    /**
     * @param  array<string, mixed>  $final
     * @return array<string, mixed>
     *
     * @throws ArgumentoInvalido
     */
    private function validarFinal(User $usuario, array $final, ?Model $alvo): array
    {
        try {
            return $this->cadastros->validar($this->entidade(), $usuario, $final, $alvo);
        } catch (ValidationException $erro) {
            throw new ArgumentoInvalido(collect($erro->errors())->flatten()->first());
        }
    }

    /**
     * Uma linha por campo: na criação, todo campo enviado (antes null); na alteração, só o que mudou.
     *
     * @param  array<string, mixed>  $atual
     * @param  array<string, mixed>  $final
     * @return list<array{rotulo: string, antes: ?string, depois: ?string}>
     */
    private function linhasDosCampos(array $atual, array $final, bool $criacao): array
    {
        $linhas = [];

        foreach ($this->campos() as $campo => $definicao) {
            if (! array_key_exists($campo, $final)) {
                continue;
            }

            $depois = $this->formatar($definicao['tipo'], $final[$campo]);

            if ($criacao) {
                $linhas[] = ['rotulo' => $definicao['rotulo'], 'antes' => null, 'depois' => $depois];

                continue;
            }

            $antes = $this->formatar($definicao['tipo'], $atual[$campo] ?? null);

            if ($antes !== $depois) {
                $linhas[] = ['rotulo' => $definicao['rotulo'], 'antes' => $antes, 'depois' => $depois];
            }
        }

        return $linhas;
    }

    private function formatar(string $tipo, mixed $valor): string
    {
        $valor = $valor instanceof BackedEnum ? $valor->value : $valor;

        return match (true) {
            $valor === null => '—',
            $tipo === 'booleano' => $valor ? 'Sim' : 'Não',
            $tipo === 'data' => Carbon::createFromFormat('Y-m-d', (string) $valor)->format('d/m/Y'),
            $tipo === 'lista' => $valor === [] ? '—' : implode(', ', $valor),
            default => (string) $valor,
        };
    }

    private function verboDoTitulo(string $operacao): string
    {
        return match ($operacao) {
            'criar' => 'Novo',
            'alterar' => 'Alterar',
            default => 'Excluir',
        };
    }

    private function verboMinusculo(string $operacao): string
    {
        return match ($operacao) {
            'criar' => 'criar',
            'alterar' => 'alterar',
            default => 'excluir',
        };
    }
}
