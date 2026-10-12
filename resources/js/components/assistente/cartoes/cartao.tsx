import type { AcaoProposta, Cartao as TipoDeCartao } from '@/types/assistente';
import { CartaoDeAcaoProposta } from './cartao-de-acao-proposta';
import { CartaoDeCertificados } from './cartao-de-certificados';
import { CartaoDeDisponibilidade } from './cartao-de-disponibilidade';
import { CartaoDeReservas } from './cartao-de-reservas';
import { CartaoDeSolicitacoes } from './cartao-de-solicitacoes';

type Props = {
    cartao: TipoDeCartao;
    aoAtualizarAcao: (acao: AcaoProposta) => void;
};

/** Escolhe o cartão pelo `tipo`. Tipo desconhecido não renderiza nada. */
export function Cartao({ cartao, aoAtualizarAcao }: Props) {
    switch (cartao.tipo) {
        case 'disponibilidade':
            return <CartaoDeDisponibilidade dados={cartao.dados} />;

        case 'reservas':
            return <CartaoDeReservas dados={cartao.dados} />;

        case 'solicitacoes':
            return <CartaoDeSolicitacoes dados={cartao.dados} />;

        case 'certificados':
            return <CartaoDeCertificados dados={cartao.dados} />;

        case 'acao_proposta':
            return (
                <CartaoDeAcaoProposta
                    acao={cartao.dados}
                    aoAtualizar={aoAtualizarAcao}
                />
            );

        default:
            return null;
    }
}
