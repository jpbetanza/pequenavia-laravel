import { Head } from '@inertiajs/react';
import { useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import DialogoDeDetalhes from '@/components/historico/dialogo-de-detalhes';
import TabelaDoHistorico from '@/components/historico/tabela-do-historico';
import { index } from '@/routes/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracoes: Geracao[];
};

export default function Historico({ geracoes }: Props) {
    const [selecionada, setSelecionada] = useState<Geracao | null>(null);
    const [aberta, setAberta] = useState(false);

    return (
        <>
            <Head title="Histórico" />
            <div className="p-4">
                <CabecalhoDaPagina
                    titulo="Histórico"
                    subtitulo="Últimas 50 gerações de certificado"
                />

                {geracoes.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                        <p className="text-lg font-medium text-muted-foreground">
                            Nenhuma geração encontrada
                        </p>
                        <p className="text-sm text-muted-foreground">
                            As gerações de certificado aparecerão aqui depois
                            que você gerar o primeiro certificado.
                        </p>
                    </div>
                ) : (
                    <TabelaDoHistorico
                        geracoes={geracoes}
                        aoVerDetalhes={(geracao) => {
                            setSelecionada(geracao);
                            setAberta(true);
                        }}
                    />
                )}

                <DialogoDeDetalhes
                    geracao={selecionada}
                    aberta={aberta}
                    aoFechar={() => setAberta(false)}
                />
            </div>
        </>
    );
}

Historico.layout = {
    breadcrumbs: [
        {
            title: 'Histórico',
            href: index(),
        },
    ],
};
