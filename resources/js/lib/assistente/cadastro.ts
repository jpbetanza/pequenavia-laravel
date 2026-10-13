import { edit as editarEspaco } from '@/routes/agendamentos/espacos';
import { index as listarEquipamentos } from '@/routes/agendamentos/equipamentos';
import { index as listarGrupos } from '@/routes/agendamentos/grupos';
import { index as listarEspacos } from '@/routes/agendamentos/espacos';
import type {
    AcaoProposta,
    AcaoPropostaDeOperacao,
    EntidadeDeCadastro,
    OperacaoDeCadastro,
    StatusDaAcao,
} from '@/types/assistente';

/** Texto do selo do cartão de cadastro. Confirmada depende da operação (criou, alterou ou excluiu). */
export function rotuloDoSelo(
    status: StatusDaAcao,
    operacao: OperacaoDeCadastro,
): string {
    switch (status) {
        case 'proposta':
            return 'Aguardando sua confirmação';

        case 'confirmada':
            return {
                criar: 'Cadastrado',
                alterar: 'Alterado',
                excluir: 'Excluído',
            }[operacao];

        case 'descartada':
            return 'Descartada';

        case 'expirada':
            return 'Expirada';
    }
}

/** Página onde o usuário vê o cadastro. Espaço salvo abre a edição; sem id (exclusão), a lista. */
export function linkDoCadastro(
    entidade: EntidadeDeCadastro,
    cadastroId: string | null,
): string {
    switch (entidade) {
        case 'espaco':
            return cadastroId === null
                ? listarEspacos.url()
                : editarEspaco.url({ espaco: cadastroId });

        case 'grupo':
            return listarGrupos.url();

        case 'equipamento':
            return listarEquipamentos.url();
    }
}

/** Cartões antigos não têm `tipo`: são sempre propostas de reserva. */
export function normalizarAcao(
    dados:
        | AcaoProposta
        | (Omit<Extract<AcaoProposta, { tipo: 'criar_reserva' }>, 'tipo'> & {
              tipo?: undefined;
          }),
): AcaoProposta {
    if (dados.tipo === undefined) {
        return { ...dados, tipo: 'criar_reserva' } as AcaoProposta;
    }

    return dados;
}

/** Proposta de operação sobre a agenda (responder solicitação, mudar reserva, marcar equipamento)? */
export function ehOperacao(acao: AcaoProposta): acao is AcaoPropostaDeOperacao {
    return 'operacao' in acao;
}

/** Título da proposta, para avisos que citam uma delas. */
export function tituloDaAcao(acao: AcaoProposta): string {
    if (acao.tipo === 'criar_reserva') {
        return acao.resumo.titulo;
    }

    return ehOperacao(acao) ? acao.operacao.titulo : acao.cadastro.titulo;
}
