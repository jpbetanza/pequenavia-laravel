import { ErroHttp } from '@/lib/http';

/** Chave da conversa aberta por último, separada por usuário para não vazar entre contas no mesmo navegador. */
export function chaveDaConversaSalva(userId: string): string {
    return `assistente-conversa-${userId}`;
}

/** Uma conversa que não existe mais (apagada ou de outro usuário) responde 404: a referência salva deve ser descartada. */
export function deveDescartarConversa(erro: unknown): boolean {
    return erro instanceof ErroHttp && erro.status === 404;
}
