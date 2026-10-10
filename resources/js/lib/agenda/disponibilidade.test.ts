import { describe, expect, it } from 'vite-plus/test';
import {
    datasComConflito,
    fimPadrao,
    horariosEmOrdem,
    resumoDoEquipamento,
    sugestoesDosConflitos,
    textoDoAlertaDeConflito,
    type DataDaDisponibilidade,
} from './disponibilidade';

const semConflito = (data: string): DataDaDisponibilidade => ({
    data,
    conflitos: [],
    sugestoes: { espacos: [], horario: null },
});

describe('datasComConflito', () => {
    it('ignora as datas puladas', () => {
        const resposta: DataDaDisponibilidade[] = [
            {
                data: '2026-10-10',
                conflitos: [{ tipo: 'sala_ocupada', mensagem: 'x' }],
                sugestoes: { espacos: [], horario: null },
            },
            semConflito('2026-11-14'),
        ];

        expect(datasComConflito(resposta, [])).toHaveLength(1);
        expect(datasComConflito(resposta, ['2026-10-10'])).toHaveLength(0);
    });
});

describe('sugestoesDosConflitos', () => {
    it('une espaços e horários sem repetir', () => {
        const salao = { id: 's1', nome: 'Auditório', capacidade: 80 };
        const conflitantes: DataDaDisponibilidade[] = [
            {
                data: '2026-10-10',
                conflitos: [{ tipo: 'sala_ocupada', mensagem: 'x' }],
                sugestoes: {
                    espacos: [salao],
                    horario: { inicio: '12:00', fim: '14:00' },
                },
            },
            {
                data: '2026-11-14',
                conflitos: [{ tipo: 'sala_ocupada', mensagem: 'x' }],
                sugestoes: {
                    espacos: [salao],
                    horario: { inicio: '12:00', fim: '14:00' },
                },
            },
        ];

        const { espacos, horarios } = sugestoesDosConflitos(conflitantes);

        expect(espacos).toEqual([salao]);
        expect(horarios).toEqual([{ inicio: '12:00', fim: '14:00' }]);
    });
});

describe('resumoDoEquipamento', () => {
    it('diz que está disponível quando nenhuma data tem conflito de equipamento', () => {
        expect(
            resumoDoEquipamento('Projetor', 2, [
                semConflito('2026-10-10'),
                semConflito('2026-11-14'),
            ]),
        ).toEqual({
            tom: 'ok',
            texto: 'Disponível nas 2 datas',
        });
    });

    it('mostra o menor número de livres e em quantas datas falta', () => {
        const consideradas: DataDaDisponibilidade[] = [
            {
                data: '2026-10-10',
                conflitos: [
                    {
                        tipo: 'equipamento_indisponivel',
                        mensagem:
                            '“Projetor”: pedidas 2, livres 1 de 2 neste horário.',
                    },
                ],
                sugestoes: { espacos: [], horario: null },
            },
            semConflito('2026-11-14'),
            semConflito('2026-12-12'),
        ];

        expect(resumoDoEquipamento('Projetor', 2, consideradas)).toEqual({
            tom: 'alerta',
            texto: '1 de 2 livres, falta em 1 das 3 datas',
        });
    });

    it('ignora conflitos de outro equipamento', () => {
        const consideradas: DataDaDisponibilidade[] = [
            {
                data: '2026-10-10',
                conflitos: [
                    {
                        tipo: 'equipamento_indisponivel',
                        mensagem:
                            '“Caixa de som”: pedidas 1, livres 0 de 1 neste horário.',
                    },
                ],
                sugestoes: { espacos: [], horario: null },
            },
        ];

        expect(resumoDoEquipamento('Projetor', 2, consideradas).tom).toBe('ok');
    });

    it('mostra a manutenção com a data de volta', () => {
        const consideradas: DataDaDisponibilidade[] = [
            {
                data: '2026-10-10',
                conflitos: [
                    {
                        tipo: 'equipamento_indisponivel',
                        mensagem:
                            '“Projetor” está em manutenção até 31/10 e não pode ser reservado.',
                    },
                ],
                sugestoes: { espacos: [], horario: null },
            },
        ];

        expect(resumoDoEquipamento('Projetor', 2, consideradas).texto).toBe(
            'Em manutenção até 31/10',
        );
    });
});

describe('fimPadrao e horariosEmOrdem', () => {
    it('propõe uma hora depois, limitada a 22:00', () => {
        expect(fimPadrao('10:00')).toBe('11:00');
        expect(fimPadrao('21:30')).toBe('22:00');
    });

    it('exige fim depois do início', () => {
        expect(horariosEmOrdem('10:00', '11:00')).toBe(true);
        expect(horariosEmOrdem('10:00', '10:00')).toBe(false);
        expect(horariosEmOrdem('10:00', '09:30')).toBe(false);
    });
});

describe('textoDoAlertaDeConflito', () => {
    it('usa a forma singular para uma data só', () => {
        expect(textoDoAlertaDeConflito(1, 1)).toBe('A data tem conflito');
    });

    it('conta as datas da série', () => {
        expect(textoDoAlertaDeConflito(1, 3)).toBe(
            '1 das 3 datas têm conflito',
        );
    });
});
