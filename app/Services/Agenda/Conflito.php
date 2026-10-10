<?php

namespace App\Services\Agenda;

/**
 * Motivo pelo qual uma reserva candidata não pode ser feita. As mensagens são exibidas à secretaria em pt-BR.
 */
final readonly class Conflito
{
    public const ESPACO_INATIVO = 'espaco_inativo';

    public const FORA_DO_HORARIO = 'fora_do_horario';

    public const BLOQUEIO = 'bloqueio';

    public const SALA_OCUPADA = 'sala_ocupada';

    public const EQUIPAMENTO_INDISPONIVEL = 'equipamento_indisponivel';

    public function __construct(
        public string $tipo,
        public string $mensagem,
    ) {}

    /**
     * @return array{tipo: string, mensagem: string}
     */
    public function toArray(): array
    {
        return ['tipo' => $this->tipo, 'mensagem' => $this->mensagem];
    }
}
