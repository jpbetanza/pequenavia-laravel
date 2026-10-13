import { Head, router } from '@inertiajs/react';
import { Bell, BellOff, Send, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import NotificacoesController from '@/actions/App/Http/Controllers/Settings/NotificacoesController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import {
    corpoDaInscricao,
    impedimento,
    type Impedimento,
    inscricaoAtual,
    inscreverAparelho,
    PermissaoNegada,
    suportaNotificacoes,
} from '@/lib/pwa/notificacoes';
import { rodandoComoAplicativo } from '@/lib/pwa/instalacao';
import { edit } from '@/routes/notificacoes';

type Aparelho = {
    id: string;
    endpoint: string;
    dispositivo: string | null;
    ativada_em: string | null;
};

type Props = {
    configurado: boolean;
    chavePublica: string | null;
    aparelhos: Aparelho[];
};

type Ambiente = { impedimento: Impedimento; endpoint: string | null };

const MENSAGENS: Record<Exclude<Impedimento, null>, string> = {
    'instalar-no-iphone':
        'No iPhone, as notificações só funcionam com o PequenaVia instalado. No Safari, toque em Compartilhar e depois em Adicionar à Tela de Início; abra o app pela tela inicial e volte aqui.',
    'sem-suporte':
        'Este navegador não recebe notificações. No celular, use o Chrome (Android) ou o app instalado na tela de início (iPhone).',
    bloqueadas:
        'As notificações estão bloqueadas para este site. Libere nas configurações do navegador e recarregue a página.',
};

function dataCurta(iso: string | null): string {
    return iso === null
        ? ''
        : new Date(iso).toLocaleDateString('pt-BR', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
          });
}

