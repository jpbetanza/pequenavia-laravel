import { index as equipamentos } from '@/routes/agendamentos/equipamentos';
import { create as novoEspaco } from '@/routes/agendamentos/espacos';
import { index as grupos } from '@/routes/agendamentos/grupos';
import { index as solicitacoes } from '@/routes/agendamentos/solicitacoes';
import { edit as notificacoes } from '@/routes/notificacoes';
import type { ChaveDoPasso } from '@/types/primeiros-passos';

/** Onde fazer o passo pela tela, para quem prefere sem o assistente (ou quando ele está desligado). */
export function linkDoPasso(chave: ChaveDoPasso): string {
    switch (chave) {
        case 'espacos':
            return novoEspaco.url();

        case 'equipamentos':
            return equipamentos.url();

        case 'grupos':
            return grupos.url();

        case 'formulario':
            return solicitacoes.url();

        case 'avisos':
            return notificacoes.url();
    }
}
