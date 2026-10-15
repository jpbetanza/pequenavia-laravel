<?php

namespace App\Services\Gestao;

use App\Enums\CasoDeUso;

/**
 * Perguntas fechadas que o JEV responde sobre cada conversa, com o texto, as opções e os limiares.
 * É o único lugar com esses textos e números.
 */
final class PerguntasDoJev
{
    /** Resposta "não" de um noul vai até 0,5; daí para cima conta como sim. */
    public const SIM = 0.5;

    /** Confiança mínima para usar o tema que o JEV escolheu, sem esperar o Haiku. */
    public const TEMA_CONFIANCA_MINIMA = 0.6;

    /** Constante de classe não lê a configuração, então o limiar fica em um método. */
    public static function confiancaMinima(): float
    {
        return (float) config('gestao.confianca_minima');
    }

    /**
     * @param  list<array{id: string, nome: string, descricao: string|null}>  $temas  Temas que entram como opção (não descartados nem juntados).
     * @return array<string, array<string, mixed>>
     */
    public function montar(array $temas): array
    {
        $perguntas = [
            'use_case' => [
                'type' => 'choice',
                'instructions' => 'What is the main thing the secretary wanted from the assistant in `conversation`?',
                'criteria' => $this->opcoesDeUso(),
            ],
            'outcome' => [
                'type' => 'choice',
                'instructions' => 'By the end of `conversation`, did the secretary get what she asked for? Changes counted in `proposed_actions.confirmed` were done. Judge her main request: small follow-ups still pending at the end do not undo it.',
                'criteria' => [
                    'resolvida' => 'Got it fully: her question was answered or the changes she asked for were confirmed',
                    'parcial' => 'Got part of it, or a workaround',
                    'nao_resolvida' => 'Did not get it',
                ],
            ],
            'frustration' => [
                'type' => 'score',
                'instructions' => 'How frustrated is the secretary across `conversation`?',
                'criteria' => [
                    'Calm or satisfied',
                    'Neutral, just getting things done',
                    'Frustrated but polite: repeats herself or complains',
                    'Very frustrated or gives up',
                ],
            ],
            'confused' => [
                'type' => 'noul',
                'instructions' => 'Does the secretary seem confused about what the assistant said or asked?',
            ],
            'unmet_request' => [
                'type' => 'noul',
                'instructions' => 'Did the secretary ask for something that the app or the assistant cannot do (see `app.capabilities`)?',
            ],
            'assistant_error' => [
                'type' => 'noul',
                'instructions' => 'Did the assistant state something false, contradict itself, or invent information?',
            ],
            'ended_waiting' => [
                'type' => 'noul',
                'instructions' => 'Does `conversation` end with the assistant waiting for an answer or a confirmation the secretary never gave?',
            ],
        ];

        if ($temas !== []) {
            $perguntas['unmet_theme'] = [
                'type' => 'choice',
                'instructions' => 'Which of these needs did the secretary have that the app does not cover?',
                'criteria' => $this->opcoesDeTema($temas),
            ];
        }

        return $perguntas;
    }

    /**
     * @return array<string, string>
     */
    private function opcoesDeUso(): array
    {
        return [
            CasoDeUso::ConsultarAgenda->value => 'Looks up the calendar or whether a space is free',
            CasoDeUso::Reserva->value => 'Covers creating, changing or cancelling bookings, and marking equipment as delivered or returned',
            CasoDeUso::Solicitacoes->value => 'Answers the requests that groups or people sent',
            CasoDeUso::Cadastro->value => 'Covers spaces, equipment and groups, including by spreadsheet',
            CasoDeUso::Certificados->value => 'Covers certificate templates and certificate summaries',
            CasoDeUso::DuvidaApp->value => 'Asks how to use the app itself',
            CasoDeUso::ForaDoEscopo->value => 'Covers subjects outside the work of a parish secretary',
            CasoDeUso::Outro->value => 'Something the app covers that none of the options above fits',
        ];
    }

    /**
     * @param  list<array{id: string, nome: string, descricao: string|null}>  $temas
     * @return array<string, string>
     */
    private function opcoesDeTema(array $temas): array
    {
        $opcoes = [];

        foreach ($temas as $tema) {
            $opcoes['tema_'.$tema['id']] = ($tema['descricao'] ?? '') === ''
                ? $tema['nome']
                : $tema['nome'].': '.$tema['descricao'];
        }

        $opcoes['outro'] = 'A need not in this list';
        $opcoes['nenhum'] = 'She asked for nothing the app cannot do';

        return $opcoes;
    }
}
