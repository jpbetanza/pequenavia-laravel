<?php

namespace App\Services\Assistente\Ferramentas;

use App\Enums\CorDoEspaco;
use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use App\Models\EspacoHorario;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEspaco;
use App\Services\Assistente\Cadastros\CadastrosDoAssistente;
use App\Services\Assistente\Cadastros\MescladorDeHorarios;
use App\Services\Assistente\ResumoDaAcao;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Prepara criar, alterar ou excluir um espaço, com horários e bloqueios, para a secretaria confirmar. Nada é gravado aqui.
 *
 * O estado final é completo (campos, horários e bloqueios), porque a gravação substitui horários e bloqueios.
 */
final class ProporEspaco extends PropoeCadastro
{
    /** Valores de um espaço novo quando o modelo não envia o campo. Espelham os padrões da tabela. */
    private const PADROES_DE_CRIACAO = [
        'capacidade' => null,
        'localizacao' => null,
        'cor' => 'bordo',
        'recursos_fixos' => [],
        'aceita_solicitacoes' => true,
        'antecedencia_min_horas' => 0,
        'duracao_max_min' => null,
        'intervalo_min' => 0,
        'ativo' => true,
    ];

    public function __construct(
        ResolvedorDeCadastros $resolvedor,
        ResumoDaAcao $resumo,
        CadastrosDoAssistente $cadastros,
        private readonly GravadorDeEspaco $gravador,
    ) {
        parent::__construct($resolvedor, $resumo, $cadastros);
    }

    public function nome(): string
    {
        return 'propor_espaco';
    }

