import { describe, expect, it } from 'vite-plus/test';
import { resumirHorarios } from './horarios';

const diaComHorario = (dia_semana: number, abre: string, fecha: string) => ({
    dia_semana,
    abre,
    fecha,
});

describe('resumirHorarios', () => {
    it('agrupa dias seguidos com o mesmo horário e mostra cada grupo', () => {
        expect(
            resumirHorarios([
                diaComHorario(1, '08:00', '22:00'),
                diaComHorario(2, '08:00', '22:00'),
                diaComHorario(3, '08:00', '22:00'),
                diaComHorario(4, '08:00', '22:00'),
                diaComHorario(5, '08:00', '22:00'),
                diaComHorario(6, '08:00', '18:00'),
            ]),
        ).toBe('Seg a sex 08:00–22:00 · Sáb 08:00–18:00');
    });

    it('diz "Todos os dias" quando os sete dias têm o mesmo horário', () => {
        const todos = [0, 1, 2, 3, 4, 5, 6].map((dia) =>
            diaComHorario(dia, '08:00', '21:00'),
        );

        expect(resumirHorarios(todos)).toBe('Todos os dias 08:00–21:00');
    });

    it('usa "e" para dois dias seguidos e "a" para três ou mais', () => {
        expect(
            resumirHorarios([
                diaComHorario(1, '08:00', '10:00'),
                diaComHorario(2, '08:00', '10:00'),
            ]),
        ).toBe('Seg e ter 08:00–10:00');

        expect(
            resumirHorarios([
                diaComHorario(1, '08:00', '10:00'),
                diaComHorario(2, '08:00', '10:00'),
                diaComHorario(3, '08:00', '10:00'),
            ]),
        ).toBe('Seg a qua 08:00–10:00');
    });

    it('lista os dias na ordem de segunda a domingo, mesmo que cheguem fora de ordem', () => {
        expect(
            resumirHorarios([
                diaComHorario(0, '09:00', '12:00'),
                diaComHorario(6, '09:00', '12:00'),
            ]),
        ).toBe('Sáb e dom 09:00–12:00');
    });

    it('não junta dias com horários diferentes nem dias separados por um fechado', () => {
        expect(
            resumirHorarios([
                diaComHorario(1, '08:00', '22:00'),
                diaComHorario(3, '08:00', '22:00'),
                diaComHorario(4, '08:00', '18:00'),
            ]),
        ).toBe('Seg 08:00–22:00 · Qua 08:00–22:00 · Qui 08:00–18:00');
    });

    it('aceita horários com segundos, que é como o banco devolve', () => {
        expect(
            resumirHorarios([diaComHorario(6, '08:00:00', '18:00:00')]),
        ).toBe('Sáb 08:00–18:00');
    });

    it('avisa quando o espaço não tem nenhum horário', () => {
        expect(resumirHorarios([])).toBe('Sem horário definido');
    });
});
