import { usePage } from '@inertiajs/react';
import { Sparkles, Upload } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useAssistente } from '@/hooks/use-assistente';
import {
    PEDIDO_DO_ARQUIVO,
    pedidoDaDescricao,
} from '@/lib/primeiros-passos/roteiro';
import { SoltarArquivo } from './soltar-arquivo';

type Cadastro = 'espacos' | 'equipamentos' | 'grupos';

const TEXTOS: Record<
    Cadastro,
    {
        titulo: string;
        explicacao: string;
        rotulo: string;
        exemplo: string;
        arquivo: string;
    }
> = {
    espacos: {
        titulo: 'Nenhum espaço ainda. Conte ao assistente como são as salas.',
        explicacao:
            'Escreva do jeito que você falaria. Ele entende nome, quantas pessoas cabem, os recursos e os horários, e prepara o cadastro para você confirmar.',
        rotulo: 'Descreva os espaços',
        exemplo:
            'Ex.: salão paroquial para 120 pessoas, com som e projetor, de segunda a sábado das 8h às 22h',
        arquivo: 'Solte a planilha das salas aqui',
    },
    equipamentos: {
        titulo: 'Nenhum equipamento ainda. Conte ao assistente o que a paróquia empresta.',
        explicacao:
            'Escreva do jeito que você falaria: o nome, quantas unidades e onde fica guardado. Ele prepara o cadastro para você confirmar.',
        rotulo: 'Descreva os equipamentos',
        exemplo:
            'Ex.: 2 projetores no armário da secretaria, 1 caixa de som e 40 cadeiras extras no depósito',
        arquivo: 'Solte a lista de equipamentos aqui',
    },
    grupos: {
        titulo: 'Nenhum grupo ainda. Conte ao assistente quem usa os espaços.',
        explicacao:
            'Escreva os grupos e pastorais, com quem coordena e o contato se souber. Ele prepara o cadastro para você confirmar.',
        rotulo: 'Descreva os grupos',
        exemplo:
            'Ex.: Catequese (Ana, 11 99999-0000), Coral, Pastoral da Juventude e Terço dos homens',
        arquivo: 'Solte a lista de grupos aqui',
    },
};

type Props = {
    cadastro: Cadastro;
};

/**
 * Convite das telas vazias de cadastro: descrever em texto (vai para o assistente) ou soltar uma planilha.
 * Só aparece com o assistente ligado; o cadastro manual da tela continua ao lado.
 */
export function ConviteDaTelaVazia({ cadastro }: Props) {
    const { assistente } = usePage().props;
    const { perguntar } = useAssistente();
    const [descricao, setDescricao] = useState('');
    const textos = TEXTOS[cadastro];

    if (!assistente?.disponivel) {
        return null;
    }

    function enviar(evento: FormEvent<HTMLFormElement>) {
        evento.preventDefault();

        if (descricao.trim() === '') {
            return;
        }

        perguntar(pedidoDaDescricao(cadastro, descricao));
        setDescricao('');
    }

    return (
        <section
            aria-labelledby={`convite-${cadastro}`}
            className="grid grid-cols-1 gap-7 rounded-[20px] border bg-card p-6 sm:p-7 lg:grid-cols-2"
        >
            <div className="flex flex-col gap-4">
                <h2
                    id={`convite-${cadastro}`}
                    className="font-serif text-3xl leading-tight font-semibold"
                >
                    {textos.titulo}
                </h2>
                <p className="text-[15px] text-muted-foreground">
                    {textos.explicacao}
                </p>
                <form onSubmit={enviar} className="flex flex-col gap-2">
                    <label
                        htmlFor={`descricao-${cadastro}`}
                        className="font-semibold"
                    >
                        {textos.rotulo}
                    </label>
                    <div className="flex flex-col gap-2 rounded-2xl border bg-background p-1.5 focus-within:ring-2 focus-within:ring-ring/40 sm:flex-row sm:items-end">
                        <textarea
                            id={`descricao-${cadastro}`}
                            rows={3}
                            value={descricao}
                            onChange={(evento) =>
                                setDescricao(evento.target.value)
                            }
                            placeholder={textos.exemplo}
                            className="min-w-0 flex-1 resize-none bg-transparent px-2 py-2 text-[15px] outline-none placeholder:text-muted-foreground"
                        />
                        <Button
                            type="submit"
                            disabled={descricao.trim() === ''}
                            className="min-h-11 shrink-0 rounded-xl"
                        >
                            <Sparkles aria-hidden="true" />
                            Enviar ao assistente
                        </Button>
                    </div>
                    <span className="text-[13px] text-muted-foreground">
                        Abre o assistente com este texto. Nada é gravado antes
                        de você confirmar.
                    </span>
                </form>
            </div>

            <SoltarArquivo
                id={`arquivo-${cadastro}`}
                aoEscolher={(arquivos) =>
                    perguntar(PEDIDO_DO_ARQUIVO, arquivos)
                }
                className="flex min-h-44 flex-col items-center justify-center gap-2.5 rounded-2xl border-border bg-background p-6 text-center data-[arrastando]:border-primary data-[arrastando]:bg-primary/5"
            >
                <span className="flex size-12 items-center justify-center rounded-2xl bg-muted text-primary">
                    <Upload aria-hidden="true" />
                </span>
                <b className="text-[15px]">{textos.arquivo}</b>
                <span className="text-muted-foreground">
                    Excel, CSV, PDF ou a foto de uma lista. O assistente lê e
                    propõe tudo de uma vez.
                </span>
            </SoltarArquivo>
        </section>
    );
}
