import { Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CampoCertificadoController';
import CartaoDoCampo from '@/components/campos/cartao-do-campo';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import { moverCampo, reverterCampo, valoresDoNovoCampo } from '@/lib/editor';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    camposIniciais: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    somenteLeitura: boolean;
};

/** Ouvintes do arraste em andamento, guardados para poderem ser removidos a qualquer momento. */
type Arraste = {
    aoMover: (movimento: PointerEvent) => void;
    aoSoltar: (movimento: PointerEvent) => void;
    aoCancelar: () => void;
};

export default function CamposEditor({
    modelo,
    camposIniciais,
    fontes,
    pesos,
    somenteLeitura,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);

    const [campos, setCampos] = useState<Campo[]>(camposIniciais);
    const [arrastando, setArrastando] = useState<string | null>(null);
    const [salvando, setSalvando] = useState(0);
    const [criando, setCriando] = useState(false);
    const criandoRef = useRef(false);
    /** Último valor que o servidor confirmou para cada campo; é para onde a tela volta em caso de falha. */
    const confirmados = useRef(
        new Map<string, Campo>(
            camposIniciais.map((campo) => [campo.id, campo]),
        ),
    );
    const arrasteAtivo = useRef<Arraste | null>(null);

    const encerrarArraste = useCallback(() => {
        const ativo = arrasteAtivo.current;

        if (!ativo) {
            return;
        }

        window.removeEventListener('pointermove', ativo.aoMover);
        window.removeEventListener('pointerup', ativo.aoSoltar);
        window.removeEventListener('pointercancel', ativo.aoCancelar);
        arrasteAtivo.current = null;
    }, []);

    useEffect(() => () => encerrarArraste(), [encerrarArraste]);

    /** Aplica a mudança na tela na hora e salva; se o servidor recusar, desfaz só o que falhou. */
    const atualizar = async (id: string, mudancas: Partial<Campo>) => {
        if (!confirmados.current.has(id)) {
            // O campo foi excluído enquanto esta mudança esperava (ex.: cor pendente ao excluir).
            return;
        }

        setCampos((atuais) =>
            atuais.map((campo) =>
                campo.id === id ? { ...campo, ...mudancas } : campo,
            ),
        );
        setSalvando((n) => n + 1);

        try {
            const salvo = await enviarJson<Campo>(
                'PATCH',
                update.url({ modelo: modelo.id, campo: id }),
                mudancas,
            );

            if (salvo) {
                confirmados.current.set(id, salvo);
            }
        } catch (erro) {
            toast.error(mensagemDoErro(erro));

            const confirmado = confirmados.current.get(id);

            if (confirmado) {
                setCampos((atuais) =>
                    atuais.map((campo) =>
                        campo.id === id
                            ? reverterCampo(campo, mudancas, confirmado)
                            : campo,
                    ),
                );
            }
        } finally {
            setSalvando((n) => n - 1);
        }
    };

    const criar = async () => {
        if (criandoRef.current) {
            return;
        }

        criandoRef.current = true;
        setCriando(true);

        try {
            const criado = await enviarJson<Campo>(
                'POST',
                store.url({ modelo: modelo.id }),
                valoresDoNovoCampo(campos.at(-1)),
            );

            if (criado) {
                confirmados.current.set(criado.id, criado);
                setCampos((atuais) => [...atuais, criado]);
            }
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        } finally {
            criandoRef.current = false;
            setCriando(false);
        }
    };

    const excluir = async (id: string) => {
        const indice = campos.findIndex((campo) => campo.id === id);
        const removido = campos[indice];

        if (!removido) {
            return;
        }

        const confirmadoAntes = confirmados.current.get(id);
        confirmados.current.delete(id);
        setCampos((atuais) => atuais.filter((campo) => campo.id !== id));

        try {
            await enviarJson(
                'DELETE',
                destroy.url({ modelo: modelo.id, campo: id }),
            );
        } catch (erro) {
            toast.error(mensagemDoErro(erro));

            if (confirmadoAntes) {
                confirmados.current.set(id, confirmadoAntes);
            }

            setCampos((atuais) => {
                if (atuais.some((campo) => campo.id === id)) {
                    return atuais;
                }

                const restaurados = [...atuais];
                restaurados.splice(
                    Math.min(indice, restaurados.length),
                    0,
                    removido,
                );

                return restaurados;
            });
        }
    };

    const iniciarArraste = (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => {
        evento.preventDefault();
        encerrarArraste();

        const inicio = { mouseX: evento.clientX, mouseY: evento.clientY };
        const original = { x: campo.x, y: campo.y };
        const novaPosicao = (movimento: PointerEvent) =>
            moverCampo(
                { x: original.x, y: original.y },
                {
                    dx: movimento.clientX - inicio.mouseX,
                    dy: movimento.clientY - inicio.mouseY,
                },
                escala,
                campo.centralizado,
            );

        const aoMover = (movimento: PointerEvent) => {
            const posicao = novaPosicao(movimento);

            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id ? { ...atual, ...posicao } : atual,
                ),
            );
        };

        const aoSoltar = (movimento: PointerEvent) => {
            encerrarArraste();
            setArrastando(null);

            const posicao = novaPosicao(movimento);
            const mudou =
                posicao.y !== original.y ||
                (posicao.x !== undefined && posicao.x !== original.x);

            if (mudou) {
                void atualizar(campo.id, posicao);
            }
        };

        const aoCancelar = () => {
            encerrarArraste();
            setArrastando(null);
            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id
                        ? { ...atual, x: original.x, y: original.y }
                        : atual,
                ),
            );
        };

        arrasteAtivo.current = { aoMover, aoSoltar, aoCancelar };
        setArrastando(campo.id);

        window.addEventListener('pointermove', aoMover);
        window.addEventListener('pointerup', aoSoltar);
        window.addEventListener('pointercancel', aoCancelar);
    };

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="flex flex-col gap-4">
                <div>
                    <h2 className="text-lg font-semibold">Campos</h2>
                    <p className="text-sm text-muted-foreground">
                        Configure os campos de texto do certificado: posição,
                        cor e fonte.
                    </p>
                </div>

                {somenteLeitura && (
                    <Alert>
                        <AlertDescription>
                            Você pode ver os campos deste modelo, mas não
                            editá-los.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-3">
                    {campos.map((campo) => (
                        <CartaoDoCampo
                            key={campo.id}
                            campo={campo}
                            fontes={fontes}
                            pesos={pesos}
                            somenteLeitura={somenteLeitura}
                            aoAtualizar={(id, mudancas) =>
                                void atualizar(id, mudancas)
                            }
                            aoExcluir={(id) => void excluir(id)}
                        />
                    ))}
                </div>

                {!somenteLeitura && (
                    <button
                        type="button"
                        disabled={criando}
                        onClick={() => void criar()}
                        className="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                    >
                        <Plus className="size-4" />
                        Novo campo
                    </button>
                )}
            </div>

            <div className="flex flex-col gap-3">
                <div>
                    <h2 className="text-lg font-semibold">Preview</h2>
                    <p className="text-sm text-muted-foreground">
                        {somenteLeitura
                            ? 'Veja como os campos ficam sobre a imagem.'
                            : 'Arraste os campos para reposicioná-los sobre a imagem.'}
                    </p>
                </div>

                <PreviewDoModelo
                    modelo={modelo}
                    campos={campos}
                    somenteLeitura={somenteLeitura}
                    arrastando={arrastando}
                    aoIniciarArraste={iniciarArraste}
                />

                {!somenteLeitura && (
                    <p
                        className="text-right text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {salvando > 0
                            ? 'Salvando…'
                            : 'Alterações salvas automaticamente'}
                    </p>
                )}
            </div>
        </div>
    );
}
