# PequenaVia — Plano 5: Redesign (identidade "livro de registros" + propostas)

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Passos com checkbox (`- [ ]`).

**Objetivo:** aplicar ao app o redesign aprovado no canvas "PequenaVia — telas reimaginadas" e implementar as funcionalidades propostas nele.

**Referência visual (fonte da verdade do layout):** os artboards em `docs/superpowers/design/redesign/*.dc.html`. São HTML com estilos inline: leia o artboard da sua task e reproduza layout, hierarquia, espaçamentos, cores e textos com Tailwind + componentes shadcn existentes. Os dados de exemplo dos artboards (nomes de pessoas, datas, "Ana Secretaria", "Paróquia [NOME]") **não** entram no app: use os dados reais das props.

**Arquitetura:** Inertia 3 + React 19 (React Compiler) + Tailwind 4 + shadcn (Radix). Tokens de cor no `resources/css/app.css` (variáveis do shadcn), consumidos por classes (`bg-primary`, `text-muted-foreground`…). Nada de cor hex solta nos componentes, exceto dentro da miniatura/certificado ilustrativo da landing.

## Restrições globais

- Texto visível em pt-BR. Rótulos acessíveis em todo input e botão só com ícone (`aria-label`). Alvos de toque ≥ 44 px.
- Fontes: títulos em **Cormorant Garamond** (600/700, classe `font-serif`), interface em **Instrument Sans** (já é `font-sans`). Remover Playfair Display.
- Ícones: `lucide-react` (já instalado). Sem emoji.
- Única dependência nova autorizada: **`fflate`** (zip no navegador, Task 6). Nenhuma outra.
- Não quebrar o que existe: autosave do editor, retry de 429, abort ao desmontar, limites da importação (5 MB, 500 linhas, 500 caracteres), confirmação de substituição, guards de duplo clique, toasts de erro (`components/modelos/erro-de-acao.ts`, `@/lib/http`). Reorganize a UI em volta dessa lógica, não reescreva a lógica.
- Modo escuro continua funcionando (tokens `.dark`).
- Verificação de cada task: `npm run build`, `npx tsc --noEmit`, `npm run check`, `npx vp test run`, `php artisan test --compact` (todos verdes). PHP: `vendor/bin/pint --dirty --format agent`.
- Commits em pt-BR com trailer `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

## Tokens (Task 1 escreve; demais tasks usam)

`:root` (claro):

```css
--background: #f5f0e6;      /* papel */
--foreground: #221a17;      /* tinta */
--card: #fbf8f2;
--card-foreground: #221a17;
--popover: #fbf8f2;
--popover-foreground: #221a17;
--primary: #6e1f24;         /* bordô */
--primary-foreground: #fbf8f2;
--secondary: #efe8db;
--secondary-foreground: #221a17;
--muted: #efe8db;
--muted-foreground: #6b5d57;
--accent: #efe8db;
--accent-foreground: #221a17;
--destructive: #a12a2a;
--destructive-foreground: #fbf8f2;
--border: #e1d7c8;
--input: #cdbfac;
--ring: #6e1f24;
--sidebar: #efe8db;
--sidebar-foreground: #221a17;
--sidebar-primary: #6e1f24;
--sidebar-primary-foreground: #fbf8f2;
--sidebar-accent: #fbf8f2;
--sidebar-accent-foreground: #6e1f24;
--sidebar-border: #e1d7c8;
--sidebar-ring: #6e1f24;
--mesa: #e9e0cf;            /* fundo atrás de imagens/certificados */
--dourado: #9a7428;
--sucesso: #24513f;
--sucesso-fundo: #e2eee8;
--aviso: #8a4b12;
--aviso-fundo: #fff6ee;
```

`.dark`:

```css
--background: #17120f;
--foreground: #efe7da;
--card: #201915;
--card-foreground: #efe7da;
--popover: #201915;
--popover-foreground: #efe7da;
--primary: #d38277;
--primary-foreground: #1a110e;
--secondary: #2b221d;
--secondary-foreground: #efe7da;
--muted: #2b221d;
--muted-foreground: #b3a596;
--accent: #2b221d;
--accent-foreground: #efe7da;
--destructive: #e0675f;
--destructive-foreground: #1a110e;
--border: #3a2e27;
--input: #4a3c33;
--ring: #d38277;
--sidebar: #1b1512;
--sidebar-foreground: #efe7da;
--sidebar-primary: #d38277;
--sidebar-primary-foreground: #1a110e;
--sidebar-accent: #2b221d;
--sidebar-accent-foreground: #f0b9b0;
--sidebar-border: #3a2e27;
--sidebar-ring: #d38277;
--mesa: #120e0c;
--dourado: #c9a45a;
--sucesso: #9fd3bb;
--sucesso-fundo: #1d3229;
--aviso: #f0b27a;
--aviso-fundo: #3a2716;
```

No bloco `@theme`: `--font-serif: 'Cormorant Garamond', ui-serif, Georgia, serif;` e as cores novas `--color-mesa: var(--mesa); --color-dourado: var(--dourado); --color-sucesso: var(--sucesso); --color-sucesso-fundo: var(--sucesso-fundo); --color-aviso: var(--aviso); --color-aviso-fundo: var(--aviso-fundo);`. Manter `--radius` e `--chart-*`.

---

### Task 1: Tema, fontes e casca do app

**Referência:** `Modelos.dc.html` (barra lateral), `Historico.dc.html` (cabeçalho de página).

**Files:** `resources/css/app.css`, `vite.config.ts`, `resources/views/app.blade.php`, `resources/js/components/{app-sidebar,nav-main,nav-user,app-logo,cabecalho-da-pagina,app-sidebar-header}.tsx`, `resources/js/layouts/app/app-sidebar-layout.tsx` (só se preciso).

- [ ] Tokens da seção "Tokens" em `app.css` (substituir valores, manter nomes; acrescentar os novos e o `@theme`).
- [ ] `vite.config.ts`: trocar `bunny('Playfair Display', …)` por `bunny('Cormorant Garamond', { weights: [500, 600, 700] })`. Em `app.blade.php`, o fundo inline do `<html>` (`oklch(...)`) passa a `#f5f0e6` / dark `#17120f`.
- [ ] Barra lateral (shadcn `Sidebar` existente): fundo `bg-sidebar`; logo + "PequenaVia" em `font-serif` 23 px bold; itens "Modelos" (ícone `FileText` ou `Presentation`) e "Histórico" (`History`) com altura 44 px, item ativo com fundo `bg-sidebar-accent`, texto `text-sidebar-accent-foreground`, peso 600 e sombra sutil (`shadow-xs`); sem o rótulo de grupo "Plataforma". No rodapé: link "Configurações" (`Settings`, rota de perfil) acima do `NavUser` (avatar com iniciais em `bg-primary`).
- [ ] `CabecalhoDaPagina`: título `font-serif text-5xl font-semibold leading-none`, subtítulo `text-muted-foreground`; com `modelo`, um link "‹ Modelos" acima do título (não à esquerda); `acoes` alinhadas à direita na mesma linha do título, quebrando no celular.
- [ ] O cabeçalho do layout (`app-sidebar-header`, breadcrumbs) fica só com o botão de recolher a barra no desktop e some visualmente quando não há breadcrumb útil (mantenha o trigger mobile).
- [ ] Verificar (lista global) e commitar: `feat: identidade visual livro de registros (tokens, fontes e barra lateral)`.

