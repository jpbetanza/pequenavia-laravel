import { Head } from '@inertiajs/react';
import { Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DialogoDeGrupo from '@/components/agendamentos/dialogo-de-grupo';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import { ConviteDaTelaVazia } from '@/components/primeiros-passos/convite-da-tela-vazia';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { filtrarGrupos } from '@/lib/agenda/grupos';
import { index as rota } from '@/routes/agendamentos/grupos';
import type { GrupoResumo } from '@/types/agendamentos';

type Props = {
    grupos: GrupoResumo[];
};

/** Diálogo aberto: `null` fecha; `grupo: null` cria um grupo novo. */
type DialogoAberto = { grupo: GrupoResumo | null } | null;

export default function Grupos({ grupos }: Props) {
    const [busca, setBusca] = useState('');
    const [dialogo, setDialogo] = useState<DialogoAberto>(null);

    const buscando = busca.trim() !== '';
    const gruposFiltrados = filtrarGrupos(grupos, busca);

    return (
        <>
            <Head title="Grupos e pastorais" />
            <div className="flex flex-col gap-10 p-4">
                <CabecalhoDaPagina
                    titulo="Grupos e pastorais"
                    subtitulo="Quem usa os espaços, com o contato de quem coordena e as reuniões fixas."
                    acoes={
                        <>
                            <div className="relative w-full sm:w-64">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    aria-label="Buscar grupo"
                                    placeholder="Buscar grupo"
                                    value={busca}
                                    onChange={(evento) =>
                                        setBusca(evento.target.value)
                                    }
                                    className="h-11 rounded-full bg-card pl-10"
                                />
                            </div>
                            <Button
                                className="h-11 rounded-full px-5"
                                onClick={() => setDialogo({ grupo: null })}
                            >
                                <Plus />
                                Novo grupo
                            </Button>
                        </>
                    }
                />

                {grupos.length === 0 && (
                    <ConviteDaTelaVazia cadastro="grupos" />
                )}

                <section
                    aria-label="Grupos e pastorais cadastrados"
                    className="overflow-hidden rounded-2xl border bg-card"
                >
                    <div className="overflow-x-auto">
                        <Table className="min-w-[760px]">
                            <TableHeader>
                                <TableRow className="bg-muted/60 hover:bg-muted/60">
                                    <TableHead className="px-4 text-xs tracking-[0.1em] uppercase">
                                        Grupo
                                    </TableHead>
                                    <TableHead className="text-xs tracking-[0.1em] uppercase">
                                        Coordenação
                                    </TableHead>
                                    <TableHead className="text-xs tracking-[0.1em] uppercase">
                                        Reuniões fixas
                                    </TableHead>
                                    <TableHead className="px-4">
                                        <span className="sr-only">Ações</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {gruposFiltrados.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell
                                            colSpan={4}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            {buscando ? (
                                                'Nenhum grupo corresponde à busca.'
                                            ) : (
                                                <div className="flex flex-col items-center gap-3">
                                                    <p>
                                                        Você ainda não tem
                                                        grupos. Crie o primeiro
                                                        para começar.
                                                    </p>
                                                    <Button
                                                        className="min-h-11"
                                                        onClick={() =>
                                                            setDialogo({
                                                                grupo: null,
                                                            })
                                                        }
                                                    >
                                                        <Plus />
                                                        Novo grupo
                                                    </Button>
                                                </div>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                )}

                                {gruposFiltrados.map((grupo) => (
                                    <TableRow key={grupo.id}>
                                        <TableCell className="px-4 py-3.5 align-top whitespace-normal">
                                            <span className="font-semibold">
                                                {grupo.nome}
                                            </span>
                                            {!grupo.ativo && (
                                                <span className="block text-sm text-muted-foreground">
                                                    Inativo
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top whitespace-normal">
                                            {grupo.coordenacao ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                            {grupo.contato && (
                                                <span className="block text-sm text-muted-foreground">
                                                    {grupo.contato}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="align-top whitespace-normal">
                                            {grupo.reunioes.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    Nenhuma
                                                </span>
                                            ) : (
                                                grupo.reunioes.map(
                                                    (reuniao, indice) => (
                                                        <span
                                                            key={indice}
                                                            className="block"
                                                        >
                                                            {reuniao}
                                                        </span>
                                                    ),
                                                )
                                            )}
                                        </TableCell>
                                        <TableCell className="px-4 text-right align-top">
                                            <Button
                                                variant="outline"
                                                className="min-h-11"
                                                aria-label={`Editar ${grupo.nome}`}
                                                onClick={() =>
                                                    setDialogo({ grupo })
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

                <p className="text-sm text-muted-foreground">
                    Os grupos ativos aparecem na lista “Pastoral ou grupo” do
                    formulário de solicitação.
                </p>
            </div>

            {dialogo && (
                <DialogoDeGrupo
                    grupo={dialogo.grupo}
                    aoFechar={() => setDialogo(null)}
                />
            )}
        </>
    );
}

Grupos.layout = {
    breadcrumbs: [{ title: 'Grupos e pastorais', href: rota() }],
};
