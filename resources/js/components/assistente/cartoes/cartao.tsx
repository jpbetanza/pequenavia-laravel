import { normalizarAcao } from '@/lib/assistente/cadastro';
import type { AcaoProposta, Cartao as TipoDeCartao } from '@/types/assistente';
import { CartaoDeAcaoProposta } from './cartao-de-acao-proposta';
import { CartaoDeCadastroProposto } from './cartao-de-cadastro-proposto';
import { CartaoDeCertificados } from './cartao-de-certificados';
import { CartaoDeDisponibilidade } from './cartao-de-disponibilidade';
import { CartaoDeReservas } from './cartao-de-reservas';
import { CartaoDeSolicitacoes } from './cartao-de-solicitacoes';

type Props = {
    cartao: TipoDeCartao;
    aoAtualizarAcao: (acao: AcaoProposta) => void;
};

/** Escolhe o cartão pelo `tipo`; proposta de cadastro tem layout próprio. Tipo desconhecido não renderiza nada. */
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

        case 'acao_proposta': {
            const acao = normalizarAcao(cartao.dados);

            return acao.tipo === 'criar_reserva' ? (
                <CartaoDeAcaoProposta
                    acao={acao}
                    aoAtualizar={aoAtualizarAcao}
                />
            ) : (
                <CartaoDeCadastroProposto
                    acao={acao}
                    aoAtualizar={aoAtualizarAcao}
                />
            );
        }

        default:
            return null;
    }
}
