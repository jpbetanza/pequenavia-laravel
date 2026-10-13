<?php

namespace App\Services\Assistente\Ferramentas;

use App\Enums\EscopoDaReserva;
use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Agenda\CriadorDeReservas;
use App\Services\Agenda\EdicaoDeReservas;
use App\Services\Agenda\PivotDoEquipamento;
use App\Services\Assistente\Operacoes\ImpressaoDaAgenda;
use App\Services\Assistente\ResumoDaAcao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prepara a alteração ou o cancelamento de uma reserva que já existe (só ela, ou ela e as próximas da série).
 * Nada é gravado aqui: a secretaria confirma no cartão. A alteração é conferida com a mesma verificação do calendário;
 * com conflito, devolve os conflitos ao modelo e não propõe.
 */
final class ProporMudancaNaReserva implements Ferramenta
{
    use PropoeOperacao;
    use ValidaArgumentos;

    public function __construct(
        private readonly ResolvedorDeCadastros $resolvedor,
        private readonly CriadorDeReservas $criador,
        private readonly EdicaoDeReservas $edicao,
        private readonly ResumoDaAcao $resumo,
    ) {}

    public function nome(): string
    {
        return 'propor_mudanca_na_reserva';
    }

    public function descricao(): string
    {
        return 'Prepara a alteração ou o cancelamento de uma reserva que já existe, para a secretaria confirmar. Use o id que veio de consultar_agenda. Na alteração, envie só os campos que mudam.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reserva' => ['type' => 'string', 'description' => 'Id da reserva (de consultar_agenda).'],
                'operacao' => ['type' => 'string', 'enum' => ['alterar', 'cancelar']],
                'escopo' => ['type' => 'string', 'enum' => ['so_esta', 'esta_e_proximas'], 'description' => 'Para reservas de uma série (serie: true): só esta, ou esta e as próximas da série. Padrão: so_esta.'],
                'titulo' => ['type' => 'string', 'description' => 'Novo nome da reserva, até 160 caracteres.'],
                'espaco' => ['type' => 'string', 'description' => 'Novo espaço, nome ou id.'],
                'grupo' => ['type' => 'string', 'description' => 'Novo grupo, nome ou id. Envie texto vazio para tirar o grupo.'],
                'data' => ['type' => 'string', 'description' => 'Nova data, AAAA-MM-DD. Não pode ser no passado.'],
                'inicio' => ['type' => 'string', 'description' => 'Novo horário de início, HH:MM.'],
                'fim' => ['type' => 'string', 'description' => 'Novo horário de término, HH:MM.'],
                'pessoas' => ['type' => 'integer', 'description' => 'Novo número de pessoas.'],
                'observacoes' => ['type' => 'string', 'description' => 'Novas observações, até 2000 caracteres. Texto vazio apaga.'],
                'equipamentos' => [
                    'type' => 'array',
                    'description' => 'Lista completa de equipamentos que a reserva passa a ter (substitui a atual; lista vazia tira todos). Até 10.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'equipamento' => ['type' => 'string', 'description' => 'Nome ou id do equipamento.'],
                            'quantidade' => ['type' => 'integer', 'description' => 'Quantas unidades.'],
                        ],
                        'required' => ['equipamento', 'quantidade'],
                    ],
                ],
            ],
            'required' => ['reserva', 'operacao'],
        ];
    }

    public function status(): string
    {
        return 'Preparando a mudança na reserva…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $hoje = CarbonImmutable::now(config('agendamentos.fuso'))->toDateString();

        $dados = $this->validar($argumentos, [
            'reserva' => ['required', 'string'],
            'operacao' => ['required', 'in:alterar,cancelar'],
            'escopo' => ['nullable', 'in:so_esta,esta_e_proximas'],
            'titulo' => ['nullable', 'string', 'max:160'],
            'espaco' => ['nullable', 'string'],
            'grupo' => ['nullable', 'string'],
            'data' => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$hoje}"],
            'inicio' => ['nullable', 'date_format:H:i'],
            'fim' => ['nullable', 'date_format:H:i'],
            'pessoas' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'equipamentos' => ['nullable', 'array', 'max:10'],
            'equipamentos.*.equipamento' => ['required', 'string'],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $reserva = $this->resolvedor->reserva($usuario, $dados['reserva']);
        $reserva->load(['espaco', 'grupo', 'equipamentos']);
        $escopo = $reserva->serie_id === null ? EscopoDaReserva::SoEsta : EscopoDaReserva::from($dados['escopo'] ?? EscopoDaReserva::SoEsta->value);
        $membros = $this->edicao->membrosDaSerie($usuario, $reserva, $escopo);
        $avisos = $this->avisoDaSerie($membros);

        if ($dados['operacao'] === 'cancelar') {
            return $this->cancelar($usuario, $conversa, $reserva, $escopo, $avisos);
        }

        return $this->alterar($usuario, $conversa, $reserva, $escopo, $membros, $avisos, $argumentos, $dados);
    }

    /**
     * @param  list<string>  $avisos
     */
    private function cancelar(User $usuario, Conversa $conversa, Reserva $reserva, EscopoDaReserva $escopo, array $avisos): ResultadoDeFerramenta
    {
        $linhas = [
            $this->linha('Data', $this->dataLegivel($reserva->data->toDateString())),
            $this->linha('Horário', $this->horario($reserva->inicio, $reserva->fim)),
            $this->linha('Espaço', $reserva->espaco->nome),
        ];

        if ($reserva->grupo !== null) {
            $linhas[] = $this->linha('Grupo', $reserva->grupo->nome);
        }

        if ($reserva->equipamentos->isNotEmpty()) {
            $linhas[] = $this->linha('Equipamentos', $this->equipamentosEmTexto($reserva->equipamentos));
        }

        foreach ($reserva->equipamentos as $equipamento) {
            if (PivotDoEquipamento::entregueEm($equipamento) !== null && PivotDoEquipamento::recolhidoEm($equipamento) === null) {
                $avisos[] = "{$equipamento->nome} foi entregue e ainda não foi recolhido.";
            }
        }

        if ($reserva->solicitacao_id !== null) {
            $avisos[] = 'Esta reserva veio do formulário público. O sistema não avisa quem pediu.';
        }

        return $this->proporOperacao($usuario, $conversa, 'cancelar_reserva', [
            'titulo' => "Cancelar reserva: {$reserva->titulo}",
            'chave' => "reserva:{$reserva->id}",
            'linhas' => $linhas,
            'avisos' => $avisos,
            'link' => route('agendamentos.calendario', ['data' => $reserva->data->toDateString()], absolute: false),
            'reserva_id' => $reserva->id,
            'escopo' => $escopo->value,
            'impressao' => ImpressaoDaAgenda::de($reserva),
        ]);
    }

    /**
     * @param  Collection<int, Reserva>  $membros
     * @param  list<string>  $avisos
     * @param  array<string, mixed>  $argumentos  argumentos crus, para saber o que foi enviado (inclusive vazio)
     * @param  array<string, mixed>  $dados  argumentos validados
     */
    private function alterar(User $usuario, Conversa $conversa, Reserva $reserva, EscopoDaReserva $escopo, Collection $membros, array $avisos, array $argumentos, array $dados): ResultadoDeFerramenta
    {
        $atual = [
            'titulo' => $reserva->titulo,
            'espaco_id' => $reserva->espaco_id,
            'grupo_id' => $reserva->grupo_id,
            'data' => $reserva->data->toDateString(),
            'inicio' => substr($reserva->inicio, 0, 5),
            'fim' => substr($reserva->fim, 0, 5),
            'pessoas' => $reserva->pessoas,
            'observacoes' => $reserva->observacoes,
            'equipamentos' => $reserva->equipamentos
                ->map(fn (Equipamento $equipamento) => ['id' => $equipamento->id, 'quantidade' => PivotDoEquipamento::quantidade($equipamento)])
                ->sortBy('id')
                ->values()
                ->all(),
        ];
        $final = $atual;

        foreach (['titulo', 'data', 'inicio', 'fim'] as $campo) {
            if (filled($dados[$campo] ?? null)) {
                $final[$campo] = $dados[$campo];
            }
        }

        $espaco = filled($dados['espaco'] ?? null) ? $this->resolvedor->espaco($usuario, $dados['espaco']) : $reserva->espaco;
        $final['espaco_id'] = $espaco->id;

        if (array_key_exists('grupo', $argumentos)) {
            $final['grupo_id'] = filled($dados['grupo'] ?? null) ? $this->resolvedor->grupo($usuario, $dados['grupo'])?->id : null;
        }

        if (array_key_exists('pessoas', $argumentos)) {
            $final['pessoas'] = $dados['pessoas'] ?? null;
        }

        if (array_key_exists('observacoes', $argumentos)) {
            $final['observacoes'] = trim((string) ($dados['observacoes'] ?? '')) ?: null;
        }

        if (isset($dados['equipamentos'])) {
            /** @var list<array{equipamento: string, quantidade: int}> $pedidos */
            $pedidos = $dados['equipamentos'];
            // Mesmo equipamento pedido de duas formas (nome e id) vira um item só, com a soma das quantidades.
            $final['equipamentos'] = collect($pedidos)
                ->map(fn (array $pedido) => [
                    'id' => $this->resolvedor->equipamento($usuario, $pedido['equipamento'])->id,
                    'quantidade' => (int) $pedido['quantidade'],
                ])
                ->groupBy('id')
                ->map(fn ($pedidos, string $id) => ['id' => $id, 'quantidade' => $pedidos->sum('quantidade')])
                ->sortBy('id')
                ->values()
                ->all();
        }

        if ($final['fim'] <= $final['inicio']) {
            throw new ArgumentoInvalido('O horário final precisa ser depois do horário inicial.');
        }

        $linhas = $this->linhasDaAlteracao($reserva, $atual, $final);

        if ($linhas === []) {
            throw new ArgumentoInvalido('Nada para alterar: os valores informados já são os atuais.');
        }

        $conflitos = $this->conflitos($espaco, $reserva, $membros, $final);

        if ($conflitos !== []) {
            return new ResultadoDeFerramenta([
                'criada' => false,
                'conflitos' => $conflitos,
                'mensagem' => 'Com esses conflitos a reserva não pode ser alterada assim. Sugira outro horário ou espaço.',
            ]);
        }

        if (! collect($linhas)->contains(fn (array $linha) => in_array($linha['rotulo'], ['Data', 'Horário'], true))) {
            array_unshift($linhas, $this->linha('Quando', $this->dataLegivel($atual['data']).', '.$this->horario($atual['inicio'], $atual['fim'])));
        }

        if ($final['data'] !== $atual['data'] && $membros->count() > 1) {
            $avisos[] = 'A data muda só nesta reserva; as próximas da série mantêm as suas datas.';
        }

        return $this->proporOperacao($usuario, $conversa, 'alterar_reserva', [
            'titulo' => "Alterar reserva: {$reserva->titulo}",
            'chave' => "reserva:{$reserva->id}",
            'linhas' => $linhas,
            'avisos' => $avisos,
            'link' => route('agendamentos.calendario', ['data' => $final['data']], absolute: false),
            'reserva_id' => $reserva->id,
            'escopo' => $escopo->value,
            'final' => $final,
            'impressao' => ImpressaoDaAgenda::de($reserva),
        ]);
    }

    /**
     * Uma linha por campo que muda, com o valor atual riscado.
     *
     * @param  array<string, mixed>  $atual
     * @param  array<string, mixed>  $final
     * @return list<array{rotulo: string, antes: ?string, depois: ?string}>
     */
    private function linhasDaAlteracao(Reserva $reserva, array $atual, array $final): array
    {
        $nomeDoEspaco = fn (string $id) => Espaco::query()->whereKey($id)->value('nome');
        $nomeDoGrupo = fn (?string $id) => $id === null ? null : Grupo::query()->whereKey($id)->value('nome');
        $equipamentos = function (array $lista): ?string {
            $nomes = Equipamento::query()->whereIn('id', array_column($lista, 'id'))->pluck('nome', 'id');

            return $this->itensEmTexto(array_map(
                fn (array $pedido) => ['nome' => (string) ($nomes[$pedido['id']] ?? 'Equipamento removido'), 'quantidade' => (int) $pedido['quantidade']],
                $lista,
            ));
        };

        $comparacoes = [
            'Título' => [$atual['titulo'], $final['titulo']],
            'Espaço' => [$reserva->espaco->nome, $nomeDoEspaco($final['espaco_id'])],
            'Grupo' => [$reserva->grupo?->nome, $nomeDoGrupo($final['grupo_id'])],
            'Data' => [$this->dataLegivel($atual['data']), $this->dataLegivel($final['data'])],
            'Horário' => [$this->horario($atual['inicio'], $atual['fim']), $this->horario($final['inicio'], $final['fim'])],
            'Pessoas' => [$atual['pessoas'] === null ? null : (string) $atual['pessoas'], $final['pessoas'] === null ? null : (string) $final['pessoas']],
            'Observações' => [$atual['observacoes'], $final['observacoes']],
            'Equipamentos' => [$equipamentos($atual['equipamentos']), $equipamentos($final['equipamentos'])],
        ];

        $linhas = [];

        foreach ($comparacoes as $rotulo => [$antes, $depois]) {
            if ($antes !== $depois) {
                $linhas[] = ['rotulo' => $rotulo, 'antes' => $antes ?? '—', 'depois' => $depois];
            }
        }

        return $linhas;
    }

    /**
     * Conflitos de cada reserva atingida, como o calendário confere: a data nova vale só para a reserva escolhida.
     *
     * @param  Collection<int, Reserva>  $membros
     * @param  array<string, mixed>  $final
     * @return list<array{data: string, conflitos: list<array{tipo: string, mensagem: string}>}>
     */
    private function conflitos(Espaco $espaco, Reserva $reserva, Collection $membros, array $final): array
    {
        $conflitos = [];

        foreach ($membros as $membro) {
            $data = $membro->is($reserva) ? $final['data'] : $membro->data->toDateString();
            $daData = $this->criador->conflitosDaData(
                $espaco,
                CarbonImmutable::createFromFormat('Y-m-d', $data, config('agendamentos.fuso'))->startOfDay(),
                ['inicio' => $final['inicio'], 'fim' => $final['fim']],
                $final['equipamentos'],
                $membro->id,
                ignorarEspacoInativo: $membro->espaco_id === $espaco->id,
            );

            if ($daData !== null) {
                $conflitos[] = $daData;
            }
        }

        return $conflitos;
    }

    /**
     * @param  Collection<int, Reserva>  $membros
     * @return list<string>
     */
    private function avisoDaSerie(Collection $membros): array
    {
        $proximas = $membros->count() - 1;

        if ($proximas === 0) {
            return [];
        }

        $ate = $this->dataLegivel($membros->last()->data->toDateString());

        return [$proximas === 1
            ? "Vale também para a próxima reserva da série ({$ate})."
            : "Vale também para as próximas {$proximas} reservas da série, até {$ate}."];
    }
}