### Task 2: Página inicial e telas de autenticação

**Referência:** `Main.dc.html`, `Entrar.dc.html`.

**Files:** `resources/js/pages/welcome.tsx`, `resources/js/layouts/auth/auth-simple-layout.tsx` (vira layout dividido), `resources/js/pages/auth/login.tsx`, `resources/js/components/google-button.tsx`.

- [ ] `welcome.tsx` reproduz `Main.dc.html`: cabeçalho (logo, "Como funciona", "Recursos", "Entrar", "Começar agora" — este último e "Entrar" apontam para `login`/`register` do Wayfinder; usuário autenticado vê "Meus certificados" no lugar), hero com o certificado ilustrativo (HTML/CSS como no artboard, usando `/images/logo.png`; a fonte script do nome pode ser a `Pinyon Script` carregada só nesta página via `<Head>` com link do Google Fonts, ou `font-serif italic` — escolha uma e registre), seção "Como funciona" (I, II, III), "Recursos" (6 itens, ícones lucide equivalentes), faixa bordô de chamada e rodapé. Âncoras `#como-funciona` e `#recursos`.
- [ ] `auth-simple-layout.tsx` vira o layout dividido de `Entrar.dc.html`: painel `bg-primary` à esquerda (logo em círculo claro, título serif por página, texto e lista de 3 benefícios) e formulário à direita (max-w 420 px). No celular o painel vira uma faixa curta no topo (só logo + título). O layout recebe `title`/`description` como hoje; o texto grande do painel é fixo ("Bem-vindo de volta à secretaria." no login; para as demais páginas use o `title` da página).
- [ ] `login.tsx`: ordem Google (botão claro, borda `border-input`, 48 px) → divisor "ou com e-mail" → e-mail, senha com "Esqueceu a senha?" ao lado do rótulo, "Lembrar de mim neste computador", botão "Entrar" 52 px → link "Entrar com chave de acesso". "Ainda não tem conta? Criar conta" abaixo do título. Mantenha `name`, `data-test`, `autoComplete` e a lógica.
- [ ] As demais páginas de auth (register, forgot, reset, verify, confirm, two-factor) herdam o layout sem mudar lógica; ajuste só espaçamento se quebrar.
- [ ] Verificar e commitar: `feat: nova página inicial e layout dividido de autenticação`.

