import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { gerar, index } from '@/routes/meus-certificados';
import type { ModeloBasico, ModoDeExibicao } from '@/types/certificados';

/** `inicial`: nada foi salvo ainda nesta tela, então não há o que confirmar. */
export type EstadoDoSalvamento = 'inicial' | 'salvando' | 'salvo' | 'erro';

type Props = {
    modelo: ModeloBasico;
    temCampos: boolean;
    modo: ModoDeExibicao;
    aoMudarModo: (modo: ModoDeExibicao) => void;
    /** Null no modo somente leitura, quando não há o que salvar. */
    estado: EstadoDoSalvamento | null;
};

const OPCOES_DE_EXIBICAO: { valor: ModoDeExibicao; rotulo: string }[] = [
    { valor: 'nomes', rotulo: 'Nomes dos campos' },
    { valor: 'exemplo', rotulo: 'Com dados de exemplo' },
];

function SeloDoSalvamento({ estado }: { estado: EstadoDoSalvamento }) {
    const texto = {
        inicial: 'Alterações salvas automaticamente',
        salvando: 'Salvando…',
        salvo: 'Salvo agora',
        erro: 'Não foi possível salvar',
    }[estado];

    return (
        <span
            role="status"
            aria-live="polite"
            className={cn(
                'rounded-full px-3 py-1 text-xs font-medium',
                estado === 'salvo' && 'bg-sucesso-fundo text-sucesso',
                (estado === 'inicial' || estado === 'salvando') &&
                    'bg-muted text-muted-foreground',
                estado === 'erro' && 'bg-muted text-destructive',
            )}
        >
            {texto}
        </span>
    );
}

export default function BarraDoEditor({
    modelo,
    temCampos,
    modo,
    aoMudarModo,
    estado,
}: Props) {
    return (
        <header className="flex flex-wrap items-center gap-x-4 gap-y-3 border-b pb-4">
            <Link
                href={index()}
                aria-label="Voltar para Modelos"
                className="flex size-11 shrink-0 items-center justify-center rounded-lg border text-foreground transition-colors hover:bg-card"
            >
                <ArrowLeft className="size-5" aria-hidden="true" />
            </Link>

            <div className="flex min-w-0 flex-1 flex-col">
                <p className="text-sm text-muted-foreground">
                    <Link
                        href={index()}
                        className="font-medium text-primary hover:text-foreground"
                    >
                        Modelos
                    </Link>{' '}
                    / Campos
                </p>
                <h1 className="truncate font-serif text-3xl leading-tight font-semibold">
                    {modelo.title}
                </h1>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                {estado && <SeloDoSalvamento estado={estado} />}

                <div
                    role="group"
                    aria-label="Exibição dos textos"
                    className="flex rounded-lg border bg-card p-1"
                >
                    {OPCOES_DE_EXIBICAO.map((opcao) => (
                        <button
                            key={opcao.valor}
                            type="button"
                            aria-pressed={modo === opcao.valor}
                            onClick={() => aoMudarModo(opcao.valor)}
                            className={cn(
                                'min-h-11 rounded-md px-3 text-sm transition-colors',
                                modo === opcao.valor
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {opcao.rotulo}
                        </button>
                    ))}
                </div>

                {temCampos ? (
                    <Button asChild className="min-h-11">
                        <Link href={gerar(modelo.id)}>Ir para geração →</Link>
                    </Button>
                ) : (
                    <Button className="min-h-11" disabled>
                        Ir para geração →
                    </Button>
                )}
            </div>
        </header>
    );
}
