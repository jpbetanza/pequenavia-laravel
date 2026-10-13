<?php

namespace App\Services\Assistente\Ferramentas;

use App\Enums\StatusSolicitacao;
use App\Models\Conversa;
use App\Models\User;
use App\Services\Agenda\CartaoDeSolicitacao;
use App\Services\Assistente\Operacoes\ImpressaoDaAgenda;
use App\Services\Assistente\ResumoDaAcao;

/**
 * Prepara a aprovação ou a recusa de uma solicitação do formulário público. Nada é gravado aqui: a secretaria confirma no
 * cartão. A aprovação cria a reserva com os dados da solicitação; com conflito, devolve os conflitos ao modelo e não propõe.
 */
final class ResponderSolicitacao implements Ferramenta
{
    use PropoeOperacao;
    use ValidaArgumentos;

    public function __construct(
        private readonly ResolvedorDeCadastros $resolvedor,
        private readonly CartaoDeSolicitacao $cartoes,
        private readonly ResumoDaAcao $resumo,
    ) {}

    public function nome(): string
    {
        return 'responder_solicitacao';
    }

    public function descricao(): string
    {
        return 'Prepara a aprovação ou a recusa de uma solicitação pendente do formulário público, para a secretaria confirmar. Aprovar cria a reserva com os dados da solicitação. Use o id que veio de listar_solicitacoes.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'solicitacao' => ['type' => 'string', 'description' => 'Id da solicitação (de listar_solicitacoes).'],
                'resposta' => ['type' => 'string', 'enum' => ['aprovar', 'recusar']],
                'espaco' => ['type' => 'string', 'description' => 'Só para aprovar: espaço onde a reserva fica, nome ou id. Obrigatório quando a solicitação não tem espaço; se tiver, troca o espaço.'],
                'motivo' => ['type' => 'string', 'description' => 'Só para recusar: motivo, até 200 caracteres. Opcional.'],
            ],
            'required' => ['solicitacao', 'resposta'],
        ];
    }

    public function status(): string
    {
        return 'Preparando a resposta…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $dados = $this->validar($argumentos, [
            'solicitacao' => ['required', 'string'],
            'resposta' => ['required', 'in:aprovar,recusar'],
            'espaco' => ['nullable', 'string'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        $solicitacao = $this->resolvedor->solicitacao($usuario, $dados['solicitacao']);
        $solicitacao->load(['espaco', 'grupo', 'equipamentos']);

        if ($solicitacao->status !== StatusSolicitacao::Pendente) {
            $situacao = $solicitacao->status === StatusSolicitacao::Aprovada ? 'aprovada' : 'recusada';

            throw new ArgumentoInvalido("Esta solicitação já foi {$situacao}.");
        }

        $pedido = [
            $this->linha('Pedido por', "{$solicitacao->nome} · {$solicitacao->telefone}"),
            $this->linha('Data', $this->dataLegivel($solicitacao->data->toDateString())),
            $this->linha('Horário', $this->horario($solicitacao->inicio, $solicitacao->fim)),
        ];
        $chave = "solicitacao:{$solicitacao->id}";

        if ($dados['resposta'] === 'recusar') {
            $motivo = trim((string) ($dados['motivo'] ?? '')) ?: null;

            return $this->proporOperacao($usuario, $conversa, 'recusar_solicitacao', [
                'titulo' => "Recusar solicitação: {$solicitacao->titulo}",
                'chave' => $chave,
                'linhas' => [...$pedido, $this->linha('Motivo', $motivo)],
                'avisos' => ["O sistema não avisa quem pediu: combine com {$solicitacao->nome} pelo telefone."],
                'link' => route('agendamentos.solicitacoes.index', ['status' => 'recusada'], absolute: false),
                'solicitacao_id' => $solicitacao->id,
                'motivo' => $motivo,
            ]);
        }

        $espaco = isset($dados['espaco']) ? $this->resolvedor->espaco($usuario, $dados['espaco']) : $solicitacao->espaco;

        if ($espaco === null) {
            throw new ArgumentoInvalido('A solicitação não tem espaço. Pergunte em qual espaço fica e envie em espaco.');
        }

        $trocaDeEspaco = $espaco->id !== $solicitacao->espaco_id;
        // Os conflitos são os do espaço escolhido, sem gravar a troca: ela só acontece na confirmação.
        $candidata = (clone $solicitacao)->setRelation('espaco', $espaco);
        $candidata->espaco_id = $espaco->id;
        $conflitos = $this->cartoes->conflitos($candidata);

        if ($conflitos !== []) {
            return new ResultadoDeFerramenta([
                'criada' => false,
                'conflitos' => array_map(fn ($conflito) => $conflito->toArray(), $conflitos),
                'mensagem' => 'Com esses conflitos a solicitação não pode ser aprovada. Sugira outro espaço ou peça para a secretaria ajustar a solicitação na tela.',
            ]);
        }

        $linhas = [
            ...$pedido,
            [
                'rotulo' => 'Espaço',
                'antes' => $trocaDeEspaco && $solicitacao->espaco !== null ? $solicitacao->espaco->nome : null,
                'depois' => $espaco->nome,
            ],
        ];

        if ($solicitacao->grupo !== null) {
            $linhas[] = $this->linha('Grupo', $solicitacao->grupo->nome);
        }

        if ($solicitacao->pessoas !== null) {
            $linhas[] = $this->linha('Pessoas', (string) $solicitacao->pessoas);
        }

        if ($solicitacao->equipamentos->isNotEmpty()) {
            $linhas[] = $this->linha('Equipamentos', $this->equipamentosEmTexto($solicitacao->equipamentos));
        }

        return $this->proporOperacao($usuario, $conversa, 'aprovar_solicitacao', [
            'titulo' => "Aprovar solicitação: {$solicitacao->titulo}",
            'chave' => $chave,
            'linhas' => $linhas,
            'avisos' => ["A reserva entra no calendário. O sistema não avisa quem pediu: combine com {$solicitacao->nome} pelo telefone."],
            'link' => route('agendamentos.calendario', ['data' => $solicitacao->data->toDateString()], absolute: false),
            'solicitacao_id' => $solicitacao->id,
            'espaco_id' => $trocaDeEspaco ? $espaco->id : null,
            'impressao' => ImpressaoDaAgenda::de($solicitacao),
        ]);
    }
}
