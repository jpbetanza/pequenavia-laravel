<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\Equipamento;
use App\Models\User;
use App\Services\Agenda\PivotDoEquipamento;
use App\Services\Assistente\Operacoes\Operacoes;
use App\Services\Assistente\Operacoes\PropostaDeOperacao;
use App\Services\Assistente\ResumoDaAcao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * O que as ferramentas de operação sobre a agenda têm em comum: gravar a proposta, devolver o cartão e formatar
 * datas, horários e equipamentos para as linhas do cartão. Quem usa precisa ter `$this->resumo`.
 *
 * @property-read ResumoDaAcao $resumo
 */
trait PropoeOperacao
{
    /**
     * @param  array{titulo: string, chave: string, linhas: list<array{rotulo: string, antes: ?string, depois: ?string}>, avisos: list<string>, link: string}&array<string, mixed>  $dados
     */
    private function proporOperacao(User $usuario, Conversa $conversa, string $tipo, array $dados): ResultadoDeFerramenta
    {
        ['acao' => $acao, 'substituiu' => $substituiu] = PropostaDeOperacao::criar($usuario, $conversa, $tipo, $dados);
        $botao = Operacoes::APRESENTACAO[$tipo]['botao'];

        return new ResultadoDeFerramenta(
            paraOModelo: [
                'criada' => true,
                'acao_id' => $acao->id,
                'mensagem' => "Proposta pronta. A secretaria precisa clicar em “{$botao}”.",
                ...($substituiu !== [] ? ['substituiu' => $substituiu] : []),
            ],
            cartao: ['tipo' => 'acao_proposta', 'dados' => $this->resumo->paraCartao($acao)],
        );
    }

    /**
     * Linha sem "antes", só com o valor.
     *
     * @return array{rotulo: string, antes: null, depois: string}
     */
    private function linha(string $rotulo, ?string $valor): array
    {
        return ['rotulo' => $rotulo, 'antes' => null, 'depois' => $valor ?? '—'];
    }

    /** "sex 16/10/2026". */
    private function dataLegivel(string $data): string
    {
        $dia = CarbonImmutable::createFromFormat('Y-m-d', $data, config('agendamentos.fuso'));
        $dia->locale('pt_BR');

        return $dia->isoFormat('ddd DD/MM/YYYY');
    }

    /** "14:00–17:00". */
    private function horario(string $inicio, string $fim): string
    {
        return substr($inicio, 0, 5).'–'.substr($fim, 0, 5);
    }

    /**
     * "Caixa de som (2), Projetor (1)", em ordem de nome. Sem equipamentos, null.
     *
     * @param  Collection<int, Equipamento>  $equipamentos  com o pivot `quantidade`
     */
    private function equipamentosEmTexto(Collection $equipamentos): ?string
    {
        return $this->itensEmTexto($equipamentos
            ->map(fn (Equipamento $equipamento) => ['nome' => $equipamento->nome, 'quantidade' => PivotDoEquipamento::quantidade($equipamento)])
            ->all());
    }

    /**
     * @param  array<array{nome: string, quantidade: int}>  $itens
     */
    private function itensEmTexto(array $itens): ?string
    {
        if ($itens === []) {
            return null;
        }

        return collect($itens)
            ->sortBy('nome')
            ->map(fn (array $item) => "{$item['nome']} ({$item['quantidade']})")
            ->implode(', ');
    }

    /** Horário gravado em UTC pelo banco, como "16/10 às 14:05" no fuso da agenda. */
    private function momento(string $gravado): string
    {
        return CarbonImmutable::parse($gravado, config('app.timezone'))
            ->setTimezone(config('agendamentos.fuso'))
            ->format('d/m \à\s H:i');
    }
}
