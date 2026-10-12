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

/** Reserva que o assistente propôs e o usuário ainda pode confirmar ou descartar. */
export type AcaoProposta = {
    id: string;
    status: 'proposta' | 'confirmada' | 'descartada' | 'expirada';
    resumo: {
        titulo: string;
        grupo_nome: string | null;
        espaco_id: string | null;
        espaco_nome: string;
        data: string;
        inicio: string;
        fim: string;
        pessoas: number | null;
        equipamentos: { nome: string; quantidade: number }[];
        observacoes: string | null;
    };
    resultado: { reserva_id: string; data: string } | null;
};

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
