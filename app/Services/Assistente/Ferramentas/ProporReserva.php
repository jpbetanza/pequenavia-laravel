<?php

namespace App\Services\Assistente\Ferramentas;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use App\Services\Agenda\VerificadorDeDisponibilidade;
use App\Services\Assistente\Cadastros\ChaveDaProposta;
use App\Services\Assistente\Cadastros\ExpiracaoDePropostas;
use App\Services\Assistente\ResumoDaAcao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Prepara uma reserva para a secretaria confirmar. Nada é gravado aqui: a reserva só existe depois do clique em Confirmar.
 * Propor uma reserva nova expira a reserva pendente anterior da mesma conversa; cadastros pendentes ficam.
 */
final class ProporReserva implements Ferramenta
{
    use ValidaArgumentos;

    public function __construct(
        private readonly ResolvedorDeCadastros $resolvedor,
        private readonly VerificadorDeDisponibilidade $verificador,
        private readonly ResumoDaAcao $resumo,
    ) {}

    public function nome(): string
    {
        return 'propor_reserva';
    }

    public function descricao(): string
    {
        return 'Prepara uma reserva para a secretaria confirmar. Nada é gravado até ela clicar em Confirmar. Use somente quando o usuário pedir para reservar ou marcar um espaço.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'titulo' => ['type' => 'string', 'description' => 'Nome da reserva, até 160 caracteres.'],
                'espaco' => ['type' => 'string', 'description' => 'Nome ou id do espaço.'],
                'grupo' => ['type' => 'string', 'description' => 'Pastoral ou grupo que vai usar o espaço; use este campo, não observações. Nome ou id. Opcional.'],
                'data' => ['type' => 'string', 'description' => 'Dia, no formato AAAA-MM-DD. Não pode ser no passado.'],
                'inicio' => ['type' => 'string', 'description' => 'Horário de início, HH:MM.'],
                'fim' => ['type' => 'string', 'description' => 'Horário de término, HH:MM. Depois do início.'],
                'pessoas' => ['type' => 'integer', 'description' => 'Número de pessoas. Opcional.'],
                'equipamentos' => [
                    'type' => 'array',
                    'description' => 'Equipamentos pedidos. Opcional, até 10.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'equipamento' => ['type' => 'string', 'description' => 'Nome ou id do equipamento.'],
                            'quantidade' => ['type' => 'integer', 'description' => 'Quantas unidades.'],
                        ],
                        'required' => ['equipamento', 'quantidade'],
                    ],
                ],
                'observacoes' => ['type' => 'string', 'description' => 'Observações. Opcional, até 1000 caracteres.'],
            ],
            'required' => ['titulo', 'espaco', 'data', 'inicio', 'fim'],
        ];
    }

    public function status(): string
    {
        return 'Preparando a reserva…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $fuso = config('agendamentos.fuso');
        $hoje = CarbonImmutable::now($fuso)->toDateString();

        $dados = $this->validar($argumentos, [
            'titulo' => ['required', 'string', 'max:160'],
            'espaco' => ['required', 'string'],
            'grupo' => ['nullable', 'string'],
            'data' => ['required', 'date_format:Y-m-d', "after_or_equal:{$hoje}"],
            'inicio' => ['required', 'date_format:H:i'],
            'fim' => ['required', 'date_format:H:i', 'after:inicio'],
            'pessoas' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'equipamentos' => ['nullable', 'array', 'max:10'],
            'equipamentos.*.equipamento' => ['required', 'string'],
            'equipamentos.*.quantidade' => ['required', 'integer', 'min:1', 'max:1000'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $espaco = $this->resolvedor->espaco($usuario, $dados['espaco']);
        $grupo = $this->resolvedor->grupo($usuario, $dados['grupo'] ?? null);
        // Mesmo equipamento pedido de duas formas (nome e id) vira um item só, com a soma das quantidades.
        $equipamentos = collect($dados['equipamentos'] ?? [])
            ->map(fn (array $pedido) => [
                'id' => $this->resolvedor->equipamento($usuario, $pedido['equipamento'])->id,
                'quantidade' => (int) $pedido['quantidade'],
            ])
            ->groupBy('id')
            ->map(fn ($pedidos, string $id) => ['id' => $id, 'quantidade' => $pedidos->sum('quantidade')])
            ->values()
            ->all();
        $data = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'], $fuso)->startOfDay();

        $conflitos = $this->verificador->verificar($espaco, $data, $dados['inicio'], $dados['fim'], $equipamentos);

        if ($conflitos !== []) {
            return new ResultadoDeFerramenta([
                'criada' => false,
                'conflitos' => array_map(fn ($conflito) => $conflito->toArray(), $conflitos),
                'sugestoes' => $this->verificador->sugestoes($espaco, $data, $dados['inicio'], $dados['fim'], $equipamentos, null, $dados['pessoas'] ?? null),
            ]);
        }

        $dadosDaReserva = [
            'titulo' => $dados['titulo'],
            'espaco_id' => $espaco->id,
            'grupo_id' => $grupo?->id,
            'data' => $data->toDateString(),
            'inicio' => $dados['inicio'],
            'fim' => $dados['fim'],
            'pessoas' => $dados['pessoas'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
            'equipamentos' => $equipamentos,
        ];

        ['acao' => $acao, 'substituiu' => $substituiu] = DB::transaction(function () use ($usuario, $conversa, $dadosDaReserva): array {
            $pedido = ExpiracaoDePropostas::pedidoAtual($conversa);
            $substituiu = ExpiracaoDePropostas::expirarPendentesComMesmaChave($conversa, ChaveDaProposta::de('criar_reserva', $dadosDaReserva), $pedido);

            $acao = AcaoProposta::create([
                'conversa_id' => $conversa->id,
                'user_id' => $usuario->id,
                'pedido_id' => $pedido,
                'tipo' => 'criar_reserva',
                'dados' => $dadosDaReserva,
                'status' => StatusDaAcao::Proposta,
            ]);

            return ['acao' => $acao, 'substituiu' => $substituiu];
        });

        return new ResultadoDeFerramenta(
            paraOModelo: [
                'criada' => true,
                'acao_id' => $acao->id,
                'mensagem' => 'Proposta pronta. A secretaria precisa clicar em Confirmar reserva.',
                ...($substituiu !== [] ? ['substituiu' => $substituiu] : []),
            ],
            cartao: ['tipo' => 'acao_proposta', 'dados' => $this->resumo->paraCartao($acao)],
        );
    }
}
