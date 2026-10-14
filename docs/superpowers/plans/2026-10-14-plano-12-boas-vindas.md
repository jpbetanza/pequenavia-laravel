# Plano 12 — Boas-vindas e primeiros passos com o assistente

Design: canvas "PequenaVia — telas reimaginadas", página "Boas-vindas (novo usuário)" (cópias em
`docs/superpowers/design/boas-vindas/`).

## Objetivo

Quem entra pela primeira vez vê um roteiro curto que deixa a agenda pronta para uso, e o caminho mais fácil para
cada passo é o assistente: descrever do seu jeito ou soltar uma planilha, e só confirmar.

## O roteiro

| Chave          | Passo                  | Feito quando                                              |
|----------------|------------------------|-----------------------------------------------------------|
| `espacos`      | Espaços                | existe ao menos um espaço                                 |
| `equipamentos` | Equipamentos           | existe ao menos um equipamento                            |
| `grupos`       | Grupos e pastorais     | existe ao menos um grupo                                  |
| `formulario`   | Formulário de pedidos  | a secretaria marcou "já enviei" ou chegou uma solicitação |
| `avisos`       | Avisos no celular      | existe ao menos um aparelho inscrito                      |

- O progresso vem dos dados, não de flags: quem já tinha cadastros vê os passos feitos.
- `avisos` só entra quando o servidor tem as chaves VAPID (sem elas, não dá para concluir).
- Qualquer passo pode ser pulado; pulado conta como resolvido.

## Estado guardado (`users.primeiros_passos`, jsonb)

`iniciado_em`, `pulados` (lista de chaves), `link_enviado_em`, `adiado_ate`, `dispensado_em`.

- `iniciado_em`: gravado na primeira vez que o roteiro aparece com passos pendentes. O "Tudo pronto" só aparece para
  quem começou o roteiro (quem já chegou com tudo feito não vê nada).
- "Agora não, me lembre depois": `adiado_ate` = amanhã. Some do Início até lá.
- "Prefiro configurar por conta própria" e o X do "Tudo pronto": `dispensado_em`. Não volta.

## Fases

- `inicio`: nada resolvido. Banner escuro com o roteiro no lugar da saudação.
- `andamento`: algum passo resolvido. Saudação normal e uma faixa clara com o que falta.
- `concluido`: tudo resolvido. Banner "Tudo pronto" até ser fechado.

## Backend

1. Migration `primeiros_passos` em `users`; cast `array`.
2. `App\Services\PrimeirosPassos`: monta o estado (fase, passos, atual, contagens, link do formulário) e aplica as
   ações. Retorna null quando não deve aparecer (dispensado, ou concluído sem ter começado).
3. `PrimeirosPassosController@update` (`POST /primeiros-passos`): `pular` (com `passo`), `adiar`, `dispensar`,
   `link_enviado`. Volta para a página anterior.
4. Prop compartilhada `primeiros_passos` (Inertia), usada pelo Início, pelo painel do assistente e pelas telas vazias.
5. Instruções do assistente: seção "Primeiros passos" com a situação de cada passo e como conduzir (um passo por vez,
   na ordem, propor e só seguir depois de confirmado; no formulário, passar o link e oferecer a mensagem de WhatsApp;
   nos avisos, explicar o caminho).

## Frontend

1. `components/primeiros-passos/`: banner de boas-vindas (fases `inicio` e `concluido`), faixa de andamento, faixa do
   passo no painel do assistente e convite das telas vazias.
2. "Configurar com o assistente" e "Pedir ao assistente" usam `perguntar(texto)`. Soltar ou escolher um arquivo no
   banner usa `perguntar(texto, arquivos)`: o painel abre, lê os arquivos e envia com o texto.
3. Telas vazias de Espaços, Equipamentos e Grupos: descrever em texto (vai ao assistente), soltar planilha, ou o
   cadastro manual de sempre.
4. Celular: banner empilhado, "Fotografar uma lista" (câmera) e o passo de avisos com o atalho para instalar.
5. Respostas do assistente ganham "Copiar" (para a mensagem de WhatsApp e afins).

## Fora deste plano

- QR code para abrir o app no celular (o passo de avisos mostra o endereço e o caminho).

## Testes

- Pest: fases, passos feitos pelos dados, pulos, adiar, dispensar, link enviado, avisos sem VAPID, prop compartilhada,
  rota, instruções do assistente.
- Vitest: textos e pedidos dos passos.