### Task 3: Backend das propostas

**Files:** `app/Http/Controllers/{ModeloCertificadoController,HistoricoController,GeracaoCertificadoController}.php`, testes em `tests/Feature/{Modelos,Historico,Certificados}` (siga os arquivos de teste existentes de cada controller), `resources/js/types/certificados.ts`.

- [ ] **Resumo do modelo**: acrescentar `'atualizado_em' => $modelo->updated_at?->toIso8601String()` ao `resumo()`; tipo `ModeloResumo.atualizado_em: string | null`. Teste: o índice expõe `atualizado_em`.
- [ ] **Histórico com filtros**: `HistoricoController::index` aceita query `busca` (string, até 100) e `modelo` (uuid). `busca` filtra as gerações cujo `campos_valores::text` contém o termo sem diferenciar maiúsculas (`whereRaw('campos_valores::text ilike ?', ['%'.$termo.'%'])`, escapando `%`/`_` do termo); `modelo` filtra por `modelo_id`. Continua limitado a 50, mais recentes primeiro. Props novas: `filtros: { busca: string, modelo: string|null }`, `modelos: {id, title}[]` (modelos distintos que aparecem no histórico do usuário, por título) e, em cada geração, `gerado_por` (nome do usuário dono da geração). Testes: busca acha por valor, ignora caixa, não vaza gerações de outro usuário; filtro por modelo; termo com `%` não vira curinga.
- [ ] **Gerar de novo**: `GeracaoCertificadoController::create` aceita `?geracao={id}`. Se a geração existe, é do usuário logado e tem o mesmo `modelo_id`, passa a prop `valores_iniciais` (objeto campo → valor, só campos que existem no modelo); senão `valores_iniciais = null` (sem erro). Teste: prefill com geração própria; ignora geração de outro usuário; ignora geração de outro modelo.
- [ ] Atualizar tipos TS (`Geracao.gerado_por: string`; nova `FiltrosHistorico`).
- [ ] Verificar e commitar: `feat: filtros do histórico, gerar de novo e data de alteração do modelo`.

### Task 4: Tela de Modelos

**Referência:** `Modelos.dc.html`.

**Files:** `resources/js/pages/meus-certificados/index.tsx`, `resources/js/components/modelos/{cartao-de-modelo,secao-compartilhados,dialogo-opcoes-do-modelo}.tsx` (o diálogo de opções deixa de ser a porta de entrada).

- [ ] Cabeçalho: "Modelos" + subtítulo "A arte de cada certificado e onde entram os dados."; à direita busca (input `type=search` em pílula, rótulo acessível "Buscar modelos") que filtra **no cliente** por título (sem acento/caixa) os dois grupos, e botão "Novo modelo" (abre o diálogo existente).
- [ ] Seções "Meus modelos · N" e "Compartilhados comigo · N" com o rótulo em caixa alta pequena.
- [ ] Cartão (meus modelos): miniatura real (`imagem_url`, `object-contain` sobre `bg-mesa`, altura 196 px), título, linha "N campos · alterado há X" (relativo em pt-BR com `Intl.RelativeTimeFormat`, a partir de `atualizado_em`), selo "N pessoas" quando há compartilhamentos; ações: "Gerar" (primário, desabilitado com dica quando `campos_count === 0`), "Campos" (contorno) e botão "⋯" (`DropdownMenu`) com "Alterar", "Compartilhar", "Excluir" — reaproveite os diálogos e a confirmação de exclusão existentes (o painel de compartilhamento pode abrir no diálogo de opções já existente, direto na aba de compartilhar). Modelo sem campos: borda tracejada, aviso "Nenhum campo ainda" em `text-aviso` e um único botão escuro "Posicionar os campos →".
- [ ] Compartilhados: lista em cartão único (linhas com miniatura pequena, título, "de {dono} · N campos", selo "Pode editar" (`bg-sucesso-fundo text-sucesso`) ou "Só gerar", botão "Gerar"; "Campos" só se `pode_editar`).
- [ ] Estado vazio de cada seção e da busca sem resultado.
- [ ] Verificar e commitar: `feat: tela de modelos com ações diretas e busca`.

