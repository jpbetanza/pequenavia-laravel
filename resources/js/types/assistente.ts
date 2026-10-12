import type { CorDoEspaco } from '@/lib/agenda/cores';

/** Cartões que o assistente devolve junto com o texto. `tipo` define o formato de `dados`. */
export type Cartao =
    | {
          tipo: 'disponibilidade';
          dados: {
              espaco: { id: string; nome: string; cor: CorDoEspaco };
              data: string;
              horario_do_dia: { abre: string; fecha: string } | null;
              reservas: { inicio: string; fim: string; titulo: string }[];
              pedido: {
                  inicio: string;
                  fim: string;
                  conflitos: { tipo: string; mensagem: string }[];
                  sugestoes: {
                      espacos: {
                          id: string;
                          nome: string;
                          capacidade: number | null;
                      }[];
                      horario: { inicio: string; fim: string } | null;
                  };
              } | null;
          };
      }
    | {
          tipo: 'reservas';
          dados: {
              inicio: string;
              fim: string;
              reservas: {
                  id: string;
                  titulo: string;
                  data: string;
                  inicio: string;
                  fim: string;
                  espaco_nome: string;
                  espaco_cor: CorDoEspaco;
                  grupo_nome: string | null;
              }[];
          };
      }
    | {
          tipo: 'solicitacoes';
          dados: {
              status: string;
              solicitacoes: {
                  id: string;
                  titulo: string;
                  nome: string;
                  data: string;
                  inicio: string;
                  fim: string;
                  espaco_nome: string | null;
                  estado:
                      | 'sem_conflito'
                      | 'conflito'
                      | 'escolha_um_espaco'
                      | null;
              }[];
          };
      }
    | {
          tipo: 'certificados';
          dados: {
              inicio: string;
              fim: string;
              total: number;
              por_modelo: { modelo: string; total: number }[];
              ultimas: { modelo: string; quando: string }[];
          };
      }
    | { tipo: 'acao_proposta'; dados: AcaoProposta };

export type StatusDaAcao =
    | 'proposta'
    | 'confirmada'
    | 'descartada'
    | 'expirada';

export type EntidadeDeCadastro = 'espaco' | 'grupo' | 'equipamento';

export type OperacaoDeCadastro = 'criar' | 'alterar' | 'excluir';

/** Reserva que o assistente propôs e o usuário ainda pode confirmar ou descartar. Cartão antigo pode vir sem `tipo`. */
export type AcaoPropostaDeReserva = {
    id: string;
    status: StatusDaAcao;
    tipo: 'criar_reserva';
    /** Chave que decide quais propostas se substituem na conversa. Ausente em cartão antigo. */
    chave?: string;
    resumo: {
        titulo: string;
        grupo_id: string | null;
        grupo_nome: string | null;
        espaco_id: string | null;
        espaco_nome: string;
        data: string;
        inicio: string;
        fim: string;
        pessoas: number | null;
        equipamentos: { id: string; nome: string; quantidade: number }[];
        observacoes: string | null;
    };
    resultado: { reserva_id: string; data: string } | null;
};

/** Criar, alterar ou excluir um espaço, grupo ou equipamento, à espera de confirmação. */
export type AcaoPropostaDeCadastro = {
    id: string;
    status: StatusDaAcao;
    tipo: TipoDeAcaoDeCadastro;
    /** Chave que decide quais propostas se substituem na conversa. Ausente em cartão antigo. */
    chave?: string;
    cadastro: {
        entidade: EntidadeDeCadastro;
        operacao: OperacaoDeCadastro;
        titulo: string;
        linhas: {
            rotulo: string;
            antes: string | null;
            depois: string | null;
        }[];
        avisos: string[];
    };
    resultado: {
        cadastro_id: string | null;
        entidade: EntidadeDeCadastro;
    } | null;
};

export type TipoDeAcaoDeCadastro =
    | 'criar_espaco'
    | 'alterar_espaco'
    | 'excluir_espaco'
    | 'criar_grupo'
    | 'alterar_grupo'
    | 'excluir_grupo'
    | 'criar_equipamento'
    | 'alterar_equipamento'
    | 'excluir_equipamento';

export type AcaoProposta = AcaoPropostaDeReserva | AcaoPropostaDeCadastro;

export type MensagemDoPainel = {
    id: string;
    papel: 'usuario' | 'assistente';
    conteudo: string;
    cartoes: Cartao[];
    erro: boolean;
    status: string | null;
    pendente: boolean;
};

export type ResumoDeConversa = {
    id: string;
    titulo: string;
    previa: string | null;
    ultima_mensagem_em: string | null;
};

/** Evento do stream, já convertido do formato do servidor (`mensagem_id` vira `mensagemId`). */
export type EventoDoAssistente =
    | { tipo: 'status'; texto: string }
    | { tipo: 'texto'; pedaco: string }
    | { tipo: 'cartao'; cartao: Cartao }
    | {
          tipo: 'fim';
          mensagemId: string;
          conversa: { id: string; titulo: string };
      }
    | { tipo: 'erro'; mensagem: string };
