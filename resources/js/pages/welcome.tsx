import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    History,
    ImageDown,
    LayoutTemplate,
    LogIn,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { home, login, register } from '@/routes';
import { index as meusCertificados } from '@/routes/meus-certificados';

const recursos = [
    {
        icone: LayoutTemplate,
        titulo: 'Modelos prontos',
        descricao:
            'Escolha entre modelos de certificado já formatados para diferentes sacramentos e ocasiões paroquiais.',
    },
    {
        icone: ImageDown,
        titulo: 'Geração instantânea',
        descricao:
            'Preencha os campos do modelo e baixe o certificado como imagem PNG pronta para impressão.',
    },
    {
        icone: History,
        titulo: 'Histórico de gerações',
        descricao:
            'Consulte as últimas gerações realizadas com todos os campos preenchidos, a qualquer momento.',
    },
    {
        icone: LogIn,
        titulo: 'Acesso com conta',
        descricao:
            'Entre com e-mail e senha ou com o Google. Seus dados ficam protegidos e vinculados à sua conta.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const comecar = auth.user ? meusCertificados() : register();

    return (
        <>
            <Head title="Certificados sagrados, geridos com beleza" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                    <Link href={home()} className="flex items-center gap-3">
                        <img
                            src="/images/logo.png"
                            alt="PequenaVia"
                            className="size-10 rounded-md object-cover"
                        />
                        <span className="font-serif text-xl font-bold">
                            PequenaVia
                        </span>
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={meusCertificados()}>
                                    Meus certificados
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={login()}>Entrar</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Criar conta</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main>
                    <section className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-12 px-6 pt-12 pb-20 text-center lg:flex-row lg:text-left">
                        <div className="flex max-w-2xl flex-col items-center gap-6 lg:items-start">
                            <h1 className="font-serif text-4xl font-bold text-balance lg:text-6xl">
                                Certificados sagrados, geridos com beleza
                            </h1>
                            <p className="text-lg leading-relaxed text-pretty text-muted-foreground md:text-xl">
                                Simplifique a geração, a gestão e a entrega de
                                certificados sacramentais para a sua paróquia,
                                com elegância e precisão.
                            </p>
                            <div className="mt-2 flex flex-col items-center gap-4 sm:flex-row">
                                <Button
                                    asChild
                                    size="lg"
                                    className="font-semibold"
                                >
                                    <Link href={comecar}>
                                        Começar agora
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="font-semibold"
                                >
                                    <a href="#recursos">Saiba mais</a>
                                </Button>
                            </div>
                        </div>
                        <img
                            src="/images/logo.png"
                            alt="PequenaVia"
                            className="h-auto w-full max-w-[280px] rounded-t-full object-contain sm:max-w-[320px] lg:max-w-[420px]"
                        />
                    </section>

                    <section id="recursos" className="border-t border-border">
                        <div className="mx-auto max-w-6xl px-6 py-20">
                            <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                                Recursos
                            </p>
                            <h2 className="mt-2 font-serif text-3xl font-bold text-balance md:text-4xl">
                                Funcionalidades principais
                            </h2>
                            <p className="mt-3 max-w-xl text-base leading-relaxed text-muted-foreground">
                                Ferramentas essenciais para a gestão paroquial,
                                construídas sobre a fundação sólida da
                                PequenaVia.
                            </p>

                            <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                {recursos.map(
                                    ({ icone: Icone, titulo, descricao }) => (
                                        <Card
                                            key={titulo}
                                            className="transition-shadow hover:shadow-md"
                                        >
                                            <CardContent className="flex flex-col gap-3">
                                                <Icone className="size-6 text-primary" />
                                                <h3 className="font-semibold">
                                                    {titulo}
                                                </h3>
                                                <p className="text-sm leading-relaxed text-muted-foreground">
                                                    {descricao}
                                                </p>
                                            </CardContent>
                                        </Card>
                                    ),
                                )}
                            </div>

                            <h3 className="mt-16 mb-6 text-lg font-semibold">
                                Prévia do certificado
                            </h3>
                            <div className="mx-auto flex max-w-2xl flex-col items-center rounded-xl border border-border bg-card p-10 text-center shadow-sm">
                                <img
                                    src="/images/logo.png"
                                    alt=""
                                    className="mb-6 size-20 object-contain opacity-80"
                                />
                                <p className="text-xs font-semibold tracking-[0.3em] text-primary uppercase">
                                    Paróquia de Nossa Senhora das Graças
                                </p>
                                <h4 className="mt-3 font-serif text-3xl font-bold">
                                    Certificado de Batismo
                                </h4>
                                <div className="mt-1 h-px w-24 bg-primary/30" />
                                <p className="mt-6 max-w-md text-sm leading-relaxed text-muted-foreground">
                                    Certificamos que{' '}
                                    <span className="font-semibold text-foreground">
                                        Maria Clara Santos
                                    </span>{' '}
                                    foi batizada no{' '}
                                    <span className="font-semibold text-foreground">
                                        décimo quarto dia de fevereiro
                                    </span>
                                    , no ano de Nosso Senhor{' '}
                                    <span className="font-semibold text-foreground">
                                        dois mil e vinte e seis
                                    </span>
                                    , na Paróquia de Nossa Senhora das Graças.
                                </p>
                                <div className="mt-8 grid grid-cols-2 gap-12 text-center text-xs text-muted-foreground">
                                    <div>
                                        <div className="mb-1 h-px bg-border" />
                                        <p>Pe. Antônio Pereira</p>
                                        <p>Celebrante</p>
                                    </div>
                                    <div>
                                        <div className="mb-1 h-px bg-border" />
                                        <p>Secretária Paroquial</p>
                                        <p>Registro Oficial</p>
                                    </div>
                                </div>
                                <p className="mt-8 text-xs text-muted-foreground">
                                    ID do certificado: PV-2026-001842
                                </p>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-border py-8 text-center text-sm text-muted-foreground">
                    © {new Date().getFullYear()} PequenaVia
                </footer>
            </div>
        </>
    );
}
