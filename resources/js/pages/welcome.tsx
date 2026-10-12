import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    Link as IconeDeLink,
    Pencil,
    Projector,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { ExemploDeCertificado } from '@/components/welcome/exemplo-de-certificado';
import { ExemploDePedido } from '@/components/welcome/exemplo-de-pedido';
import { ExemploDoDia } from '@/components/welcome/exemplo-do-dia';
import {
    ExemploDoAssistente,
    PainelDoAssistente,
} from '@/components/welcome/exemplo-do-assistente';
import { Button } from '@/components/ui/button';
import { home, inicio, login, register } from '@/routes';

const recursosDaAgenda = [
    {
        icone: CalendarDays,
        titulo: 'A semana inteira, por sala',
        descricao:
            'Cada espaço com sua cor e seus horários de funcionamento. Reservas que se repetem toda semana ou todo mês entram de uma vez.',
    },
    {
        icone: TriangleAlert,
        titulo: 'Conflito avisado na hora',
        descricao:
            'Sala ocupada, fora do horário, bloqueada para reforma ou sem projetor sobrando: o aviso aparece antes de você salvar, com uma sugestão de horário livre.',
    },
    {
        icone: Projector,
        titulo: 'Equipamentos que circulam',
        descricao:
            'Dois projetores para cinco salas? O sistema conta quantos estão em uso em cada horário e mostra o que separar e recolher hoje.',
    },
    {
        icone: IconeDeLink,
        titulo: 'Um link para as pastorais',
        descricao:
            'Quem coordena um grupo escolhe a pastoral, o espaço e o horário, deixa nome e telefone. Sem criar conta.',
    },
];

const recursosDoAssistente = [
    {
        icone: Search,
        titulo: 'Consulta',
        descricao:
            '“O que tem amanhã?”, “Quem pediu o salão no sábado?”, “Quantos batismos emitimos este mês?”',
    },
    {
        icone: CalendarDays,
        titulo: 'Encontra horário',
        descricao:
            'Procura a sala certa para o tamanho do grupo e sugere o próximo horário livre.',
    },
    {
        icone: Pencil,
        titulo: 'Prepara o trabalho',
        descricao:
            'Monta reservas e cadastra salas, grupos e equipamentos. Nada é gravado sem o seu clique.',
    },
];

const recursosDosCertificados = [
    {
        titulo: 'Modelos por sacramento',
        descricao: 'Batismo, Eucaristia, Crisma…',
    },
    { titulo: 'Planilha do Excel', descricao: 'Vários de uma vez' },
    { titulo: 'Equipe', descricao: 'Modelos compartilhados' },
];

const passos = [
    {
        numeral: 'I',
        titulo: 'Cadastre salas e equipamentos',
        descricao:
            'Horários de funcionamento, capacidade, projetores. Ou peça ao assistente: “cadastra a capela, das 7h às 21h”.',
    },
    {
        numeral: 'II',
        titulo: 'Mande o link às pastorais',
        descricao:
            'No grupo de coordenadores, no mural, no boletim. Cada pedido cai na sua lista de solicitações.',
    },
    {
        numeral: 'III',
        titulo: 'Aprove com tudo à vista',
        descricao:
            'Conflitos no próprio cartão, contato pelo WhatsApp ao lado do Aprovar. A semana se monta sozinha.',
    },
];

const ancoraClasse =
    'inline-flex min-h-11 items-center rounded-full px-3.5 font-medium text-foreground/80 hover:text-foreground';

