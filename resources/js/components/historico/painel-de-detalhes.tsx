import { Link } from '@inertiajs/react';
import { Copy, RotateCcw } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { horaDaGeracao, ordenarValores, tituloDoDia } from '@/lib/historico';
import { gerar } from '@/routes/meus-certificados';
import type { Geracao } from '@/types/certificados';
import SeloDoModelo from './selo-do-modelo';

type Props = {
    geracao: Geracao;
    agora: Date;
};

export default function PainelDeDetalhes({ geracao, agora }: Props) {
    const campos = ordenarValores(geracao.campos_valores, geracao.ordem_campos);
    const [primeiro] = campos;
    const titulo = primeiro?.[1] ?? geracao.modelo_title;
    const semModelo = geracao.modelo_id === null;

    async function copiarDados() {
        const texto = campos
            .map(([nome, valor]) => `${nome}: ${valor}`)
            .join('\n');

        try {
            await navigator.clipboard.writeText(texto);
            toast.success('Dados copiados.');
        } catch {
            toast.error('Não foi possível copiar os dados.');
        }
    }

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                    <SeloDoModelo titulo={geracao.modelo_title} />
                    <span>
                        {tituloDoDia(geracao.created_at, agora)},{' '}
                        {horaDaGeracao(geracao.created_at)}
                    </span>
                </div>
                <h2 className="font-serif text-3xl leading-tight font-semibold break-words">
                    {titulo}
                </h2>
            </div>

            <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2.5 text-sm">
                {campos.map(([nome, valor]) => (
                    <div key={nome} className="contents">
                        <dt className="text-muted-foreground">{nome}</dt>
                        <dd className="font-medium break-words">{valor}</dd>
                    </div>
                ))}
                <div className="contents">
                    <dt className="text-muted-foreground">Gerado por</dt>
                    <dd className="font-medium break-words">
                        {geracao.gerado_por}
                    </dd>
                </div>
            </dl>

            <div className="flex flex-col gap-2.5">
                {semModelo ? (
                    <>
                        <Button
                            variant="default"
                            className="min-h-11"
                            disabled
                            aria-describedby="dica-sem-modelo"
                        >
                            <RotateCcw />
                            Gerar de novo com estes dados
                        </Button>
                        <p
                            id="dica-sem-modelo"
                            className="text-sm text-muted-foreground"
                        >
                            O modelo foi excluído, por isso não dá para gerar de
                            novo com estes dados.
                        </p>
                    </>
                ) : (
                    <Button asChild className="min-h-11">
                        <Link
                            href={gerar(
                                { modelo: geracao.modelo_id ?? '' },
                                { query: { geracao: geracao.id } },
                            )}
                        >
                            <RotateCcw />
                            Gerar de novo com estes dados
                        </Link>
                    </Button>
                )}
                <Button
                    variant="outline"
                    className="min-h-11"
                    onClick={copiarDados}
                >
                    <Copy />
                    Copiar dados
                </Button>
            </div>
        </div>
    );
}
