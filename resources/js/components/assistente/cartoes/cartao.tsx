import { ehOperacao, normalizarAcao } from '@/lib/assistente/cadastro';
import type { AcaoProposta, Cartao as TipoDeCartao } from '@/types/assistente';
import { CartaoDeAcaoProposta } from './cartao-de-acao-proposta';
import { CartaoDeCadastroProposto } from './cartao-de-cadastro-proposto';
import { CartaoDeCertificados } from './cartao-de-certificados';
import { CartaoDeDisponibilidade } from './cartao-de-disponibilidade';
import { CartaoDeOperacaoProposta } from './cartao-de-operacao-proposta';
import { CartaoDeReservas } from './cartao-de-reservas';
import { CartaoDeSolicitacoes } from './cartao-de-solicitacoes';

type Props = {
    cartao: TipoDeCartao;
    aoAtualizarAcao: (acao: AcaoProposta) => void;
};

/** Escolhe o cartão pelo `tipo`; propostas de cadastro e de operação têm layout próprio. Tipo desconhecido não renderiza nada. */
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

            if (acao.tipo === 'criar_reserva') {
                return (
                    <CartaoDeAcaoProposta
                        acao={acao}
                        aoAtualizar={aoAtualizarAcao}
                    />
                );
            }

            return ehOperacao(acao) ? (
                <CartaoDeOperacaoProposta
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
