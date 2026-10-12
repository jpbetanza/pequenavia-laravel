<?php

namespace App\Services\Assistente;

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Monta as mensagens enviadas ao modelo: as instruções do sistema e o histórico recente da conversa no formato OpenAI.
 * O corte nunca começa numa mensagem que não é do usuário, para não separar uma chamada de ferramenta do seu resultado.
 */
final class MontadorDeContexto
{
    /**
     * @return list<array<string, mixed>>
     */
    public function montar(User $usuario, Conversa $conversa): array
    {
        $fuso = config('agendamentos.fuso');
        $agora = now($fuso)->locale('pt_BR');

        $instrucoes = view('assistente.instrucoes', [
            'nome' => $usuario->name,
            'hoje' => $agora->format('Y-m-d'),
            'diaDaSemana' => $agora->translatedFormat('l'),
            'hora' => $agora->format('H:i'),
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

        return $inicio === false ? collect() : $recentes->slice($inicio)->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function paraOModelo(Mensagem $mensagem): array
    {
        return match ($mensagem->papel) {
            PapelDaMensagem::Usuario => ['role' => 'user', 'content' => $mensagem->conteudo],
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
