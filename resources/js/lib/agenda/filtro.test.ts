import { describe, expect, it } from 'vite-plus/test';
import {
    espacosDisponiveisParaReserva,
    lerFiltroSalvo,
    montarFiltroSalvo,
    reconciliarSelecao,
} from './filtro';

describe('reconciliarSelecao', () => {
    it('marca todos os espaços quando não há nada salvo', () => {
        expect(reconciliarSelecao(null, ['a', 'b'])).toEqual(['a', 'b']);
    });

    it('respeita a seleção salva, inclusive a vazia', () => {
        const salvo = montarFiltroSalvo(['b'], ['a', 'b']);

        expect(reconciliarSelecao(salvo, ['a', 'b'])).toEqual(['b']);
        expect(reconciliarSelecao(montarFiltroSalvo([], ['a']), ['a'])).toEqual(
            [],
        );
    });

    it('marca um espaço novo, criado depois de salvar, mesmo fora da seleção salva', () => {
        const salvo = montarFiltroSalvo(['a'], ['a', 'b']);

        expect(reconciliarSelecao(salvo, ['a', 'b', 'c'])).toEqual(['a', 'c']);
    });

    it('ignora espaço salvo que não existe mais', () => {
        const salvo = montarFiltroSalvo(['a', 'x'], ['a', 'x']);

        expect(reconciliarSelecao(salvo, ['a'])).toEqual(['a']);
    });
});

describe('lerFiltroSalvo', () => {
    it('lê o registro válido e recusa qualquer outra forma', () => {
        const valido = JSON.stringify({
            selecionados: ['a'],
            conhecidos: ['a'],
        });

        expect(lerFiltroSalvo(valido)).toEqual({
            selecionados: ['a'],
            conhecidos: ['a'],
        });
        expect(lerFiltroSalvo(null)).toBeNull();
        expect(lerFiltroSalvo('{nao é json')).toBeNull();
        expect(lerFiltroSalvo('[1,2]')).toBeNull();
        expect(
            lerFiltroSalvo('{"selecionados":[1],"conhecidos":[]}'),
        ).toBeNull();
    });
});

describe('espacosDisponiveisParaReserva', () => {
    const espacos = [
        { id: 'salao', ativo: true },
        { id: 'sala-antiga', ativo: false },
        { id: 'auditorio', ativo: true },
    ];

    it('lista só os ativos ao criar uma reserva', () => {
        expect(espacosDisponiveisParaReserva(espacos, null)).toEqual([
            { id: 'salao', ativo: true },
            { id: 'auditorio', ativo: true },
        ]);
    });

    it('na edição, mantém o espaço atual mesmo quando está inativo', () => {
        expect(
            espacosDisponiveisParaReserva(espacos, 'sala-antiga').map(
                (espaco) => espaco.id,
            ),
        ).toEqual(['salao', 'sala-antiga', 'auditorio']);
    });
});
