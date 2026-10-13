<?php

namespace App\Services\Assistente\Operacoes;

/**
 * Propostas do assistente que agem sobre o que já existe na agenda: responder solicitações, alterar ou cancelar reservas
 * e marcar a entrega e a devolução de equipamentos. Cada tipo tem o texto do botão, o selo depois de confirmada e o aviso
 * de sucesso, que vão no cartão.
 */
final class Operacoes
{
    /** @var array<string, array{botao: string, feito: string, sucesso: string, destrutiva: bool}> */
    public const APRESENTACAO = [
        'aprovar_solicitacao' => ['botao' => 'Aprovar solicitação', 'feito' => 'Aprovada', 'sucesso' => 'Solicitação aprovada e reserva criada.', 'destrutiva' => false],
        'recusar_solicitacao' => ['botao' => 'Confirmar recusa', 'feito' => 'Recusada', 'sucesso' => 'Solicitação recusada.', 'destrutiva' => true],
        'alterar_reserva' => ['botao' => 'Confirmar alteração', 'feito' => 'Alterada', 'sucesso' => 'Reserva alterada.', 'destrutiva' => false],
        'cancelar_reserva' => ['botao' => 'Confirmar cancelamento', 'feito' => 'Cancelada', 'sucesso' => 'Reserva cancelada.', 'destrutiva' => true],
        'entregar_equipamento' => ['botao' => 'Marcar como entregue', 'feito' => 'Entregue', 'sucesso' => 'Equipamento marcado como entregue.', 'destrutiva' => false],
        'recolher_equipamento' => ['botao' => 'Marcar como recolhido', 'feito' => 'Recolhido', 'sucesso' => 'Equipamento marcado como recolhido.', 'destrutiva' => false],
    ];

    public static function ehOperacao(string $tipo): bool
    {
        return isset(self::APRESENTACAO[$tipo]);
    }
}