### Task 5: Editor de campos em três colunas

**Referência:** `Editor.dc.html`.

**Files:** `resources/js/pages/meus-certificados/campos.tsx`, `resources/js/components/campos/*` (reorganizar: lista de campos, área da imagem, painel de propriedades do campo selecionado). Toda a lógica de autosave/rollback/arraste de `campos-editor.tsx` permanece.

- [ ] Barra superior própria da página (dentro do layout do app): voltar (ícone, `aria-label="Voltar para Modelos"`), "Modelos / Campos" + título do modelo em serif, selo de status do autosave ("Salvando…" / "Salvo agora" em `bg-sucesso-fundo`, erro em `text-destructive`), alternador "Nomes dos campos" | "Com dados de exemplo" (grupo de toggle com `aria-pressed`) e "Ir para geração →" (desabilitado sem campos).
- [ ] Coluna esquerda (260 px): "Campos · N" + botão "+" (`aria-label="Adicionar campo"`, mesmo guard de duplo clique); lista de botões, um por campo (amostra "Aa" na fonte do campo, nome, linha "Família · centralizado" ou "Família · x 1180"); o selecionado com borda `border-primary` e fundo `bg-card`. Dica no pé: "Arraste o texto na imagem ou use as setas do teclado para mover o campo selecionado."
- [ ] Centro (`bg-mesa`): a imagem com `PreviewDoModelo`; clicar num texto o seleciona; o selecionado tem moldura `border-primary` com 4 alças e etiqueta "Nome · centro, y 1460"; os demais, contorno tracejado discreto; centralizado mostra guia vertical tracejada no centro durante a seleção. Abaixo: "Imagem original L × A px · zoom N%".
- [ ] Teclado: com um campo selecionado e o foco na área da imagem, setas movem 1 px da imagem (Shift = 10 px), centralizado só no eixo Y; salva com o mesmo autosave (debounce já existente ou um novo de 400 ms, sem PATCH por tecla).
- [ ] Alternador: "Nomes dos campos" mostra `{nome}`; "Com dados de exemplo" mostra `label` (rótulo do campo) quando preenchido, senão o nome.
- [ ] Coluna direita (300 px, `bg-card`): "Campo “{nome}”" + excluir (ícone lixeira, com confirmação `AlertDialog` — novo), "Nome da coluna" (+ ajuda "É o cabeçalho esperado na planilha."), "Texto de exemplo" (é o `label` existente), Fonte (seletor existente), Tamanho e Espaçamento lado a lado, Cor (4 amostras: tinta `#221A17`, bordô `#6E1F24`, dourado `#9A7428`, branco `#FFFFFF` + botão com o hex atual que abre o seletor de cor atual), Posição: segmentado "Livre | Centralizado" e X/Y (X desabilitado mostrando "centro" quando centralizado). Sem campo selecionado: estado vazio "Selecione um campo na imagem ou na lista."
- [ ] Somente leitura (`pode_editar` falso): sem "+", sem excluir, inputs desabilitados, sem arraste/teclado.
- [ ] Abaixo de `lg`, as três colunas empilham (lista, imagem, propriedades).
- [ ] Verificar e commitar: `feat: editor de campos em três colunas com painel de propriedades`.

### Task 6: Geração (desktop e celular), Gerar todos e zip

**Referência:** `Gerar.dc.html` e `GerarCelular.dc.html`.

**Files:** `resources/js/pages/meus-certificados/gerar.tsx`, `resources/js/components/gerar/*`, `package.json` (fflate), novo `resources/js/lib/zip.ts` + teste.

