import { describe, expect, it } from 'vite-plus/test';
import { dicasDoEspaco, mascararTelefone } from './formulario-publico';

describe('mascararTelefone', () => {
    it('formata celular com nove dígitos depois do DDD', () => {
        expect(mascararTelefone('11900001234')).toBe('(11) 90000-1234');
    });

    it('formata fixo com oito dígitos depois do DDD', () => {
        expect(mascararTelefone('1130001234')).toBe('(11) 3000-1234');
    });

    it('ignora letras e pontuação digitadas', () => {
        expect(mascararTelefone('(11) 9 0000-1234 abc')).toBe(
            '(11) 90000-1234',
        );
    });

    it('mostra só o DDD enquanto o número está começando', () => {
        expect(mascararTelefone('1')).toBe('(1');
        expect(mascararTelefone('11')).toBe('(11');
        expect(mascararTelefone('119')).toBe('(11) 9');
    });

    it('não passa de 11 dígitos', () => {
        expect(mascararTelefone('119000012345678')).toBe('(11) 90000-1234');
    });

    it('vazio continua vazio', () => {
        expect(mascararTelefone('')).toBe('');
    });
});

describe('dicasDoEspaco', () => {
    it('sem regras, não há dica', () => {
        expect(
            dicasDoEspaco({ antecedencia_min_horas: 0, duracao_max_min: null }),
        ).toEqual([]);
    });

    it('antecedência singular e plural', () => {
        expect(
            dicasDoEspaco({ antecedencia_min_horas: 1, duracao_max_min: null }),
        ).toEqual([
            'Pedidos para este espaço precisam de pelo menos 1 hora de antecedência.',
        ]);
        expect(
            dicasDoEspaco({
                antecedencia_min_horas: 48,
                duracao_max_min: null,
            })[0],
        ).toContain('48 horas');
    });

    it('lista antecedência e duração máxima juntas', () => {
        expect(
            dicasDoEspaco({ antecedencia_min_horas: 24, duracao_max_min: 120 }),
        ).toHaveLength(2);
    });
});