export default function Notificacoes({
    configurado,
    chavePublica,
    aparelhos,
}: Props) {
    // Lido só no navegador, depois de montar: permissão e inscrição são do aparelho.
    const [ambiente, setAmbiente] = useState<Ambiente | null>(null);
    const [processando, setProcessando] = useState<string | null>(null);

    useEffect(() => {
        const suporta = suportaNotificacoes();

        void inscricaoAtual()
            .catch(() => null)
            .then((inscricao) =>
                setAmbiente({
                    impedimento: impedimento({
                        suporta,
                        iphone:
                            /iPhone|iPad|iPod/.test(navigator.userAgent) ||
                            (/Macintosh/.test(navigator.userAgent) &&
                                navigator.maxTouchPoints > 1),
                        instalado: rodandoComoAplicativo(),
                        permissao: suporta ? Notification.permission : null,
                    }),
                    endpoint: inscricao?.endpoint ?? null,
                }),
            );
    }, []);

    const ativoAqui =
        ambiente?.endpoint != null &&
        aparelhos.some((aparelho) => aparelho.endpoint === ambiente.endpoint);

    async function executar(operacao: string, acao: () => Promise<void>) {
        if (processando !== null) {
            return;
        }

        setProcessando(operacao);

        try {
            await acao();
        } catch (erro) {
            toast.error(
                erro instanceof PermissaoNegada
                    ? erro.message
                    : mensagemDoErro(erro),
            );
        } finally {
            setProcessando(null);
        }
    }

    function ativar() {
        void executar('ativar', async () => {
            const inscricao = await inscreverAparelho(chavePublica ?? '');

            await enviarJson(
                'POST',
                NotificacoesController.store.url(),
                corpoDaInscricao(inscricao.toJSON(), navigator.userAgent),
            );
            setAmbiente(
                (atual) => atual && { ...atual, endpoint: inscricao.endpoint },
            );
            router.reload({ only: ['aparelhos'] });
            toast.success('Notificações ativadas neste aparelho.');
        });
    }

    function desativar() {
        void executar('desativar', async () => {
            const inscricao = await inscricaoAtual();

            if (inscricao !== null) {
                await enviarJson(
                    'DELETE',
                    NotificacoesController.destroy.url(),
                    {
                        endpoint: inscricao.endpoint,
                    },
                );
                await inscricao.unsubscribe();
            }

            setAmbiente((atual) => atual && { ...atual, endpoint: null });
            router.reload({ only: ['aparelhos'] });
            toast.success('Notificações desativadas neste aparelho.');
        });
    }

    function remover(aparelho: Aparelho) {
        void executar(aparelho.id, async () => {
            await enviarJson('DELETE', NotificacoesController.destroy.url(), {
                endpoint: aparelho.endpoint,
            });
            router.reload({ only: ['aparelhos'] });
        });
    }

    function testar() {
        void executar('testar', async () => {
            const resposta = await enviarJson<{ entregues: number }>(
                'POST',
                NotificacoesController.teste.url(),
            );
            const entregues = resposta?.entregues ?? 0;

            toast.success(
                entregues === 1
                    ? 'Notificação de teste enviada para 1 aparelho.'
                    : `Notificação de teste enviada para ${entregues} aparelhos.`,
            );
        });
    }

    return (
        <>
            <Head title="Notificações" />

            <h1 className="sr-only">Notificações</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notificações no celular"
                    description="Receba um aviso no celular quando chegar um pedido pelo formulário público de reservas, mesmo com o PequenaVia fechado."
                />

                {!configurado ? (
                    <p className="rounded-xl border bg-muted/40 p-4 text-sm text-muted-foreground">
                        As notificações ainda não foram configuradas no
                        servidor: faltam as chaves VAPID_PUBLIC_KEY e
                        VAPID_PRIVATE_KEY (gere com{' '}
                        <code className="font-mono">
                            php artisan push:gerar-chaves
                        </code>
                        ).
                    </p>
                ) : ambiente === null ? (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        Verificando este aparelho…
                    </p>
                ) : ambiente.impedimento !== null ? (
                    <p className="rounded-xl border bg-muted/40 p-4 text-sm">
                        {MENSAGENS[ambiente.impedimento]}
                    </p>
                ) : (
                    <section
                        aria-label="Este aparelho"
                        className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                    >
                        <div className="flex items-center gap-3">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                {ativoAqui ? (
                                    <Bell
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                ) : (
                                    <BellOff
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                )}
                            </span>
                            <div className="flex flex-col">
                                <span className="font-semibold">
                                    Neste aparelho
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {ativoAqui
                                        ? 'Notificações ativadas.'
                                        : 'Notificações desativadas.'}
                                </span>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {ativoAqui ? (
                                <>
                                    <Button
                                        type="button"
                                        onClick={testar}
                                        disabled={processando !== null}
                                        className="min-h-11"
                                    >
                                        {processando === 'testar' ? (
                                            <Spinner />
                                        ) : (
                                            <Send aria-hidden="true" />
                                        )}
                                        Enviar notificação de teste
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={desativar}
                                        disabled={processando !== null}
                                        className="min-h-11"
                                    >
                                        {processando === 'desativar' && (
                                            <Spinner />
                                        )}
                                        Desativar
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="button"
                                    onClick={ativar}
                                    disabled={processando !== null}
                                    className="min-h-11"
                                >
                                    {processando === 'ativar' ? (
                                        <Spinner />
                                    ) : (
                                        <Bell aria-hidden="true" />
                                    )}
                                    Ativar notificações
                                </Button>
                            )}
                        </div>
                    </section>
                )}

                {aparelhos.length > 0 && (
                    <section aria-labelledby="aparelhos" className="space-y-3">
                        <h2 id="aparelhos" className="text-sm font-semibold">
                            Aparelhos que recebem
                        </h2>
                        <ul className="divide-y rounded-xl border">
                            {aparelhos.map((aparelho) => (
                                <li
                                    key={aparelho.id}
                                    className="flex items-center gap-3 py-2 pr-2 pl-4"
                                >
                                    <Smartphone
                                        aria-hidden="true"
                                        className="size-5 shrink-0 text-muted-foreground"
                                    />
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <span className="flex flex-wrap items-center gap-2 font-medium">
                                            {aparelho.dispositivo ?? 'Aparelho'}
                                            {aparelho.endpoint ===
                                                ambiente?.endpoint && (
                                                <Badge variant="secondary">
                                                    este aparelho
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            Ativado em{' '}
                                            {dataCurta(aparelho.ativada_em)}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => remover(aparelho)}
                                        disabled={processando !== null}
                                        className="min-h-11"
                                    >
                                        Remover
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

Notificacoes.layout = {
    breadcrumbs: [
        {
            title: 'Notificações',
            href: edit(),
        },
    ],
};
