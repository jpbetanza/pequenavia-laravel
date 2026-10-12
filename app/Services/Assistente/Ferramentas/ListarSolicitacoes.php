<?php

namespace App\Services\Assistente\Ferramentas;

use App\Enums\StatusSolicitacao;
use App\Models\Conversa;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\CartaoDeSolicitacao;
use Illuminate\Support\Arr;

/**
 * Lista as solicitações do formulário público do usuário, com o mesmo estado de conflito da tela da secretaria.
 */
final class ListarSolicitacoes implements Ferramenta
{
    use ValidaArgumentos;

    private const LIMITE = 20;

    public function __construct(private readonly CartaoDeSolicitacao $cartao) {}

    public function nome(): string
    {
        return 'listar_solicitacoes';
    }

    public function descricao(): string
    {
        return 'Lista as solicitações recebidas pelo formulário público, com o estado de conflito de cada uma. Padrão: pendentes.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['pendente', 'aprovada', 'recusada'], 'description' => 'Situação das solicitações. Padrão: pendente.'],
            ],
        ];
    }

    public function status(): string
    {
        return 'Conferindo as solicitações…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $dados = $this->validar($argumentos, [
            'status' => ['nullable', 'in:pendente,aprovada,recusada'],
        ]);

        $status = StatusSolicitacao::from($dados['status'] ?? StatusSolicitacao::Pendente->value);

        $solicitacoes = Solicitacao::query()
            ->where('user_id', $usuario->id)
            ->where('status', $status->value)
            ->with(['espaco', 'grupo', 'equipamentos'])
            ->orderBy('data')
            ->orderBy('inicio')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Solicitacao $solicitacao) => [
                ...Arr::only($this->cartao->montar($solicitacao), ['id', 'titulo', 'nome', 'data', 'inicio', 'fim', 'estado']),
                'espaco_nome' => $solicitacao->espaco?->nome,
            ])
            ->values()
            ->all();

        return new ResultadoDeFerramenta(
            paraOModelo: ['solicitacoes' => $solicitacoes],
            cartao: [
                'tipo' => 'solicitacoes',
                'dados' => ['status' => $status->value, 'solicitacoes' => $solicitacoes],
            ],
        );
    }
}
