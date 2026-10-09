import { useEffect, useRef, useState } from 'react';
import { escalaDoPreview } from '@/lib/editor';
import type { Campo, ModeloBasico } from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    somenteLeitura: boolean;
    arrastando: string | null;
    aoIniciarArraste: (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => void;
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
    aoIniciarArraste,
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

    return (
        <div
            ref={container}
            className="relative w-full overflow-hidden rounded-lg border select-none"
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
                className="size-full object-contain"
                onLoad={(evento) =>
                    setNatural({
                        w: evento.currentTarget.naturalWidth,
                        h: evento.currentTarget.naturalHeight,
                    })
                }
            />

            {natural &&
                campos.map((campo) => {
                    const emArraste = arrastando === campo.id;

                    return (
                        <span
                            key={campo.id}
                            onPointerDown={
                                somenteLeitura
                                    ? undefined
                                    : (evento) =>
                                          aoIniciarArraste(
                                              evento,
                                              campo,
                                              escala,
                                          )
                            }
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
                                pointerEvents: somenteLeitura ? 'none' : 'auto',
                                cursor: somenteLeitura
                                    ? 'default'
                                    : campo.centralizado
                                      ? emArraste
                                          ? 'ns-resize'
                                          : 'row-resize'
                                      : emArraste
                                        ? 'grabbing'
                                        : 'grab',
                                outline: emArraste
                                    ? '2px dashed rgba(255,255,255,0.7)'
                                    : 'none',
                                outlineOffset: 4,
                                borderRadius: 2,
                            }}
                        >
                            {campo.label?.trim() || campo.nome}
                        </span>
                    );
                })}
        </div>
    );
}
