<?php

namespace App\Enums;

/**
 * Para que a secretaria usou o assistente numa conversa, segundo a classificação.
 */
enum CasoDeUso: string
{
    case ConsultarAgenda = 'consultar_agenda';
    case Reserva = 'reserva';
    case Solicitacoes = 'solicitacoes';
    case Cadastro = 'cadastro';
    case Certificados = 'certificados';
    case DuvidaApp = 'duvida_app';
    case ForaDoEscopo = 'fora_do_escopo';
    case Outro = 'outro';

    /**
     * Texto mostrado no painel de gestão.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::ConsultarAgenda => 'Consultar a agenda',
            self::Reserva => 'Criar ou mudar reserva',
            self::Solicitacoes => 'Responder solicitações',
            self::Cadastro => 'Cadastrar espaços, equipamentos ou grupos',
            self::Certificados => 'Certificados',
            self::DuvidaApp => 'Dúvida sobre o app',
            self::ForaDoEscopo => 'Fora do escopo',
            self::Outro => 'Outro',
        };
    }
}
