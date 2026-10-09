import { Plus } from 'lucide-react';
import { useState } from 'react';
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
import { moverCampo, valoresDoNovoCampo } from '@/lib/editor';
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

    /** Aplica a mudança na tela na hora e salva; se o servidor recusar, volta ao valor anterior. */
    const atualizar = async (id: string, mudancas: Partial<Campo>) => {
        const anterior = campos.find((campo) => campo.id === id);

        setCampos((atuais) =>
            atuais.map((campo) =>
                campo.id === id ? { ...campo, ...mudancas } : campo,
            ),
        );
        setSalvando((n) => n + 1);

        try {
            await enviarJson(
                'PATCH',
                update.url({ modelo: modelo.id, campo: id }),
                mudancas,
            );
        } catch (erro) {
            toast.error(mensagemDoErro(erro));

            if (anterior) {
                const reverso = Object.fromEntries(
                    Object.keys(mudancas).map((chave) => [
                        chave,
                        anterior[chave as keyof Campo],
                    ]),
                );

                setCampos((atuais) =>
                    atuais.map((campo) =>
                        campo.id === id ? { ...campo, ...reverso } : campo,
                    ),
                );
            }
        } finally {
            setSalvando((n) => n - 1);
        }
    };

    const criar = async () => {
        try {
            const criado = await enviarJson<Campo>(
                'POST',
                store.url({ modelo: modelo.id }),
                valoresDoNovoCampo(campos.at(-1)),
            );

            if (criado) {
                setCampos((atuais) => [...atuais, criado]);
            }
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        }
    };

    const excluir = async (id: string) => {
        const antes = campos;

        setCampos((atuais) => atuais.filter((campo) => campo.id !== id));

        try {
            await enviarJson(
                'DELETE',
                destroy.url({ modelo: modelo.id, campo: id }),
            );
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
            setCampos(antes);
        }
    };

    const iniciarArraste = (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => {
        evento.preventDefault();

        const inicio = {
            mouseX: evento.clientX,
            mouseY: evento.clientY,
            x: campo.x,
            y: campo.y,
        };
        const novaPosicao = (movimento: PointerEvent) =>
            moverCampo(
                { x: inicio.x, y: inicio.y },
                {
                    dx: movimento.clientX - inicio.mouseX,
                    dy: movimento.clientY - inicio.mouseY,
                },
                escala,
                campo.centralizado,
            );

        setArrastando(campo.id);

        const aoMover = (movimento: PointerEvent) => {
            const posicao = novaPosicao(movimento);

            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id ? { ...atual, ...posicao } : atual,
                ),
            );
        };

        const aoSoltar = (movimento: PointerEvent) => {
            window.removeEventListener('pointermove', aoMover);
            window.removeEventListener('pointerup', aoSoltar);
            setArrastando(null);
            void atualizar(campo.id, novaPosicao(movimento));
        };

        window.addEventListener('pointermove', aoMover);
        window.addEventListener('pointerup', aoSoltar);
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
                        onClick={() => void criar()}
                        className="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
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
