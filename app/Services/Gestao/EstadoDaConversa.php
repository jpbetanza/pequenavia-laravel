<?php

namespace App\Services\Gestao;

use App\Enums\PapelDaMensagem;
use App\Enums\StatusDaAcao;
use App\Models\Conversa;
use App\Models\Grupo;
use App\Models\Mensagem;

/**
 * Monta o estado que o JEV e o Haiku leem: o que o app faz, a conversa (mascarada) e as ações propostas.
 */
class EstadoDaConversa
{
    private const CAPACIDADES = [
        'Look up the calendar and the availability of spaces',
        'Propose a booking (one date and one space per proposal)',
        'Change or cancel bookings',
        'Answer requests from groups and people',
        'Mark equipment as delivered or returned',
        'Add spaces, equipment and groups, including by reading spreadsheets',
        'Summarize certificates',
        'Send the public form link',
        'Explain the app',
        'Cannot: recurring booking in a single proposal',
        'Cannot: several spaces in one booking',
        'Cannot: send messages or reminders',
        'Cannot: reports',
        'Cannot: edit certificates',
    ];

    public function __construct(private readonly Mascarador $mascarador) {}

    /**
     * @return array{app: array<string, mixed>, conversation: list<array{from: string, text: string, tools_used: list<string>}>, proposed_actions: array{confirmed: int, discarded: int, pending: int}}
     */
    public function montar(Conversa $conversa): array
    {
        $conversa->loadMissing(['mensagens' => fn ($consulta) => $consulta->orderBy('created_at')->orderBy('id'), 'acoes']);

        $trechos = $this->trechos($conversa);

        return [
            'app' => [
                'name' => 'PequenaVia',
                'purpose' => 'Helps a parish secretary manage spaces, equipment, groups, bookings and certificates through chat.',
                'capabilities' => self::CAPACIDADES,
            ],
            'conversation' => array_map(fn (array $trecho) => [
                'from' => $trecho['de'] === 'secretaria' ? 'secretary' : 'assistant',
                'text' => $trecho['texto'],
                'tools_used' => $trecho['tools_used'],
            ], $trechos),
            'proposed_actions' => $this->acoes($conversa),
        ];
    }

    /**
     * Mesmas mensagens do estado, no formato que o Haiku lê.
     *
     * @return list<array{de: string, texto: string}>
     */
    public function mensagensParaResumo(Conversa $conversa): array
    {
        $conversa->loadMissing(['mensagens' => fn ($consulta) => $consulta->orderBy('created_at')->orderBy('id')]);

        return array_map(
            fn (array $trecho) => ['de' => $trecho['de'], 'texto' => $trecho['texto']],
            $this->trechos($conversa),
        );
    }

    /**
     * Só mensagens da secretaria e do assistente com texto. Mascara antes de cortar, para que um
     * pedaço de dado pessoal não escape pelo corte.
     *
     * @return list<array{de: string, texto: string, tools_used: list<string>}>
     */
    private function trechos(Conversa $conversa): array
    {
        $nomes = $this->nomes($conversa);
        $limite = (int) config('gestao.max_caracteres_por_mensagem');

        $trechos = $conversa->mensagens
            ->filter(fn (Mensagem $mensagem) => in_array($mensagem->papel, [PapelDaMensagem::Usuario, PapelDaMensagem::Assistente], true)
                && trim((string) $mensagem->conteudo) !== '')
            ->values()
            ->slice(-(int) config('gestao.max_mensagens_no_estado'))
            ->values()
            ->map(fn (Mensagem $mensagem) => [
                'de' => $mensagem->papel === PapelDaMensagem::Usuario ? 'secretaria' : 'assistente',
                'texto' => mb_substr($this->mascarador->mascarar((string) $mensagem->conteudo, $nomes), 0, $limite),
                'tools_used' => $mensagem->papel === PapelDaMensagem::Assistente ? $this->ferramentas($mensagem) : [],
            ])
            ->all();

        return array_values($trechos);
    }

    /**
     * Nomes das ferramentas que a mensagem do assistente chamou, sem repetição.
     *
     * @return list<string>
     */
    private function ferramentas(Mensagem $mensagem): array
    {
        $nomes = array_map(fn (array $chamada) => (string) $chamada['nome'], $mensagem->chamadas ?? []);

        return array_values(array_unique($nomes));
    }

    /**
     * Nome do dono da conversa e dos grupos dele: tudo o que a máscara precisa tirar do texto.
     *
     * @return list<string>
     */
    public function nomes(Conversa $conversa): array
    {
        $conversa->loadMissing('user');

        return $this->nomesDoCliente($conversa->user_id, $conversa->user?->name);
    }

    /**
     * Mesmos nomes, para quando o cliente já é conhecido e vale buscar uma vez só para várias conversas.
     *
     * @return list<string>
     */
    public function nomesDoCliente(string $clienteId, ?string $nome): array
    {
        /** @var list<string> $nomes */
        $nomes = array_values(Grupo::query()->where('user_id', $clienteId)->pluck('nome')->all());

        if ($nome) {
            $nomes[] = $nome;
        }

        return $nomes;
    }

    /**
     * @return array{confirmed: int, discarded: int, pending: int}
     */
    private function acoes(Conversa $conversa): array
    {
        $acoes = $conversa->acoes;

        return [
            'confirmed' => $acoes->filter(fn ($acao) => $acao->status === StatusDaAcao::Confirmada)->count(),
            // Expirada conta como descartada: não foi confirmada nem segue pendente.
            'discarded' => $acoes->filter(fn ($acao) => in_array($acao->status, [StatusDaAcao::Descartada, StatusDaAcao::Expirada], true))->count(),
            'pending' => $acoes->filter(fn ($acao) => $acao->status === StatusDaAcao::Proposta)->count(),
        ];
    }
}
