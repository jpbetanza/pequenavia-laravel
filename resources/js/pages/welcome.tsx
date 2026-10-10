import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FileSpreadsheet,
    History,
    Image,
    Lock,
    Type,
    Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { home, login, register } from '@/routes';
import { index as meusCertificados } from '@/routes/meus-certificados';

const passos = [
    {
        numeral: 'I',
        titulo: 'Envie a arte do certificado',
        descricao:
            'PNG, JPG ou WEBP de até 50 MB, com os espaços em branco onde entram os dados.',
    },
    {
        numeral: 'II',
        titulo: 'Posicione os campos',
        descricao:
            'Arraste nome, data e paróquia sobre a imagem e escolha fonte, tamanho e cor. Tudo é salvo sozinho.',
    },
    {
        numeral: 'III',
        titulo: 'Gere um ou vários',
        descricao:
            'Digite os dados ou importe uma planilha do Excel e baixe cada certificado em alta resolução.',
    },
];

const recursos = [
    {
        icone: Type,
        titulo: 'Fontes clássicas incluídas',
        descricao:
            'Caligráficas, serifadas e sem serifa, escolhidas para documentos solenes.',
    },
    {
        icone: FileSpreadsheet,
        titulo: 'Importação de planilha',
        descricao:
            'Baixe a planilha do modelo, preencha no Excel e traga tudo de volta de uma vez.',
    },
    {
        icone: Users,
        titulo: 'Equipe compartilhada',
        descricao:
            'Compartilhe modelos por e-mail e decida quem pode editar e quem só gera.',
    },
    {
        icone: History,
        titulo: 'Histórico de emissões',
        descricao:
            'Cada certificado gerado fica registrado, com os dados na ordem do modelo.',
    },
    {
        icone: Image,
        titulo: 'Imagens em alta resolução',
        descricao:
            'Artes de até 50 MB, geradas no tamanho original, prontas para a gráfica.',
    },
    {
        icone: Lock,
        titulo: 'Acesso simples e seguro',
        descricao:
            'Entre com o Google ou com e-mail e senha; chaves de acesso e 2FA disponíveis.',
    },
];

const ancoraClasse =
    'inline-flex min-h-11 items-center rounded-full px-3.5 font-medium text-foreground/80 hover:text-foreground';

