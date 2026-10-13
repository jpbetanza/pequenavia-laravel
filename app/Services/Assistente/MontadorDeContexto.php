<?php

namespace App\Services\Assistente;

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Monta as mensagens enviadas ao modelo: as instruções do sistema e o histórico recente da conversa no formato OpenAI.
 * Pedido com anexos vira uma lista de partes (texto, blocos `<anexo>`, imagens e PDFs); ver Anexos.
 * O corte nunca começa numa mensagem que não é do usuário, para não separar uma chamada de ferramenta do seu resultado.
 */
final class MontadorDeContexto
{
    public function __construct(private readonly Anexos $anexos) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function montar(User $usuario, Conversa $conversa): array
    {
        $fuso = config('agendamentos.fuso');
        $agora = now($fuso)->locale('pt_BR');

        $instrucoes = view('assistente.instrucoes', [
            'nome' => Str::limit(Str::squish($usuario->name), 80),
            'hoje' => $agora->format('Y-m-d'),
            'diaDaSemana' => $agora->translatedFormat('l'),
            'hora' => $agora->format('H:i'),
            'linkDoFormulario' => route('reservar.create', $usuario->agendaSlug()),
        ])->render();

        $historico = $this->historico($conversa);

        return [
            ['role' => 'system', 'content' => $instrucoes],
            ...$historico->map(fn (Mensagem $mensagem) => $this->paraOModelo($mensagem))->all(),
        ];
    }

    /**
     * @return Collection<int, Mensagem>
     */
    private function historico(Conversa $conversa): Collection
    {
        $recentes = $conversa->mensagens()
            ->reorder()
            ->where('erro', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('assistente.mensagens_no_contexto'))
            ->get()
            ->reverse()
            ->values();

        $inicio = $recentes->search(fn (Mensagem $mensagem) => $mensagem->papel === PapelDaMensagem::Usuario);

        if ($inicio === false) {
            return $this->semChamadasOrfas($this->turnoDoUltimoPedido($conversa));
        }

        return $this->semChamadasOrfas($recentes->slice($inicio)->values());
    }

    /**
     * Quando um único turno (pedido e respostas com ferramentas) passa do limite, o corte ainda começa no pedido,
     * para o modelo não perder a pergunta. Vai do último pedido do usuário até o fim da conversa.
     *
     * @return Collection<int, Mensagem>
     */
    private function turnoDoUltimoPedido(Conversa $conversa): Collection
    {
        $ultimoPedido = $conversa->mensagens()
            ->reorder()
            ->where('papel', PapelDaMensagem::Usuario)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($ultimoPedido === null) {
            return collect();
        }

        return $conversa->mensagens()
            ->reorder()
            ->where('erro', false)
            ->whereRaw('(created_at, id) >= (?, ?)', [$ultimoPedido->created_at, $ultimoPedido->id])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Uma chamada sem resultado de ferramenta (ou um resultado sem chamada) faz o provedor recusar o pedido inteiro.
     * Tira esses pares incompletos do contexto, mesmo que tenham sido gravados por uma interrupção.
     *
     * @param  Collection<int, Mensagem>  $mensagens
     * @return Collection<int, Mensagem>
     */
    private function semChamadasOrfas(Collection $mensagens): Collection
    {
        $comResultado = $mensagens
            ->where('papel', PapelDaMensagem::Ferramenta)
            ->pluck('ferramenta_id')
            ->all();

        $mensagens = $mensagens->reject(fn (Mensagem $mensagem) => $mensagem->papel === PapelDaMensagem::Assistente
            && $mensagem->chamadas !== null
            && collect($mensagem->chamadas)->contains(fn (array $chamada) => ! in_array($chamada['id'], $comResultado, true)))
            ->values();

        $chamadas = $mensagens
            ->filter(fn (Mensagem $mensagem) => $mensagem->papel === PapelDaMensagem::Assistente && $mensagem->chamadas !== null)
            ->flatMap(fn (Mensagem $mensagem) => array_column($mensagem->chamadas, 'id'))
            ->all();

        return $mensagens
            ->reject(fn (Mensagem $mensagem) => $mensagem->papel === PapelDaMensagem::Ferramenta && ! in_array($mensagem->ferramenta_id, $chamadas, true))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function paraOModelo(Mensagem $mensagem): array
    {
        return match ($mensagem->papel) {
            PapelDaMensagem::Usuario => ['role' => 'user', 'content' => $this->anexos->conteudoParaOModelo($mensagem->conteudo, $mensagem->anexos ?? [])],
            PapelDaMensagem::Ferramenta => ['role' => 'tool', 'tool_call_id' => $mensagem->ferramenta_id, 'content' => $mensagem->conteudo],
            PapelDaMensagem::Assistente => $mensagem->chamadas === null
                ? ['role' => 'assistant', 'content' => $mensagem->conteudo]
                : [
                    'role' => 'assistant',
                    'content' => $mensagem->conteudo ?: null,
                    'tool_calls' => array_map(fn (array $chamada) => [
                        'id' => $chamada['id'],
                        'type' => 'function',
                        'function' => ['name' => $chamada['nome'], 'arguments' => $chamada['argumentos']],
                    ], $mensagem->chamadas),
                ],
        };
    }
}
