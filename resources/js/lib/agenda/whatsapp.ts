/** Mensagem fixa do "Entrar em contato" (a mesma da especificação). */
export const MENSAGEM_DO_WHATSAPP =
    'Olá! Aqui é da secretaria da paróquia, sobre o seu pedido de reserva.';

/**
 * Link wa.me para o telefone da solicitação. Só dígitos; acrescenta 55 quando o número
 * não traz o código do país (10 ou 11 dígitos). Devolve null quando não dá para montar.
 */
export function linkDoWhatsApp(telefone: string): string | null {
    const digitos = telefone.replace(/\D/g, '');

    if (digitos.length < 10 || digitos.length > 13) {
        return null;
    }

    const numero = digitos.length <= 11 ? `55${digitos}` : digitos;

    return `https://wa.me/${numero}?text=${encodeURIComponent(MENSAGEM_DO_WHATSAPP)}`;
}