export default function Welcome() {
    const { auth } = usePage().props;
    const comecar = auth.user ? meusCertificados() : register();

    return (
        <>
            <Head title="Certificados sagrados, geridos com beleza">
                <link
                    rel="stylesheet"
                    href="https://fonts.googleapis.com/css2?family=Pinyon+Script&display=swap"
                />
            </Head>
            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-6 py-4 md:px-8">
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
                            className="flex flex-wrap items-center gap-2"
                        >
                            <a href="#como-funciona" className={ancoraClasse}>
                                Como funciona
                            </a>
                            <a href="#recursos" className={ancoraClasse}>
                                Recursos
                            </a>
                            {auth.user ? (
                                <Button
                                    asChild
                                    className="h-11 rounded-full px-5 font-semibold"
                                >
                                    <Link href={meusCertificados()}>
                                        Meus certificados
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="h-11 rounded-full border-input bg-transparent px-5 font-semibold"
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
                        className="mx-auto grid max-w-[1200px] grid-cols-1 items-center gap-16 px-6 pt-16 pb-24 md:px-8 lg:grid-cols-2 lg:pt-22"
                    >
                        <div className="flex flex-col gap-7">
                            <p className="text-[13px] font-semibold tracking-[0.16em] text-dourado uppercase">
                                Certificados sacramentais para paróquias
                            </p>
                            <h1 className="font-serif text-5xl leading-[0.98] font-semibold text-balance md:text-7xl">
                                Cada sacramento, registrado com o cuidado que
                                merece.
                            </h1>
                            <p className="max-w-[520px] text-lg text-pretty text-foreground/80 md:text-xl">
                                Envie a arte do seu certificado, diga onde vai
                                cada nome e data, e gere um ou cem certificados
                                prontos para imprimir — sem abrir um editor de
                                imagens.
                            </p>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    asChild
                                    className="h-12 rounded-full px-7 text-base font-semibold"
                                >
                                    <Link href={comecar}>
                                        Criar meu primeiro modelo
                                    </Link>
                                </Button>
                                <a
                                    href="#como-funciona"
                                    className="inline-flex min-h-11 items-center px-4 font-semibold text-foreground"
                                >
                                    Ver como funciona{' '}
                                    <ArrowRight className="ml-1 size-4" />
                                </a>
                            </div>
                            {!auth.user && (
                                <p className="text-sm text-muted-foreground">
                                    Entre com sua conta Google ou com e-mail e
                                    senha.
                                </p>
                            )}
                        </div>

                        <div className="relative mx-auto h-[500px] w-full max-w-[460px] sm:h-[520px] lg:mx-0 lg:max-w-none">
                            <div
                                aria-hidden="true"
                                className="absolute top-9 left-[12%] h-[430px] w-[72%] -rotate-6 rounded-md border border-[#DCCFBB] bg-[#EDE4D3]"
                            />
                            <div
                                aria-hidden="true"
                                className="absolute top-[18px] left-[16%] h-[430px] w-[72%] rotate-3 rounded-md border border-[#DCCFBB] bg-[#F1E9DA]"
                            />
                            <figure className="absolute top-0 left-[10%] m-0 h-[470px] w-[78%] rounded-md bg-[#FCF8EF] p-[18px] shadow-[0_30px_60px_-30px_rgba(60,30,20,0.45),0_2px_6px_rgba(60,30,20,0.08)]">
                                <div className="h-full border-2 border-[#6E1F24] p-1.5">
                                    <div className="flex h-full flex-col items-center justify-center gap-2.5 border border-[#B8955A] px-4 py-5 text-center sm:px-4">
                                        <img
                                            src="/images/logo.png"
                                            alt=""
                                            className="size-12 object-contain opacity-90"
                                        />
                                        <p className="font-serif text-lg leading-snug font-bold tracking-[0.04em] text-[#6E1F24] uppercase sm:text-xl sm:whitespace-nowrap">
                                            Certificado de Batismo
                                        </p>
                                        <p className="font-serif text-base text-[#4A3D38] italic">
                                            Certificamos que
                                        </p>
                                        <p
                                            className="text-3xl leading-tight text-[#221A17] sm:text-4xl"
                                            style={{
                                                fontFamily:
                                                    "'Pinyon Script', cursive",
                                            }}
                                        >
                                            Maria Clara Souza
                                        </p>
                                        <div className="h-px w-3/5 bg-[#B8955A]" />
                                        <p className="max-w-[340px] font-serif text-sm leading-snug text-[#4A3D38] sm:text-base">
                                            recebeu o Sacramento do Batismo em
                                            14 de setembro de 2026, na Paróquia
                                            [NOME DA PARÓQUIA].
                                        </p>
                                    </div>
                                </div>
                                <figcaption className="absolute right-[-14px] bottom-9 flex items-center gap-2 rounded-full bg-[#221A17] px-3.5 py-2 text-[13px] font-semibold text-[#FBF8F2]">
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg>
                                    Gerado a partir da planilha
                                </figcaption>
                            </figure>
                        </div>
                    </section>

                    <section
                        id="como-funciona"
                        className="border-y border-border bg-secondary"
                    >
                        <div className="mx-auto flex max-w-[1200px] flex-col gap-12 px-6 py-20 md:px-8">
                            <div className="flex flex-wrap items-end justify-between gap-6">
                                <h2 className="max-w-xl font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                                    Três passos, do modelo ao certificado
                                    impresso.
                                </h2>
                                <p className="max-w-sm text-foreground/80">
                                    Você faz a arte uma vez. Depois disso,
                                    preencher certificados vira uma tarefa de
                                    secretaria, não de design.
                                </p>
                            </div>
                            <ol className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                {passos.map(
                                    ({ numeral, titulo, descricao }) => (
                                        <li
                                            key={numeral}
                                            className="flex flex-col gap-3.5 rounded-2xl border border-border bg-card p-8"
                                        >
                                            <span className="font-serif text-5xl leading-none font-semibold text-primary">
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

                    <section
                        id="recursos"
                        className="mx-auto flex max-w-[1200px] flex-col gap-12 px-6 py-24 md:px-8"
                    >
                        <h2 className="max-w-2xl font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                            Pensado para a rotina da secretaria paroquial.
                        </h2>
                        <ul className="grid grid-cols-1 border-t border-border md:grid-cols-2 lg:grid-cols-3">
                            {recursos.map(
                                ({ icone: Icone, titulo, descricao }) => (
                                    <li
                                        key={titulo}
                                        className="flex gap-4.5 border-b border-border py-7 pr-7"
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
                    </section>

                    <section className="bg-primary text-primary-foreground">
                        <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-8 px-6 py-20 md:px-8">
                            <div className="flex max-w-[620px] flex-col gap-3">
                                <h2 className="font-serif text-4xl leading-[1.05] font-semibold text-balance md:text-5xl">
                                    Comece pelo certificado que você mais emite.
                                </h2>
                                <p className="text-lg text-primary-foreground/85">
                                    Batismo, Primeira Eucaristia, Crisma ou
                                    Matrimônio: um modelo por sacramento.
                                </p>
                            </div>
                            <Button
                                asChild
                                variant="secondary"
                                className="h-12 rounded-full bg-card px-7 text-base font-semibold text-primary hover:bg-card/90"
                            >
                                <Link href={comecar}>
                                    {auth.user
                                        ? 'Abrir meus certificados'
                                        : 'Criar conta gratuita'}
                                </Link>
                            </Button>
                        </div>
                    </section>
                </main>

                <footer className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground md:px-8">
                    <span className="flex items-center gap-2.5">
                        <img
                            src="/images/logo.png"
                            alt=""
                            className="size-6 object-contain"
                        />
                        PequenaVia
                    </span>
                    <span>© {new Date().getFullYear()} PequenaVia</span>
                </footer>
            </div>
        </>
    );
}
