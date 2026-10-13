<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\User;
use App\Services\Agenda\PivotDoEquipamento;
use App\Services\Assistente\ResumoDaAcao;

/**
 * Prepara a marcação de um equipamento de uma reserva como entregue ou recolhido, como na tela de equipamentos.
 * Nada é gravado aqui: a secretaria confirma no cartão. Para vários equipamentos, uma chamada por equipamento.
 */
final class MarcarEquipamento implements Ferramenta
{
    use PropoeOperacao;
    use ValidaArgumentos;

    public function __construct(
        private readonly ResolvedorDeCadastros $resolvedor,
        private readonly ResumoDaAcao $resumo,
    ) {}

    public function nome(): string
    {
        return 'marcar_equipamento';
    }

    public function descricao(): string
    {
        return 'Prepara a marcação de um equipamento de uma reserva como entregue (saiu para o grupo) ou recolhido (voltou), para a secretaria confirmar. Use o id da reserva que veio de consultar_agenda. Uma chamada por equipamento.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reserva' => ['type' => 'string', 'description' => 'Id da reserva (de consultar_agenda).'],
                'equipamento' => ['type' => 'string', 'description' => 'Nome ou id do equipamento da reserva.'],
                'acao' => ['type' => 'string', 'enum' => ['entregue', 'recolhido']],
            ],
            'required' => ['reserva', 'equipamento', 'acao'],
        ];
    }

    public function status(): string
    {
        return 'Preparando a marcação do equipamento…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $dados = $this->validar($argumentos, [
            'reserva' => ['required', 'string'],
            'equipamento' => ['required', 'string'],
            'acao' => ['required', 'in:entregue,recolhido'],
        ]);

        $reserva = $this->resolvedor->reserva($usuario, $dados['reserva']);
        $reserva->load('espaco');
        $cadastro = $this->resolvedor->equipamento($usuario, $dados['equipamento']);
        $equipamento = $reserva->equipamentos()->whereKey($cadastro->id)->first()
            ?? throw new ArgumentoInvalido("A reserva “{$reserva->titulo}” não tem o equipamento “{$cadastro->nome}”.");

        $entregar = $dados['acao'] === 'entregue';
        $jaMarcado = $entregar ? PivotDoEquipamento::entregueEm($equipamento) : PivotDoEquipamento::recolhidoEm($equipamento);

        if ($jaMarcado !== null) {
            throw new ArgumentoInvalido("{$equipamento->nome} já foi marcado como {$dados['acao']} em {$this->momento($jaMarcado)}.");
        }

        $avisos = ! $entregar && PivotDoEquipamento::entregueEm($equipamento) === null
            ? ["{$equipamento->nome} ainda não foi marcado como entregue."]
            : [];
        $quantidade = PivotDoEquipamento::quantidade($equipamento);

        return $this->proporOperacao($usuario, $conversa, $entregar ? 'entregar_equipamento' : 'recolher_equipamento', [
            'titulo' => ($entregar ? 'Entregar' : 'Recolher')." {$equipamento->nome} ({$quantidade}): {$reserva->titulo}",
            'chave' => "equipamento:{$reserva->id}:{$equipamento->id}:{$dados['acao']}",
            'linhas' => [
                $this->linha('Data', $this->dataLegivel($reserva->data->toDateString())),
                $this->linha('Horário', $this->horario($reserva->inicio, $reserva->fim)),
                $this->linha('Espaço', $reserva->espaco->nome),
                $this->linha('Equipamento', "{$equipamento->nome} ({$quantidade})"),
            ],
            'avisos' => $avisos,
            'link' => route('agendamentos.equipamentos.index', absolute: false),
            'reserva_id' => $reserva->id,
            'equipamento_id' => $equipamento->id,
        ]);
    }
}