- [ ] `npm install fflate` (dependência autorizada). `lib/zip.ts`: `export async function zipar(arquivos: { nome: string; blob: Blob }[]): Promise<Blob>` com `zipSync` do fflate (sem compressão: `level: 0`, PNG já é comprimido) e nomes únicos (sufixo `-2`, `-3` quando repetem). Teste Vitest com dois blobs e nomes repetidos.
- [ ] Cabeçalho via `CabecalhoDaPagina` com `modelo`: título "Gerar · {modelo}", ações "Planilha em branco" (baixa o modelo de planilha existente) e "Importar planilha" (diálogo existente).
- [ ] Tabela em cartão: colunas `#`, um por campo, "Situação" (170 px). Inputs sem borda até o foco; linha selecionada (clique/foco) com fundo `bg-aviso-fundo`/realce suave e número em `text-primary`. Situação: pronto → botão "Baixar PNG" (`bg-sucesso-fundo text-sucesso`); gerando → spinner + "Gerando…" (incluindo a espera de 429: "Aguardando limite…"); na fila → "Na fila"; erro → ícone + mensagem em `text-aviso`; linha sem nenhum valor → "Falta preencher" e não entra em "Gerar todos"; linha editada após pronta volta a "Gerar". Última linha: "+ Adicionar linha".
- [ ] Rodapé do cartão: "N de M prontos" + barra de progresso (`role="progressbar"` com `aria-valuenow`), "Baixar prontos (.zip)" (desabilitado sem prontos; nome `certificados-{modelo}.zip`; usa os blobs já gerados) e "Gerar todos" (gera em sequência as linhas não prontas e preenchidas, uma por vez, respeitando o retry de 429 e o abort; cancelável com "Parar" enquanto roda; erros por linha não interrompem as demais). Não baixa cada PNG automaticamente no "Gerar todos" (só no botão de linha, como hoje).
- [ ] Coluna direita (340 px): "Linha N · prévia" + "atualiza enquanto você digita" e o `PreviewDoModelo` com os valores da linha selecionada; abaixo "O arquivo final sai no tamanho original da arte (L × A px)." O Sheet de preview atual sai (o PNG gerado continua baixável).
- [ ] `valores_iniciais` (Task 3): quando presente, a tabela começa com essa linha preenchida e selecionada.
- [ ] Celular (abaixo de `md`), conforme `GerarCelular.dc.html`: prévia no topo, formulário da linha selecionada com campos empilhados (inputs 48 px, fonte 16 px), seletor "Registro N de M" com anterior/próximo e "+", aviso "N certificados gerados nesta sessão · ver", e barra fixa no rodapé com "Limpar" e "Gerar e baixar" (gera a linha atual e baixa). Importar planilha continua no ícone do cabeçalho.
- [ ] Verificar e commitar: `feat: geração com prévia ao lado, gerar todos, zip e layout de celular`.

### Task 7: Histórico

**Referência:** `Historico.dc.html`.

**Files:** `resources/js/pages/historico/index.tsx`, `resources/js/components/historico/*` (o diálogo de detalhes vira painel lateral).

- [ ] Cabeçalho "Histórico" + "Cada certificado emitido, com os dados como foram usados."; à direita busca ("Buscar por nome", envia `busca` com `router.get` + `preserveState`, debounce 300 ms, `replace: true`) e um `Select` "Todos os modelos" com `modelos` (envia `modelo`).
- [ ] Lista agrupada por dia (Hoje / Ontem / dia da semana na semana atual / "12 de setembro" etc., em pt-BR), cada grupo com título serif e contagem; linhas são `<button>` com hora (ou dia), o primeiro valor da `ordem_campos` em serif 21 px, os demais valores resumidos em `text-muted-foreground`, e o selo do modelo. Cor do selo derivada do título (paleta fixa de 4 pares claro/escuro dos tokens; mesmo título, mesma cor).
- [ ] Painel à direita (320 px; abaixo de `lg` vira `Sheet` ao tocar na linha): "Modelo · quando", primeiro valor em serif 30 px, `dl` com todos os campos na ordem e "Gerado por" (`gerado_por`); botões "Gerar de novo com estes dados" (link para `meus-certificados.gerar` com `?geracao={id}`; desabilitado com dica quando o modelo foi excluído — `modelo_id` nulo) e "Copiar dados" (`navigator.clipboard.writeText` com linhas "Campo: valor"; toast "Dados copiados.").
- [ ] Estados vazios: sem gerações; busca sem resultado (com "Limpar filtros").
- [ ] Verificar e commitar: `feat: histórico agrupado por dia com busca, filtro e painel de detalhes`.

### Task 8: Verificação no navegador (controlador)

- [ ] Percorrer, claro e escuro, desktop e celular: landing, login, modelos (busca, ⋯, compartilhar, excluir), editor (selecionar, arrastar, setas, alternador, propriedades, excluir campo), geração (digitar, prévia, gerar linha, gerar todos, parar, zip, importar, celular), histórico (busca, filtro, painel, gerar de novo, copiar). Consertos via subagente.