export default function Welcome() {
    const { auth } = usePage().props;
    const comecar = auth.user ? inicio() : register();

    return (
        <>
            <Head title="Salas, horários e certificados da paróquia">
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Instrument+Sans:wght@400;500;600&family=Pinyon+Script&display=swap"
                />
            </Head>
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-4 py-4 md:px-8">
                        <Link
                            href={home()}
                            className="flex items-center gap-3 text-foreground"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-medalha p-1">
                                <img
                                    src="/images/logo.png"
                                    alt=""
                                    className="size-full object-contain"
                                />
                            </span>
                            <span className="font-serif text-[26px] font-bold tracking-wide">
                                PequenaVia
                            </span>
                        </Link>

                        <nav
                            aria-label="Principal"
                            className="flex flex-wrap items-center gap-1"
                        >
                            <a href="#agenda" className={ancoraClasse}>
                                Salas e horários
                            </a>
                            <a href="#assistente" className={ancoraClasse}>
                                Assistente
                            </a>
                            <a href="#certificados" className={ancoraClasse}>
                                Certificados
                            </a>
                            {auth.user ? (
                                <Button
                                    asChild
                                    className="ml-1 h-11 rounded-full px-5 font-semibold"
                                >
                                    <Link href={inicio()}>Abrir o app</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="ml-2 h-11 rounded-full border-input bg-transparent px-5 font-semibold"
                                    >
                                        <Link href={login()}>Entrar</Link>
                                    </Button>
                                    <Button
                                        asChild
                                        className="h-11 rounded-full px-5 font-semibold"
                                    >
                                        <Link href={comecar}>
                                            Começar agora
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section
                        id="topo"
                        className="mx-auto grid max-w-[1200px] grid-cols-1 items-center gap-14 px-4 pt-14 pb-20 md:px-8 lg:grid-cols-2 lg:pt-20 lg:pb-24"
                    >
                        <div className="flex flex-col gap-7">
                            <p className="text-[13px] font-semibold tracking-[0.16em] text-dourado uppercase">
                                A secretaria paroquial, num só lugar
                            </p>
                            <h1 className="font-serif text-5xl leading-[0.98] font-semibold text-balance md:text-7xl">
                                Cada sala no seu horário. Cada pedido com
                                resposta.
                            </h1>
                            <p className="max-w-[530px] text-lg text-pretty text-foreground/80 md:text-[19px]">
                                Organize salas, capela e equipamentos sem
                                planilha e sem choque de horário. As pastorais
                                pedem pelo link, você aprova num clique, e um
                                assistente responde quem está onde.
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    asChild
                                    className="h-12 rounded-full px-7 text-base font-semibold"
                                >
                                    <Link href={comecar}>
                                        Organizar minha agenda
                                    </Link>
                                </Button>
                                <a
                                    href="#assistente"
                                    className="inline-flex min-h-11 items-center px-4 font-semibold text-foreground"
                                >
                                    Conhecer o assistente →
                                </a>
                            </div>
                            <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
                                {[
                                    'Conflitos avisados antes de reservar',
                                    'Projetores controlados entre salas',
                                    'Certificados continuam aqui',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-center gap-2"
                                    >
                                        <Check
                                            className="size-4 shrink-0 text-sucesso"
                                            strokeWidth={2.2}
                                            aria-hidden="true"
                                        />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="relative flex flex-col gap-5 lg:block lg:h-[560px]">
                            <ExemploDoDia />
                            <ExemploDoAssistente />
                        </div>
                    </section>

                    <section
                        id="agenda"
                        className="border-y border-border bg-secondary"
                    >
                        <div className="mx-auto flex max-w-[1200px] flex-col gap-14 px-4 py-20 md:px-8 md:py-24">
                            <div className="flex flex-wrap items-end justify-between gap-6">
                                <div className="flex max-w-[620px] flex-col gap-3.5">
                                    <p className="text-[13px] font-semibold tracking-[0.16em] text-dourado uppercase">
                                        Salas e horários
                                    </p>
                                    <h2 className="font-serif text-4xl leading-[1.04] font-semibold text-balance md:text-5xl">
                                        Ninguém chega e encontra a sala ocupada.
                                    </h2>
                                </div>
                                <p className="max-w-[400px] text-foreground/80">
                                    Só a secretaria reserva. As pastorais pedem
                                    pelo link, e cada pedido chega já dizendo se
                                    bate com outra reserva.
                                </p>
                            </div>

                            <div className="grid gap-14 lg:grid-cols-2 lg:items-start">
                                <ul className="flex flex-col border-t border-border">
                                    {recursosDaAgenda.map(
                                        ({
                                            icone: Icone,
                                            titulo,
                                            descricao,
                                        }) => (
                                            <li
                                                key={titulo}
                                                className="flex gap-4.5 border-b border-border py-6"
                                            >
                                                <Icone
                                                    className="size-7 shrink-0 text-primary"
                                                    strokeWidth={1.6}
                                                    aria-hidden="true"
                                                />
                                                <div>
                                                    <h3 className="mb-1.5 text-lg font-semibold">
                                                        {titulo}
                                                    </h3>
                                                    <p className="text-foreground/80">
                                                        {descricao}
                                                    </p>
                                                </div>
                                            </li>
                                        ),
                                    )}
                                </ul>

                                <ExemploDePedido />
                            </div>
                        </div>
                    </section>

                    <section
                        id="assistente"
                        className="bg-foreground text-background"
                    >
                        <div className="mx-auto grid max-w-[1200px] items-center gap-16 px-4 py-24 md:px-8 lg:grid-cols-2">
                            <div className="flex flex-col gap-6">
                                <p className="text-[13px] font-semibold tracking-[0.16em] text-dourado-claro uppercase">
                                    Assistente com IA
                                </p>
                                <h2 className="font-serif text-4xl leading-[1.02] font-semibold text-balance md:text-[56px]">
                                    Pergunte como perguntaria a uma colega de
                                    secretaria.
                                </h2>
                                <p className="max-w-[500px] text-lg text-background/75">
                                    Ele lê a sua agenda, os pedidos e o
                                    histórico de certificados para responder em
                                    segundos. E quando sugere uma mudança,
                                    espera o seu “confirmar”.
                                </p>
                                <ul className="flex flex-col gap-4">
                                    {recursosDoAssistente.map(
                                        ({
                                            icone: Icone,
                                            titulo,
                                            descricao,
                                        }) => (
                                            <li
                                                key={titulo}
                                                className="flex items-start gap-3.5"
                                            >
                                                <span className="flex size-[34px] shrink-0 items-center justify-center rounded-[10px] bg-background/10 text-dourado-claro">
                                                    <Icone
                                                        className="size-[18px]"
                                                        strokeWidth={1.8}
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <div>
                                                    <b className="block font-semibold">
                                                        {titulo}
                                                    </b>
                                                    <span className="text-background/75">
                                                        {descricao}
                                                    </span>
                                                </div>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>

                            <PainelDoAssistente />
                        </div>
                    </section>

                    <section
                        id="certificados"
                        className="mx-auto grid max-w-[1200px] items-center gap-16 px-4 py-24 md:px-8 lg:grid-cols-2"
                    >
                        <ExemploDeCertificado />

                        <div className="flex flex-col gap-6">
                            <p className="text-[13px] font-semibold tracking-[0.16em] text-dourado uppercase">
                                Certificados
                            </p>
                            <h2 className="font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                                E os certificados continuam a um clique.
                            </h2>
                            <p className="text-[17px] text-foreground/80">
                                Envie a arte uma vez, posicione nome e data, e
                                gere um ou cem certificados a partir de uma
                                planilha. Cada emissão fica no histórico.
                            </p>
                            <ul className="grid gap-3 sm:grid-cols-3">
                                {recursosDosCertificados.map(
                                    ({ titulo, descricao }) => (
                                        <li
                                            key={titulo}
                                            className="rounded-xl border border-border bg-card px-4 py-3.5 text-[15px]"
                                        >
                                            <b className="block font-semibold">
                                                {titulo}
                                            </b>
                                            <span className="text-muted-foreground">
                                                {descricao}
                                            </span>
                                        </li>
                                    ),
                                )}
                            </ul>
                        </div>
                    </section>

                    <section className="border-t border-border bg-secondary">
                        <div className="mx-auto flex max-w-[1200px] flex-col gap-11 px-4 py-20 md:px-8 md:py-24">
                            <h2 className="max-w-[640px] font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                                Em uma tarde, a agenda da paróquia está de pé.
                            </h2>
                            <ol className="grid gap-6 md:grid-cols-3">
                                {passos.map(
                                    ({ numeral, titulo, descricao }) => (
                                        <li
                                            key={numeral}
                                            className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-7"
                                        >
                                            <span className="font-serif text-[44px] leading-none font-semibold text-primary">
                                                {numeral}
                                            </span>
                                            <h3 className="text-xl font-semibold">
                                                {titulo}
                                            </h3>
                                            <p className="text-foreground/80">
                                                {descricao}
                                            </p>
                                        </li>
                                    ),
                                )}
                            </ol>
                        </div>
                    </section>

                    <section className="bg-primary text-primary-foreground">
                        <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-8 px-4 py-20 md:px-8">
                            <div className="flex max-w-[640px] flex-col gap-3">
                                <h2 className="font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                                    Comece pela sala mais disputada da paróquia.
                                </h2>
                                <p className="text-lg text-primary-foreground/85">
                                    Salas, capela, salão, projetores e
                                    certificados: tudo com a mesma conta.
                                </p>
                            </div>
                            <Button
                                asChild
                                variant="secondary"
                                className="h-12 rounded-full bg-card px-7 text-base font-semibold text-primary hover:bg-card/90"
                            >
                                <Link href={comecar}>Criar conta gratuita</Link>
                            </Button>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground md:px-8">
                    <span className="flex items-center gap-2.5">
                        <img
                            src="/images/logo.png"
                            alt=""
                            className="size-6 object-contain"
                        />
                        PequenaVia · salas, horários e certificados
                    </span>
                    <span>certificados.pequenavia.app.br</span>
                </footer>
            </div>
        </>
    );
}
