import { Head, Link, usePage } from '@inertiajs/react';
import PainelDeEquipamentosDeHoje from '@/components/agendamentos/painel-de-equipamentos-de-hoje';
import { AtalhoDeCertificados } from '@/components/inicio/atalho-de-certificados';
import { HojeNasSalas } from '@/components/inicio/hoje-nas-salas';
import { PedidosDoInicio } from '@/components/inicio/pedidos-do-inicio';
import { SaudacaoDoInicio } from '@/components/inicio/saudacao-do-inicio';
import { Button } from '@/components/ui/button';
import { equipamentosParaSeparar, resumoDoDia } from '@/lib/inicio/resumo';
import { index as equipamentos } from '@/routes/agendamentos/equipamentos';
import type { PropsDoInicio } from '@/types/inicio';

export default function Inicio({
    hoje,
    reservas,
    solicitacoes,
    equipamentos_hoje,
    certificados,
}: PropsDoInicio) {
    const { auth } = usePage().props;
    const resumo = resumoDoDia({
        reservas: reservas.length,
        pedidos: solicitacoes.total,
        separar: equipamentosParaSeparar(equipamentos_hoje),
    });

    return (
        <>
            <Head title="Início" />
            <div className="flex flex-col gap-6 p-4 md:p-7">
                <SaudacaoDoInicio
                    hoje={hoje}
                    nome={auth.user.name}
                    resumo={resumo}
                />

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <HojeNasSalas reservas={reservas} />

                    <div className="flex flex-col gap-6">
                        <PedidosDoInicio
                            total={solicitacoes.total}
                            primeiras={solicitacoes.primeiras}
                        />

                        <section
                            aria-labelledby="separar-do-inicio"
                            className="flex flex-col gap-3 rounded-[18px] border border-border bg-card p-5 sm:p-6"
                        >
                            <div className="flex items-baseline justify-between gap-3">
                                <h2
                                    id="separar-do-inicio"
                                    className="font-serif text-3xl leading-none font-semibold"
                                >
                                    Separar e recolher
                                </h2>
                                <Button
                                    asChild
                                    variant="link"
                                    className="h-auto min-h-11 p-0 font-semibold whitespace-nowrap"
                                >
                                    <Link href={equipamentos()} prefetch>
                                        Equipamentos →
                                    </Link>
                                </Button>
                            </div>
                            <PainelDeEquipamentosDeHoje
                                painel={equipamentos_hoje}
                                semTitulo
                            />
                        </section>

                        <AtalhoDeCertificados
                            emitidos={certificados.emitidos_no_mes}
                            dataHoje={hoje.data}
                            ultimo={certificados.ultimo}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
