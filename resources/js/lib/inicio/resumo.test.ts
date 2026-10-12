import { describe, expect, it } from 'vite-plus/test';
import type { PainelDeHoje } from '@/types/agendamentos';
import {
    dataCurta,
    diaDoCertificado,
    equipamentosParaSeparar,
    horaCurta,
    linhaDeCertificados,
    mesPorExtenso,
    primeiroNome,
    resumoDoDia,
    saudacao,
} from './resumo';

describe('saudacao', () => {
    it('usa o período e o primeiro nome', () => {
        expect(saudacao('manha', 'Ana Paula Souza')).toBe('Bom dia, Ana.');
        expect(saudacao('tarde', 'Ana')).toBe('Boa tarde, Ana.');
        expect(saudacao('noite', '  José  ')).toBe('Boa noite, José.');
    });

    it('sem nome, só a saudação', () => {
        expect(saudacao('tarde', '')).toBe('Boa tarde.');
    });
});

describe('primeiroNome', () => {
    it('pega a primeira palavra', () => {
        expect(primeiroNome('Maria Clara')).toBe('Maria');
    });
});

describe('resumoDoDia', () => {
    it('junta as partes com vírgula e "e", no plural certo', () => {
        expect(resumoDoDia({ reservas: 6, pedidos: 3, separar: 2 })).toBe(
            '6 reservas hoje, 3 pedidos esperando resposta e 2 equipamentos para separar.',
        );
    });

    it('usa o singular', () => {
        expect(resumoDoDia({ reservas: 1, pedidos: 1, separar: 1 })).toBe(
            '1 reserva hoje, 1 pedido esperando resposta e 1 equipamento para separar.',
        );
    });

    it('omite o que é zero', () => {
        expect(resumoDoDia({ reservas: 2, pedidos: 0, separar: 0 })).toBe(
            '2 reservas hoje.',
        );
        expect(resumoDoDia({ reservas: 0, pedidos: 3, separar: 0 })).toBe(
            '3 pedidos esperando resposta.',
        );
        expect(resumoDoDia({ reservas: 2, pedidos: 0, separar: 1 })).toBe(
            '2 reservas hoje e 1 equipamento para separar.',
        );
    });

    it('dia vazio', () => {
        expect(resumoDoDia({ reservas: 0, pedidos: 0, separar: 0 })).toBe(
            'Nenhuma reserva hoje e nenhum pedido esperando resposta.',
        );
    });
});

describe('equipamentosParaSeparar', () => {
    it('soma só os itens não marcados dos movimentos de levar', () => {
        const painel: PainelDeHoje = {
            rotulo: 'sábado, 17 de outubro',
            movimentos: [
                {
                    chave: 'a',
                    acao: 'levar',
                    horario: '14:45',
                    titulo: 'Crisma',
                    espaco: 'Salão',
                    reserva_id: 'r1',
                    itens: [
                        {
                            equipamento_id: 'e1',
                            nome: 'Projetor',
                            quantidade: 1,
                            marcado: false,
                        },
                        {
                            equipamento_id: 'e2',
                            nome: 'Caixa',
                            quantidade: 2,
                            marcado: false,
                        },
                        {
                            equipamento_id: 'e3',
                            nome: 'Mesa',
                            quantidade: 1,
                            marcado: true,
                        },
                    ],
                },
                {
                    chave: 'b',
                    acao: 'recolher',
                    horario: '17:00',
                    titulo: 'Coral',
                    espaco: 'Salão',
                    reserva_id: 'r2',
                    itens: [
                        {
                            equipamento_id: 'e1',
                            nome: 'Projetor',
                            quantidade: 5,
                            marcado: false,
                        },
                    ],
                },
            ],
        };

        expect(equipamentosParaSeparar(painel)).toBe(3);
    });
});

describe('horaCurta e dataCurta', () => {
    it('horas redondas e com minutos no formato da tela', () => {
        expect(horaCurta('15:00')).toBe('15h');
        expect(horaCurta('19:30')).toBe('19h30');
    });

    it('data curta com dia da semana e dia do mês', () => {
        expect(dataCurta('2026-10-20')).toBe('ter 20');
    });
});

describe('certificados', () => {
    it('mês por extenso', () => {
        expect(mesPorExtenso('2026-10-17')).toBe('outubro');
    });

    it('dia do certificado: hoje, ontem ou dd/mm', () => {
        expect(
            diaDoCertificado('2026-10-17T10:00:00-03:00', '2026-10-17'),
        ).toBe('hoje');
        expect(
            diaDoCertificado('2026-10-16T10:00:00-03:00', '2026-10-17'),
        ).toBe('ontem');
        expect(
            diaDoCertificado('2026-10-02T10:00:00-03:00', '2026-10-17'),
        ).toBe('02/10');
    });

    it('linha com o último e sem ele', () => {
        expect(
            linhaDeCertificados({
                emitidos: 14,
                dataHoje: '2026-10-17',
                ultimo: {
                    modelo: 'Batismo',
                    gerado_em: '2026-10-16T10:00:00-03:00',
                },
            }),
        ).toBe('14 emitidos em outubro · último: Batismo, ontem');
        expect(
            linhaDeCertificados({
                emitidos: 2,
                dataHoje: '2026-10-17',
                ultimo: null,
            }),
        ).toBe('2 emitidos em outubro');
        expect(
            linhaDeCertificados({
                emitidos: 0,
                dataHoje: '2026-10-17',
                ultimo: null,
            }),
        ).toBe('Nenhum emitido em outubro');
    });
});
