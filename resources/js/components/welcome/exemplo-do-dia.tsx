import type { CSSProperties, ReactNode } from 'react';
import { type CorDoEspaco, corDoEspaco } from '@/lib/agenda/cores';

const HORAS = [14, 15, 16, 17, 18, 19, 20];
const ALTURA_DA_HORA = 48;

const SALAS: { nome: string; cor: CorDoEspaco }[] = [
    { nome: 'Salão São José', cor: 'bordo' },
    { nome: 'Sala Sta. Teresinha', cor: 'azul' },
    { nome: 'Capela', cor: 'verde' },
];

function Bloco({
    cor,
    topo,
    altura,
    titulo,
    horario,
    children,
}: {
    cor: CorDoEspaco;
    topo: number;
    altura: number;
    titulo: string;
    horario: string;
    children?: ReactNode;
}) {
    const paleta = corDoEspaco(cor);
    const estilo: CSSProperties = {
        top: topo,
        height: altura,
        background: paleta.fundo,
        borderColor: paleta.forte,
        color: paleta.texto,
    };

    return (
        <div
            className="absolute inset-x-0 rounded-md border-l-[3px] px-2 py-1.5 text-xs"
            style={estilo}
        >
            <b className="block text-xs">{titulo}</b>
            {horario}
            {children}
        </div>
    );
}

function Ponto({ cor }: { cor: CorDoEspaco }) {
    return (
        <span
            aria-hidden="true"
            className="size-2 shrink-0 rounded-full"
            style={{ background: corDoEspaco(cor).forte }}
        />
    );
}

export function ExemploDoDia() {
    return (
        <figure
            aria-label="Exemplo: ocupação de sábado por sala"
            className="m-0 box-border w-full rounded-[18px] border border-border bg-card p-5 shadow-[0_30px_60px_-34px_rgba(60,30,20,0.45)] lg:absolute lg:top-0 lg:left-0 lg:w-[88%]"
        >
            <div className="mb-3.5 flex items-baseline justify-between">
                <p className="font-serif text-[26px] leading-none font-semibold">
                    Sábado, 17 de outubro
                </p>
                <span className="text-xs font-semibold text-muted-foreground">
                    6 reservas
                </span>
            </div>

            <div className="grid grid-cols-[34px_repeat(3,1fr)] gap-x-2 text-xs">
                <span />
                {SALAS.map((sala) => (
                    <span
                        key={sala.nome}
                        className="flex items-center gap-1.5 pb-2 font-semibold"
                    >
                        <Ponto cor={sala.cor} />
                        {sala.nome}
                    </span>
                ))}
            </div>

            <div className="relative grid h-[336px] grid-cols-[34px_repeat(3,1fr)] gap-x-2 text-[11px] text-muted-foreground">
                <div className="relative">
                    {HORAS.map((hora, indice) => (
                        <span
                            key={hora}
                            className="absolute"
                            style={{ top: indice * ALTURA_DA_HORA - 6 }}
                        >
                            {hora}h
                        </span>
                    ))}
                </div>

                <div
                    aria-hidden="true"
                    className="absolute inset-y-0 right-0 left-[42px] bg-[repeating-linear-gradient(#EDE4D3_0_1px,transparent_1px_48px)]"
                />

                <div className="relative">
                    <Bloco
                        cor="bordo"
                        topo={50}
                        altura={92}
                        titulo="Catequese · Crisma"
                        horario="15h–17h"
                    />
                    <Bloco
                        cor="bordo"
                        topo={266}
                        altura={70}
                        titulo="Ensaio do coral"
                        horario="19h30–21h"
                    />
                </div>

                <div className="relative">
                    <Bloco
                        cor="azul"
                        topo={2}
                        altura={92}
                        titulo="Pastoral da Criança"
                        horario="14h–16h"
                    />
                    <Bloco
                        cor="azul"
                        topo={194}
                        altura={92}
                        titulo="Grupo de oração"
                        horario="18h–20h"
                    >
                        <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-background px-1.5 py-px text-[10px] font-semibold">
                            Projetor ×1
                        </span>
                    </Bloco>
                </div>

                <div className="relative">
                    <Bloco
                        cor="verde"
                        topo={98}
                        altura={44}
                        titulo="Batizados"
                        horario="16h–17h"
                    />
                    <Bloco
                        cor="verde"
                        topo={242}
                        altura={44}
                        titulo="Terço dos homens"
                        horario="19h–20h"
                    />
                </div>

                <div
                    aria-hidden="true"
                    className="absolute top-[113px] right-0 left-9 h-0.5 bg-primary"
                >
                    <span className="absolute -top-[3px] -left-1 size-2 rounded-full bg-primary" />
                </div>
            </div>
        </figure>
    );
}
