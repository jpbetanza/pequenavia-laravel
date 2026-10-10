import { describe, expect, it } from 'vite-plus/test';
import type { AgoraDoEquipamento } from '@/types/agendamentos';
import {
    letraDoDia,
    rotuloDaAcao,
    rotuloDoMarcar,
    rotuloDoSelo,
    textoDoAgora,
    tomDaCelula,
} from './equipamentos';

function agora(parcial: Partial<AgoraDoEquipamento>): AgoraDoEquipamento {
    return {
        situacao: 'livre',
        em_uso: 0,
        total: 1,
        livres: 1,
        espacos: [],
        ate: null,
        ...parcial,
    };
}

describe('textoDoAgora', () => {
    it('mostra a manutenção com a data de fim', () => {
        expect(
            textoDoAgora(
                agora({
                    situacao: 'manutencao',
                    total: 1,
                    livres: 0,
                    ate: '31/10',
                }),
            ),
        ).toBe('Em manutenção até 31/10');
    });

    it('mostra "Em uso" quando todas as unidades estão em uso, com o espaço', () => {
        expect(
            textoDoAgora(
                agora({
                    situacao: 'em_uso',
                    em_uso: 1,
                    total: 1,
                    livres: 0,
                    espacos: ['Salão paroquial'],
                }),
            ),
        ).toBe('Em uso · Salão paroquial');
    });

    it('mostra a contagem quando só parte das unidades está em uso', () => {
        expect(
            textoDoAgora(
                agora({
                    situacao: 'em_uso',
                    em_uso: 1,
                    total: 2,
                    livres: 1,
                    espacos: ['Salão paroquial', 'Sala São José'],
                }),
            ),
        ).toBe('1 em uso · Salão paroquial, Sala São José');
    });

    it('mostra "Livre" para uma unidade e a contagem para mais', () => {
        expect(textoDoAgora(agora({ total: 1, livres: 1 }))).toBe('Livre');
        expect(textoDoAgora(agora({ total: 4, livres: 4 }))).toBe('4 livres');
    });
});

describe('letraDoDia', () => {
    it('usa a letra do dia de segunda a domingo, sem depender do fuso do navegador', () => {
        const letras = [
            '2026-10-05',
            '2026-10-06',
            '2026-10-07',
            '2026-10-08',
            '2026-10-09',
            '2026-10-10',
            '2026-10-11',
        ].map(letraDoDia);

        expect(letras).toEqual(['S', 'T', 'Q', 'Q', 'S', 'S', 'D']);
    });
});

describe('tomDaCelula', () => {
    it('é cheia só quando o pico alcança todas as unidades', () => {
        expect(tomDaCelula(2, 2)).toBe('cheia');
        expect(tomDaCelula(1, 2)).toBe('parcial');
        expect(tomDaCelula(0, 2)).toBe('vazia');
    });
});

describe('rótulos das ações', () => {
    it('usa os textos do painel e do selo', () => {
        expect(rotuloDaAcao('levar')).toBe('Levar');
        expect(rotuloDaAcao('recolher')).toBe('Recolher');
        expect(rotuloDoMarcar('levar')).toBe('Marcar entregue');
        expect(rotuloDoMarcar('recolher')).toBe('Marcar recolhido');
        expect(rotuloDoSelo('levar')).toBe('Entregue');
        expect(rotuloDoSelo('recolher')).toBe('Recolhido');
    });
});