    public function descricao(): string
    {
        return 'Prepara criar, alterar ou excluir um espaço, com horários e bloqueios. Nada é gravado: a secretaria precisa clicar em Confirmar. Para alterar ou excluir, informe o espaço pelo nome ou id e envie só o que muda: horários enviados por dia substituem só esse dia, e bloqueios são somados ou removidos. Espaço com reservas não pode ser excluído.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operacao' => ['type' => 'string', 'enum' => ['criar', 'alterar', 'excluir'], 'description' => 'Criar um espaço novo, alterar um existente ou excluir um existente.'],
                'alvo' => ['type' => 'string', 'description' => 'Nome ou id do espaço que será alterado ou excluído. Obrigatório em alterar e excluir.'],
                'nome' => ['type' => 'string', 'description' => 'Nome do espaço, até 120 caracteres. Obrigatório ao criar.'],
                'capacidade' => ['type' => 'integer', 'description' => 'Capacidade em pessoas, de 1 a 2000. Opcional.'],
                'localizacao' => ['type' => 'string', 'description' => 'Localização, até 160 caracteres. Opcional.'],
                'cor' => ['type' => 'string', 'enum' => array_column(CorDoEspaco::cases(), 'value'), 'description' => 'Cor de identificação. Se não informar ao criar, usa bordo.'],
                'recursos_fixos' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Recursos fixos do espaço, como "Piano" ou "Projetor". Envie a lista completa ao alterar.'],
                'aceita_solicitacoes' => ['type' => 'boolean', 'description' => 'Se o espaço aparece no formulário de solicitação. Opcional.'],
                'antecedencia_min_horas' => ['type' => 'integer', 'description' => 'Antecedência mínima em horas, de 0 a 10080. Opcional.'],
                'duracao_max_min' => ['type' => 'integer', 'description' => 'Duração máxima de uma reserva em minutos. Vazio para não limitar. Opcional.'],
                'intervalo_min' => ['type' => 'integer', 'description' => 'Intervalo entre reservas em minutos, de 0 a 10080. Opcional.'],
                'ativo' => ['type' => 'boolean', 'description' => 'Se o espaço está ativo. Opcional.'],
                'horarios' => [
                    'type' => 'array',
                    'description' => 'Mudanças por dia da semana. Os dias que não aparecem continuam como estão.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'dia' => ['type' => 'string', 'description' => 'Dia da semana: domingo, segunda, terca, quarta, quinta, sexta ou sabado.'],
                            'abre' => ['type' => 'string', 'description' => 'Horário de abertura no formato HH:MM. Pode faltar se o dia já existe.'],
                            'fecha' => ['type' => 'string', 'description' => 'Horário de fechamento no formato HH:MM. Pode faltar se o dia já existe.'],
                            'fechado' => ['type' => 'boolean', 'description' => 'true para o espaço fechar nesse dia.'],
                        ],
                        'required' => ['dia'],
                    ],
                ],
                'adicionar_bloqueios' => [
                    'type' => 'array',
                    'description' => 'Bloqueios novos (período em que o espaço não recebe reservas).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'inicio' => ['type' => 'string', 'description' => 'Primeiro dia do bloqueio, no formato AAAA-MM-DD.'],
                            'fim' => ['type' => 'string', 'description' => 'Último dia do bloqueio, no formato AAAA-MM-DD.'],
                            'motivo' => ['type' => 'string', 'description' => 'Motivo, até 160 caracteres.'],
                        ],
                        'required' => ['inicio', 'fim', 'motivo'],
                    ],
                ],
                'remover_bloqueios' => [
                    'type' => 'array',
                    'description' => 'Bloqueios a remover. Informe o motivo, a data de início, ou os dois.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'inicio' => ['type' => 'string', 'description' => 'Data de início do bloqueio, no formato AAAA-MM-DD.'],
                            'motivo' => ['type' => 'string', 'description' => 'Motivo do bloqueio, sem diferenciar maiúsculas e acentos.'],
                        ],
                    ],
                ],
            ],
            'required' => ['operacao'],
        ];
    }

    public function status(): string
    {
        return 'Preparando o cadastro…';
    }

    protected function entidade(): string
    {
        return 'espaco';
    }

    protected function nomeNaFrase(): string
    {
        return 'espaço';
    }

    protected function campos(): array
    {
        return [
            'nome' => ['rotulo' => 'Nome', 'tipo' => 'texto'],
            'capacidade' => ['rotulo' => 'Capacidade', 'tipo' => 'inteiro'],
            'localizacao' => ['rotulo' => 'Localização', 'tipo' => 'texto'],
            'cor' => ['rotulo' => 'Cor', 'tipo' => 'texto'],
            'recursos_fixos' => ['rotulo' => 'Recursos', 'tipo' => 'lista'],
            'aceita_solicitacoes' => ['rotulo' => 'Aceita solicitações', 'tipo' => 'booleano'],
            'antecedencia_min_horas' => ['rotulo' => 'Antecedência (horas)', 'tipo' => 'inteiro'],
            'duracao_max_min' => ['rotulo' => 'Duração máxima (min)', 'tipo' => 'inteiro'],
            'intervalo_min' => ['rotulo' => 'Intervalo (min)', 'tipo' => 'inteiro'],
            'ativo' => ['rotulo' => 'Ativo', 'tipo' => 'booleano'],
        ];
    }

    protected function montarFinal(?Model $alvo, array $atual, array $enviados, array $argumentos): array
    {
        $final = array_replace($alvo === null ? self::PADROES_DE_CRIACAO : $atual, $enviados);

        $final['horarios'] = MescladorDeHorarios::mesclar(
            $this->horariosAtuais($alvo),
            $this->listaDe($argumentos, 'horarios'),
        );

        $bloqueios = $this->removerBloqueios($this->bloqueiosAtuais($alvo), $this->listaDe($argumentos, 'remover_bloqueios'));

        $final['bloqueios'] = [
            ...$bloqueios,
            ...array_map(fn (array $bloqueio) => [
                'inicio' => $bloqueio['inicio'] ?? null,
                'fim' => $bloqueio['fim'] ?? null,
                'motivo' => $bloqueio['motivo'] ?? null,
            ], $this->listaDe($argumentos, 'adicionar_bloqueios')),
        ];

        return $final;
    }

    protected function linhasExtras(?Model $alvo, array $final, bool $criacao): array
    {
        if ($criacao) {
            return [
                ['rotulo' => 'Horários', 'antes' => null, 'depois' => MescladorDeHorarios::resumo($final['horarios'])],
                ...array_map(fn (array $bloqueio) => ['rotulo' => 'Bloqueios', 'antes' => null, 'depois' => '+ '.$this->descreverBloqueio($bloqueio)], $final['bloqueios']),
            ];
        }

        $atuaisBloqueios = $this->bloqueiosAtuais($alvo);
        $chavesAtuais = array_map($this->chaveDoBloqueio(...), $atuaisBloqueios);
        $chavesFinais = array_map($this->chaveDoBloqueio(...), $final['bloqueios']);

        return [
            ...MescladorDeHorarios::linhas($this->horariosAtuais($alvo), $final['horarios']),
            ...array_map(fn (array $bloqueio) => ['rotulo' => 'Bloqueios', 'antes' => null, 'depois' => '+ '.$this->descreverBloqueio($bloqueio)],
                array_values(array_filter($final['bloqueios'], fn (array $bloqueio) => ! in_array($this->chaveDoBloqueio($bloqueio), $chavesAtuais, true)))),
            ...array_map(fn (array $bloqueio) => ['rotulo' => 'Bloqueios', 'antes' => null, 'depois' => '− '.$this->descreverBloqueio($bloqueio)],
                array_values(array_filter($atuaisBloqueios, fn (array $bloqueio) => ! in_array($this->chaveDoBloqueio($bloqueio), $chavesFinais, true)))),
        ];
    }

    protected function resolverAlvo(User $usuario, string $idOuNome): Model
    {
        return $this->resolvedor->espaco($usuario, $idOuNome);
    }

    protected function avisosDaExclusao(Model $alvo): array
    {
        assert($alvo instanceof Espaco);

        $motivo = $this->gravador->motivoParaNaoExcluir($alvo);

        if ($motivo !== null) {
            throw new ArgumentoInvalido($motivo);
        }

        return [];
    }

    /**
     * @return list<array{dia_semana: int, abre: string, fecha: string}>
     */
    private function horariosAtuais(?Model $alvo): array
    {
        if (! $alvo instanceof Espaco) {
            return [];
        }

        return $alvo->horarios
            ->map(fn (EspacoHorario $horario) => [
                'dia_semana' => (int) $horario->dia_semana,
                'abre' => substr((string) $horario->abre, 0, 5),
                'fecha' => substr((string) $horario->fecha, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{inicio: string, fim: string, motivo: string}>
     */
    private function bloqueiosAtuais(?Model $alvo): array
    {
        if (! $alvo instanceof Espaco) {
            return [];
        }

        return $alvo->bloqueios
            ->map(fn (EspacoBloqueio $bloqueio) => [
                'inicio' => $bloqueio->inicio->toDateString(),
                'fim' => $bloqueio->fim->toDateString(),
                'motivo' => $bloqueio->motivo,
            ])
            ->values()
            ->all();
    }

    /**
     * Tira os bloqueios pedidos. Cada pedido precisa achar exatamente um bloqueio atual.
     *
     * @param  list<array{inicio: string, fim: string, motivo: string}>  $atuais
     * @param  list<array<string, mixed>>  $remocoes
     * @return list<array{inicio: string, fim: string, motivo: string}>
     *
     * @throws ArgumentoInvalido
     */
    private function removerBloqueios(array $atuais, array $remocoes): array
    {
        foreach ($remocoes as $remocao) {
            $inicio = trim((string) ($remocao['inicio'] ?? '')) ?: null;
            $motivo = trim((string) ($remocao['motivo'] ?? '')) ?: null;

            if ($inicio === null && $motivo === null) {
                throw new ArgumentoInvalido('Informe o motivo ou a data de início do bloqueio a remover.');
            }

            $combinam = array_keys(array_filter($atuais, fn (array $bloqueio) => ($inicio === null || $bloqueio['inicio'] === $inicio)
                && ($motivo === null || $this->normalizarTexto($bloqueio['motivo']) === $this->normalizarTexto($motivo))));

            if ($combinam === []) {
                throw new ArgumentoInvalido('Não encontrei esse bloqueio no espaço.');
            }

            if (count($combinam) > 1) {
                throw new ArgumentoInvalido('Mais de um bloqueio combina com isso. Informe também a data de início.');
            }

            unset($atuais[$combinam[0]]);
        }

        return array_values($atuais);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ArgumentoInvalido
     */
    private function listaDe(array $argumentos, string $campo): array
    {
        $valor = $argumentos[$campo] ?? [];

        if (! is_array($valor) || array_filter($valor, fn (mixed $item) => ! is_array($item)) !== []) {
            throw new ArgumentoInvalido("O campo {$campo} precisa ser uma lista de objetos.");
        }

        return array_values($valor);
    }

    /**
     * @param  array<string, mixed>  $bloqueio
     */
    private function chaveDoBloqueio(array $bloqueio): string
    {
        return $bloqueio['inicio'].'|'.$bloqueio['fim'].'|'.$this->normalizarTexto((string) $bloqueio['motivo']);
    }

    /**
     * @param  array<string, mixed>  $bloqueio
     */
    private function descreverBloqueio(array $bloqueio): string
    {
        $inicio = Carbon::createFromFormat('Y-m-d', (string) $bloqueio['inicio'])->format('d/m/Y');
        $fim = Carbon::createFromFormat('Y-m-d', (string) $bloqueio['fim'])->format('d/m/Y');

        return "{$inicio} a {$fim} ({$bloqueio['motivo']})";
    }

    private function normalizarTexto(string $texto): string
    {
        return Str::lower(Str::ascii(trim($texto)));
    }
}
