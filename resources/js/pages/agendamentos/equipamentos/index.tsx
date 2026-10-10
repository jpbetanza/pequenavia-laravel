import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import DialogoDeEquipamento from '@/components/agendamentos/dialogo-de-equipamento';
import PainelDeEquipamentosDeHoje from '@/components/agendamentos/painel-de-equipamentos-de-hoje';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { corDoEspaco } from '@/lib/agenda/cores';
import {
    letraDoDia,
    textoDoAgora,
    tomDaCelula,
} from '@/lib/agenda/equipamentos';
import { cn } from '@/lib/utils';
import { index as rota } from '@/routes/agendamentos/equipamentos';
import type {
    DiaDeUsoDoEquipamento,
    EquipamentoResumo,
    PainelDeHoje,
} from '@/types/agendamentos';

type Props = {
    equipamentos: EquipamentoResumo[];
    hoje: PainelDeHoje;
};

/** Diálogo aberto: `null` fecha; `equipamento: null` cria um equipamento novo. */
type DialogoAberto = { equipamento: EquipamentoResumo | null } | null;

const COR_DO_USO = corDoEspaco('bordo');

/** Selo de "agora" por situação. Em uso usa a cor de espaço bordô, o restante são tokens do tema. */
function SeloDoAgora({ equipamento }: { equipamento: EquipamentoResumo }) {
    const { agora } = equipamento;
    const classes = cn(
        'inline-block rounded-full px-2.5 py-1 text-xs font-semibold',
        agora.situacao === 'manutencao' && 'bg-aviso-fundo text-aviso',
        agora.situacao === 'livre' && 'bg-sucesso-fundo text-sucesso',
    );

    return (
        <span
            className={classes}
            style={
                agora.situacao === 'em_uso'
                    ? {
                          backgroundColor: COR_DO_USO.fundo,
                          color: COR_DO_USO.texto,
                      }
                    : undefined
            }
        >
            {textoDoAgora(agora)}
        </span>
    );
}

/** Uma célula da semana: letra, "pico/quantidade". Tom mais forte quando todas as unidades estão em uso. */
function CelulaDaSemana({ dia }: { dia: DiaDeUsoDoEquipamento }) {
    const tom = tomDaCelula(dia.pico, dia.quantidade);
    const rotulo = `${letraDoDia(dia.data)} ${dia.pico}/${dia.quantidade}`;

    return (
        <span
            title={`${dia.data.split('-').reverse().join('/')}: ${dia.pico} de ${dia.quantidade} em uso no pico`}
            className={cn(
                'rounded-md py-1',
                tom === 'cheia' && 'bg-primary text-primary-foreground',
                tom === 'vazia' && 'bg-muted text-muted-foreground',
            )}
            style={
                tom === 'parcial'
                    ? {
                          backgroundColor: COR_DO_USO.fundo,
                          color: COR_DO_USO.texto,
                      }
                    : undefined
            }
        >
            {rotulo}
        </span>
    );
}

export default function Equipamentos({ equipamentos, hoje }: Props) {
    const [dialogo, setDialogo] = useState<DialogoAberto>(null);

    return (
        <>
            <Head title="Equipamentos" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Equipamentos"
                    subtitulo="Itens que circulam entre as salas. Eles são reservados junto com a sala, e o sistema avisa quando não há unidades livres no horário."
                    acoes={
                        <Button
                            className="h-11 rounded-full px-5"
                            onClick={() => setDialogo({ equipamento: null })}
                        >
                            <Plus />
                            Novo equipamento
                        </Button>
                    }
                />

                <section
                    aria-label="Equipamentos cadastrados"
                    className="overflow-hidden rounded-2xl border bg-card"
                >
                    <div className="overflow-x-auto">
                        <Table className="min-w-[960px]">
                            <TableHeader>
                                <TableRow className="bg-muted/60 hover:bg-muted/60">
                                    <TableHead className="px-4 text-xs tracking-[0.1em] uppercase">
                                        Equipamento
                                    </TableHead>
                                    <TableHead className="text-xs tracking-[0.1em] uppercase">
                                        Unidades
                                    </TableHead>
                                    <TableHead className="text-xs tracking-[0.1em] uppercase">
                                        Onde fica guardado
                                    </TableHead>
                                    <TableHead className="text-xs tracking-[0.1em] uppercase">
                                        Agora
                                    </TableHead>
                                    <TableHead className="w-72 text-xs tracking-[0.1em] uppercase">
                                        Em uso nesta semana
                                    </TableHead>
                                    <TableHead className="px-4">
                                        <span className="sr-only">Ações</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {equipamentos.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center gap-3">
                                                <p>
                                                    Você ainda não tem
                                                    equipamentos. Cadastre o
                                                    primeiro para começar.
                                                </p>
                                                <Button
                                                    className="min-h-11"
                                                    onClick={() =>
                                                        setDialogo({
                                                            equipamento: null,
                                                        })
                                                    }
                                                >
                                                    <Plus />
                                                    Novo equipamento
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}

                                {equipamentos.map((equipamento) => (
                                    <TableRow
                                        key={equipamento.id}
                                        className={cn(
                                            equipamento.em_manutencao &&
                                                'opacity-60',
                                        )}
                                    >
                                        <TableCell className="px-4 py-3.5 align-top whitespace-normal">
                                            <span className="font-semibold">
                                                {equipamento.nome}
                                            </span>
                                            {equipamento.descricao && (
                                                <span className="block text-sm text-muted-foreground">
                                                    {equipamento.descricao}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top text-lg font-semibold tabular-nums">
                                            {equipamento.quantidade}
                                        </TableCell>
                                        <TableCell className="align-top whitespace-normal">
                                            {equipamento.local_guarda ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>

                                        {equipamento.em_manutencao ? (
                                            <TableCell
                                                colSpan={2}
                                                className="align-top whitespace-normal"
                                            >
                                                <SeloDoAgora
                                                    equipamento={equipamento}
                                                />{' '}
                                                <span className="text-sm text-muted-foreground">
                                                    · não aparece para reservar
                                                </span>
                                            </TableCell>
                                        ) : (
                                            <>
                                                <TableCell className="align-top whitespace-normal">
                                                    <SeloDoAgora
                                                        equipamento={
                                                            equipamento
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="px-2 py-3.5 align-top">
                                                    <div className="grid grid-cols-7 gap-1 text-center text-[11px]">
                                                        {equipamento.semana.map(
                                                            (dia) => (
                                                                <CelulaDaSemana
                                                                    key={
                                                                        dia.data
                                                                    }
                                                                    dia={dia}
                                                                />
                                                            ),
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </>
                                        )}

                                        <TableCell className="px-4 text-right align-top">
                                            <Button
                                                variant="outline"
                                                className="min-h-11"
                                                aria-label={`Editar ${equipamento.nome}`}
                                                onClick={() =>
                                                    setDialogo({ equipamento })
                                                }
                                            >
                                                <Pencil />
                                                Editar
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <PainelDeEquipamentosDeHoje painel={hoje} />
            </div>

            {dialogo && (
                <DialogoDeEquipamento
                    equipamento={dialogo.equipamento}
                    aoFechar={() => setDialogo(null)}
                />
            )}
        </>
    );
}

Equipamentos.layout = {
    breadcrumbs: [{ title: 'Equipamentos', href: rota() }],
};
