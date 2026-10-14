import { Head, Link, usePage } from '@inertiajs/react';
import PainelDeEquipamentosDeHoje from '@/components/agendamentos/painel-de-equipamentos-de-hoje';
import { AtalhoDeCertificados } from '@/components/inicio/atalho-de-certificados';
import { HojeNasSalas } from '@/components/inicio/hoje-nas-salas';
import { PedidosDoInicio } from '@/components/inicio/pedidos-do-inicio';
import { SaudacaoDoInicio } from '@/components/inicio/saudacao-do-inicio';
import {
    BannerDeBoasVindas,
    BannerDeConcluido,
} from '@/components/primeiros-passos/banner-de-boas-vindas';
import { FaixaDeAndamento } from '@/components/primeiros-passos/faixa-de-andamento';
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
    const { auth, primeiros_passos: roteiro } = usePage().props;
    const roteiroVisivel = roteiro !== null && !roteiro.adiado;
    const resumo = resumoDoDia({
        reservas: reservas.length,
        pedidos: solicitacoes.total,
        separar: equipamentosParaSeparar(equipamentos_hoje),
    });

    return (
        <>
            <Head title="Início" />
            <div className="@container flex min-w-0 flex-col gap-6 p-4 md:p-7">
                {roteiroVisivel && roteiro.fase === 'inicio' ? (
                    <BannerDeBoasVindas
                        roteiro={roteiro}
                        nome={auth.user.name}
                    />
                ) : roteiroVisivel && roteiro.fase === 'concluido' ? (
                    <BannerDeConcluido
                        roteiro={roteiro}
                        nome={auth.user.name}
                    />
                ) : (
                    <SaudacaoDoInicio
                        hoje={hoje}
                        nome={auth.user.name}
                        resumo={resumo}
                    />
                )}

                {roteiroVisivel && roteiro.fase === 'andamento' && (
                    <FaixaDeAndamento roteiro={roteiro} />
                )}

                <div className="grid grid-cols-1 items-start gap-6 @4xl:grid-cols-2">
                    <HojeNasSalas reservas={reservas} />

                    <div className="flex min-w-0 flex-col gap-6">
                        <PedidosDoInicio
                            total={solicitacoes.total}
                            primeiras={solicitacoes.primeiras}
                        />

                        <section
                            aria-labelledby="separar-do-inicio"
                            className="flex min-w-0 flex-col gap-3 rounded-[18px] border border-border bg-card p-5 sm:p-6"
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
