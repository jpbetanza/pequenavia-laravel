import { useEffect, useRef, useState } from 'react';
import { escalaDoPreview } from '@/lib/editor';
import type { Campo, ModeloBasico, ModoDeExibicao } from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    somenteLeitura: boolean;
    arrastando: string | null;
    /** Sem seleção (ex.: tela de geração, só leitura). */
    selecionadoId?: string | null;
    /** Padrão: texto de exemplo, como antes do editor de três colunas. */
    modo?: ModoDeExibicao;
    /** Valores reais (ex.: uma linha da geração). Campo vazio mostra o nome do campo, esmaecido. */
    valores?: Record<string, string>;
    /** Avisa o tamanho natural da imagem do modelo quando ela carrega. */
    aoCarregar?: (natural: { w: number; h: number }) => void;
    aoSelecionar?: (id: string) => void;
    aoIniciarArraste: (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => void;
    aoTecla?: (evento: React.KeyboardEvent<HTMLDivElement>) => void;
};

const ALCA: React.CSSProperties = {
    position: 'absolute',
    width: 8,
    height: 8,
    background: 'var(--card)',
    border: '1.5px solid var(--primary)',
    borderRadius: 2,
};

/**
 * A imagem do modelo com o texto de cada campo por cima. O campo centralizado usa `left: 50%` e
 * `translateX(-50%)`: o navegador centraliza a caixa do próprio texto, que inclui o espaçamento depois
 * da última letra, igual ao que o servidor faz ao gerar o PNG.
 */
export default function PreviewDoModelo({
    modelo,
    campos,
    somenteLeitura,
    arrastando,
    selecionadoId = null,
    modo = 'exemplo',
    valores,
    aoCarregar,
    aoSelecionar,
    aoIniciarArraste,
    aoTecla,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const [largura, setLargura] = useState(0);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(
        null,
    );

    useEffect(() => {
        const elemento = container.current;

        if (!elemento) {
            return;
        }

        const observador = new ResizeObserver(([entrada]) =>
            setLargura(entrada.contentRect.width),
        );
        observador.observe(elemento);

        return () => observador.disconnect();
    }, []);

    const escala = escalaDoPreview(largura, natural?.w ?? 0);
    const selecionado = campos.find((campo) => campo.id === selecionadoId);

    return (
        <div className="flex min-w-0 flex-col gap-3 rounded-xl bg-mesa p-4 sm:p-6">
            <div
                ref={container}
                role="group"
                aria-label={
                    somenteLeitura
                        ? 'Imagem do modelo'
                        : 'Imagem do modelo. Com um campo selecionado, use as setas do teclado para movê-lo.'
                }
                tabIndex={somenteLeitura ? undefined : 0}
                onKeyDown={somenteLeitura ? undefined : aoTecla}
                onPointerDown={(evento) => {
                    if (!somenteLeitura) {
                        evento.currentTarget.focus({ preventScroll: true });
                    }
                }}
                className="relative w-full rounded-lg border bg-card select-none focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                style={
                    natural
                        ? { height: largura * (natural.h / natural.w) }
                        : { aspectRatio: '4 / 3' }
                }
            >
                <img
                    src={modelo.imagem_url}
                    alt={modelo.title}
                    draggable={false}
                    className="pointer-events-none size-full rounded-lg object-contain"
                    onLoad={(evento) => {
                        const dimensoes = {
                            w: evento.currentTarget.naturalWidth,
                            h: evento.currentTarget.naturalHeight,
                        };

                        setNatural(dimensoes);
                        aoCarregar?.(dimensoes);
                    }}
                />

                {natural && selecionado?.centralizado && (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-y-0 left-1/2 border-l border-dashed"
                        style={{ borderColor: 'var(--primary)' }}
                    />
                )}

                {natural &&
                    campos.map((campo) => {
                        const emArraste = arrastando === campo.id;
                        const selecionadoAgora = campo.id === selecionadoId;
                        const exibido =
                            modo === 'nomes'
                                ? campo.nome
                                : campo.label?.trim() || campo.nome;
                        const valor = valores?.[campo.nome] ?? '';
                        const vazioComValores =
                            valores !== undefined && valor.trim() === '';
                        const texto =
                            valores === undefined || vazioComValores
                                ? exibido
                                : valor;
                        const posicao = campo.centralizado
                            ? `centro, y ${campo.y}`
                            : `x ${campo.x}, y ${campo.y}`;

                        return (
                            <span
                                key={campo.id}
                                aria-hidden="true"
                                onPointerDown={(evento) => {
                                    aoSelecionar?.(campo.id);

                                    if (!somenteLeitura) {
                                        aoIniciarArraste(evento, campo, escala);
                                    }
                                }}
                                style={{
                                    position: 'absolute',
                                    left: campo.centralizado
                                        ? '50%'
                                        : campo.x * escala,
                                    transform: campo.centralizado
                                        ? 'translateX(-50%)'
                                        : undefined,
                                    top: campo.y * escala,
                                    color: campo.cor,
                                    fontSize: campo.tamanho_fonte * escala,
                                    fontFamily: `"${campo.fonte_familia}"`,
                                    fontWeight: campo.peso_fonte,
                                    letterSpacing: `${campo.espacamento_letras * escala}px`,
                                    lineHeight: 1,
                                    whiteSpace: 'nowrap',
                                    userSelect: 'none',
                                    touchAction: 'none',
                                    pointerEvents: somenteLeitura
                                        ? 'none'
                                        : 'auto',
                                    cursor: somenteLeitura
                                        ? 'default'
                                        : campo.centralizado
                                          ? emArraste
                                              ? 'ns-resize'
                                              : 'row-resize'
                                          : emArraste
                                            ? 'grabbing'
                                            : 'grab',
                                    outline:
                                        selecionadoAgora || somenteLeitura
                                            ? 'none'
                                            : '1px dashed color-mix(in srgb, var(--foreground) 45%, transparent)',
                                    outlineOffset: 4,
                                    borderRadius: 2,
                                    opacity: vazioComValores ? 0.4 : undefined,
                                }}
                            >
                                {texto}

                                {selecionadoAgora && !somenteLeitura && (
                                    <>
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none absolute"
                                            style={{
                                                inset: -6,
                                                border: '2px solid var(--primary)',
                                                borderRadius: 3,
                                            }}
                                        />
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none"
                                            style={{
                                                ...ALCA,
                                                top: -10,
                                                left: -10,
                                            }}
                                        />
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none"
                                            style={{
                                                ...ALCA,
                                                top: -10,
                                                right: -10,
                                            }}
                                        />
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none"
                                            style={{
                                                ...ALCA,
                                                bottom: -10,
                                                left: -10,
                                            }}
                                        />
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none"
                                            style={{
                                                ...ALCA,
                                                bottom: -10,
                                                right: -10,
                                            }}
                                        />
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none absolute rounded-sm bg-primary px-1.5 py-1 text-[11px] font-medium whitespace-nowrap text-primary-foreground"
                                            style={{
                                                left: -6,
                                                bottom: 'calc(100% + 8px)',
                                                fontFamily: 'var(--font-sans)',
                                                fontWeight: 500,
                                                letterSpacing: 0,
                                            }}
                                        >
                                            {`${campo.nome} · ${posicao}`}
                                        </span>
                                    </>
                                )}
                            </span>
                        );
                    })}
            </div>

            {natural && (
                <p className="text-center text-xs text-muted-foreground">
                    Imagem original {natural.w} × {natural.h} px · zoom{' '}
                    {Math.round(escala * 100)}%
                </p>
            )}
        </div>
    );
}
