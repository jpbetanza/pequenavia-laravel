# PequenaVia — Plano 10: menu superior com menus suspensos

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Passos com checkbox (`- [ ]`).

**Objetivo:** a barra lateral sai. O app passa a ter uma barra no topo com **Início · Agendamentos ▾ · Certificados ▾**, o botão do assistente e o menu da conta; no celular, um botão abre o menu em tela cheia. Todo elemento clicável mostra o cursor de mão.

**Arquitetura:** um layout novo `app-header-layout.tsx` substitui `app-sidebar-layout.tsx` em `app-layout.tsx`. As seções do menu (hoje em `app-sidebar.tsx`) viram dados puros em `resources/js/lib/menu/secoes.ts`, usados pelo topo e pelo menu do celular. Os menus suspensos usam o `DropdownMenu` do shadcn que já existe (clique, teclado e foco prontos). O painel do assistente continua à direita, abaixo da barra.

**Tech stack:** Inertia 3 + React 19 (React Compiler) + Tailwind 4 + shadcn/Radix, Wayfinder, Vitest (`vite-plus/test`), Pest.

**Design de referência:** `docs/superpowers/design/menu-superior/TopoSuspenso.dc.html` (desktop, menu de Agendamentos aberto) e `TopoCelular.dc.html` (celular, menu aberto). HTML com estilo inline: traduza para Tailwind com os tokens; não copie `style=""`.

## Restrições globais

- Texto em pt-BR exatamente como no design. Botões só-ícone com `aria-label`; alvos ≥ 44 px; foco visível.
- Site só modo claro: nenhuma classe `dark:`. Cores só por tokens do tema (`bg-background`, `bg-card`, `bg-secondary`, `text-primary`, `bg-primary`, `text-muted-foreground`, `border-border`, `text-dourado`, `font-serif`…).
- Nenhuma dependência nova. Nunca `dangerouslySetInnerHTML`.
- Rotas por Wayfinder (`@/routes`, `@/routes/agendamentos/...`, `@/routes/meus-certificados`, `@/routes/historico`).
- **Ambiente:** só em `/Users/jpbetanza/Projetos/pequenavia-laravel/.worktrees/tela-inicial` (branch `feat/menu-superior`). Toda shell: `export PATH="$HOME/.config/herd-lite/bin:$PATH"`; testes PHP: `export DB_DATABASE=pequenavia_test_inicio`.
- **Verificação de cada task:** `npm run build`, `npx tsc --noEmit`, `npm run check` (`npx vp check --fix` se reclamar de formatação), `npx vp test run`, `php artisan test --compact` — todos verdes.
- Commits em pt-BR; última linha (após linha em branco) `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`. Sem push.

## Review Focus

1. **Seção ativa:** a aba certa fica sublinhada em qualquer subpágina (ex.: `/agendamentos/espacos/123/editar` → Agendamentos; `/meus-certificados/abc/campos` → Certificados; `/inicio` → Início). Teste em Vitest.
2. **Painel do assistente** não pode ficar por baixo da barra nem rolar junto com ela: no desktop ele começa logo abaixo da barra e ocupa o resto da altura.
3. **Teclado:** abrir o menu suspenso com Enter/Espaço, navegar com setas, Esc fecha e devolve o foco à aba (o `DropdownMenu` do Radix já faz; não reimplemente).
4. **Cursor:** `cursor: pointer` em todo botão, link, aba e item de menu habilitado; desabilitado não mostra mão.

---

## Task 1: Barra superior no desktop, cursor e remoção da barra lateral

**Files:**
- Create: `resources/js/lib/menu/secoes.ts`, `resources/js/lib/menu/secoes.test.ts`
- Create: `resources/js/components/menu-superior/barra-superior.tsx`, `menu-do-modulo.tsx`, `menu-da-conta.tsx`
- Create: `resources/js/layouts/app/app-header-layout.tsx`
- Modify: `resources/js/layouts/app-layout.tsx` (usa o layout novo)
- Modify: `resources/js/components/assistente/painel-do-assistente.tsx` (posição abaixo da barra, só no desktop)
- Modify: `resources/js/components/assistente/botao-do-assistente.tsx` (tirar o `ml-auto`; quem posiciona é a barra)
- Modify: `resources/css/app.css` (cursor)
- Delete (se nada mais importar): `resources/js/components/app-sidebar.tsx`, `resources/js/components/app-sidebar-header.tsx`, `resources/js/components/nav-main.tsx`, `resources/js/components/nav-user.tsx`, `resources/js/layouts/app/app-sidebar-layout.tsx`. Mantenha `components/ui/sidebar.tsx` e `app-shell.tsx`/`app-content.tsx` (o `AppShell variant="header"` já existe). Antes de apagar, `grep -rn` cada nome em `resources/js`.

- [ ] **Step 1: Dados do menu com Vitest (test-first)**

`resources/js/lib/menu/secoes.ts`:

```ts
import type { InertiaLinkProps } from '@inertiajs/react';

export type ItemDoMenu = {
    titulo: string;
    descricao: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Número no item; some quando é 0. */
    contador?: number;
};

export type SecaoDoMenu = {
    id: 'agendamentos' | 'certificados';
    titulo: string;
    /** Prefixos de caminho que pertencem à seção (sem domínio, começando com "/"). */
    prefixos: string[];
    itens: ItemDoMenu[];
};

export type IdDaAba = 'inicio' | SecaoDoMenu['id'];

/** Aba ativa para um caminho ("/agendamentos/espacos" → "agendamentos"). Fora das seções: null. */
export function abaAtiva(caminho: string, secoes: SecaoDoMenu[]): IdDaAba | null;
```

`secoesDoMenu(solicitacoesPendentes: number): SecaoDoMenu[]` também mora aqui, montando com Wayfinder (mesmos destinos de `app-sidebar.tsx` hoje):

| Seção | prefixos | itens (titulo — descricao) |
|---|---|---|
| Agendamentos | `/agendamentos` | Calendário — A semana das salas, por espaço · Solicitações — Pedidos das pastorais para decidir (`contador`) · Espaços — Salas, capela, horários e bloqueios · Equipamentos — Projetores e o que separar hoje · Grupos e pastorais — Quem reserva e as reuniões fixas |
| Certificados | `/meus-certificados`, `/historico` | Modelos — Artes e campos de cada sacramento · Histórico — Certificados já emitidos |

`abaAtiva`: `/inicio` (ou `/inicio?x`) → `'inicio'`; caminho igual a um prefixo ou começando com `prefixo + '/'` → id da seção; senão `null`. Ignore query string e barra final.

`secoes.test.ts` (o arquivo de teste monta as seções à mão com `href: '/x'`, sem Wayfinder):

```ts
import { describe, expect, it } from 'vite-plus/test';
import { abaAtiva, type SecaoDoMenu } from './secoes';

const secoes: SecaoDoMenu[] = [
    { id: 'agendamentos', titulo: 'Agendamentos', prefixos: ['/agendamentos'], itens: [] },
    { id: 'certificados', titulo: 'Certificados', prefixos: ['/meus-certificados', '/historico'], itens: [] },
];

describe('abaAtiva', () => {
    it('reconhece o início', () => {
        expect(abaAtiva('/inicio', secoes)).toBe('inicio');
        expect(abaAtiva('/inicio?pergunta=1', secoes)).toBe('inicio');
    });

    it('reconhece subpáginas da seção', () => {
        expect(abaAtiva('/agendamentos/espacos/01abc/editar', secoes)).toBe('agendamentos');
        expect(abaAtiva('/agendamentos/', secoes)).toBe('agendamentos');
        expect(abaAtiva('/meus-certificados/01abc/campos', secoes)).toBe('certificados');
        expect(abaAtiva('/historico?pagina=2', secoes)).toBe('certificados');
    });

    it('não confunde prefixo parecido', () => {
        expect(abaAtiva('/agendamentosx', secoes)).toBeNull();
        expect(abaAtiva('/settings/profile', secoes)).toBeNull();
    });
});
```

- [ ] **Step 2: Cursor de mão (global)**

Em `resources/css/app.css`, dentro do `@layer base` existente (crie se não houver), acrescente:

```css
    button:not(:disabled),
    [role='button']:not([aria-disabled='true']),
    [role='tab']:not([aria-disabled='true']),
    [role='menuitem']:not([data-disabled]),
    [role='menuitemradio']:not([data-disabled]),
    [role='menuitemcheckbox']:not([data-disabled]),
    [role='option']:not([data-disabled]),
    select:not(:disabled),
    summary,
    label[for],
    input[type='checkbox']:not(:disabled),
    input[type='radio']:not(:disabled),
    input[type='file']:not(:disabled) {
        cursor: pointer;
    }

    :disabled,
    [aria-disabled='true'],
    [data-disabled] {
        cursor: not-allowed;
    }
```

(O Tailwind 4 tira o cursor de mão dos botões por padrão; isto devolve para o app inteiro, inclusive os componentes do shadcn.)

- [ ] **Step 3: Barra superior**

`BarraSuperior` (em `barra-superior.tsx`), `<header>` `sticky top-0 z-40 h-16 border-b border-border bg-background print:hidden`, conteúdo `mx-auto flex h-full max-w-[1376px] items-center gap-7 px-4 md:px-8`:
- Logo (componente `AppLogo` existente) como `Link` para `inicio()`.
- `<nav aria-label="Navegação do app">` **só a partir de `md`** (`hidden md:flex`), com:
  - aba **Início** (`Link`, ícone `House`);
  - aba **Agendamentos** = `MenuDoModulo` (ícone `CalendarDays`), com o contador de solicitações pendentes ao lado do título quando > 0 (`aria-label` "N pedidos pendentes");
  - aba **Certificados** = `MenuDoModulo` (ícone `FileText`).
  - Estilo de aba: `h-16 px-3.5 inline-flex items-center gap-2 border-b-2 text-[15px] font-semibold`; ativa `border-primary text-primary` + `aria-current="page"` (no `Link`) ; inativa `border-transparent text-foreground/80 hover:text-foreground`. A ativa vem de `abaAtiva(usePage().url, secoes)`.
- À direita (`ml-auto flex items-center gap-3`): `BotaoDoAssistente` (só se `assistente?.disponivel`) e `MenuDaConta`.
- O contador vem de `usePage().props.agenda?.solicitacoes_pendentes ?? 0`, como hoje no `app-sidebar.tsx`.

`MenuDoModulo({ secao, ativa, icone })`: `DropdownMenu` com `DropdownMenuTrigger asChild` num `<button>` no estilo de aba + `ChevronDown` (gira 180° com `group-data-[state=open]`). `DropdownMenuContent align="start" sideOffset={6}` `w-[330px] rounded-2xl p-2`. Cada item: `DropdownMenuItem asChild` com `Link` (`prefetch`), duas linhas — título `font-semibold` (+ contador como pílula `bg-primary text-primary-foreground` em Solicitações) e descrição `text-[13px] text-muted-foreground`. O item da página atual tem `bg-secondary` e `aria-current="page"` (use `useCurrentUrl().isCurrentUrl`). No menu de **Agendamentos**, depois de um `DropdownMenuSeparator`, o item **"+ Nova reserva"** (`font-semibold text-primary`) levando para `calendario()`.

`MenuDaConta`: `DropdownMenu` com gatilho só-ícone redondo `size-10 rounded-full bg-primary text-primary-foreground` mostrando as iniciais (use `useInitials` de `@/hooks/use-initials` se existir — confira; senão o `UserInfo`/avatar existente), `aria-label={\`Conta de ${user.name}\`}`; conteúdo `align="end"` com o `UserMenuContent` existente.

`app-header-layout.tsx`:

```tsx
export default function AppHeaderLayout({ children, breadcrumbs = [] }: AppLayoutProps) {
    return (
        <ProvedorDoAssistente>
            <AppShell variant="header">
                <BarraSuperior />
                <div className="flex min-h-0 flex-1">
                    <main className="min-w-0 flex-1 overflow-x-clip">
                        {breadcrumbs.length > 1 && (
                            <div className="mx-auto max-w-[1376px] px-4 pt-4 md:px-8">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        )}
                        {children}
                    </main>
                    <PainelDoAssistente />
                </div>
            </AppShell>
        </ProvedorDoAssistente>
    );
}
```

Em `app-layout.tsx`, importe `AppHeaderLayout` no lugar de `AppSidebarLayout`.

As páginas hoje têm padding próprio pensado para a coluna ao lado da barra lateral; **não mexa nelas** nesta task, exceto se alguma ficar sem margem lateral — nesse caso, anote no relatório.

- [ ] **Step 4: Painel do assistente abaixo da barra**

Em `painel-do-assistente.tsx`, só a classe do modo desktop muda: de `sticky top-0 h-svh` para `sticky top-16 h-[calc(100svh-4rem)]` (a barra tem `h-16`). O modo celular (`fixed inset-0 z-50`) fica igual. Em `botao-do-assistente.tsx`, remova `ml-auto`.

- [ ] **Step 5: Verificar e commitar**

Verificação completa. Commit: `feat: menu superior com menus suspensos e cursor de mão em todo clicável`.

---

## Task 2: Menu no celular

**Files:**
- Create: `resources/js/components/menu-superior/menu-do-celular.tsx`
- Modify: `resources/js/components/menu-superior/barra-superior.tsx`

- [ ] **Step 1: Botão e tela cheia**

Abaixo de `md`, a barra mostra: logo, e à direita o botão do assistente **só-ícone** (`Sparkles`, `aria-label="Abrir o assistente"`, mesmo `alternar` do `useAssistente`; o `BotaoDoAssistente` de texto fica `hidden md:inline-flex`) e o botão **Menu** (`Menu`/`X`, `aria-label` "Abrir menu"/"Fechar menu", `aria-expanded`, `aria-controls="menu-do-celular"`).

`MenuDoCelular` usa o `Sheet` do shadcn (`components/ui/sheet.tsx`) com `side="top"` em tela cheia (`h-svh`), título acessível "Menu" (`SheetTitle` com `sr-only`). Conteúdo conforme `TopoCelular.dc.html`: item **Início** destacado quando ativo; rótulos de seção em maiúsculas pequenas `text-dourado` com ícone (AGENDAMENTOS, CERTIFICADOS); itens com `min-h-12`, contador em Solicitações; no rodapé, avatar + nome + links **Configurações** (`edit()` de `@/routes/profile`) e **Sair** (mesmo comportamento do `UserMenuContent`: `logout()` com `router.flushAll()` antes).

Fechar o menu ao navegar: cada `Link` chama `setAberto(false)` no `onClick` (o hook `useMobileNavigation` existente pode ajudar — confira o que ele faz).

- [ ] **Step 2: Verificar e commitar**

Verificação completa. Commit: `feat: menu em tela cheia no celular`.

---

## Task 3: Verificação no navegador (controlador)

- [ ] Desktop: abas, aba ativa em subpáginas, menus suspensos (clique, teclado, Esc), contador, "+ Nova reserva", menu da conta (Configurações, Sair).
- [ ] Painel do assistente abaixo da barra; pergunta pelo Início continua mandando 1 conversa + 1 mensagem.
- [ ] Celular 375 px: botão menu, tela cheia, navegação fecha o menu, sem rolagem lateral.
- [ ] Cursor de mão em botões, abas e itens; páginas existentes (Calendário, Solicitações, Modelos, Gerar, Configurações) sem quebra de layout sem a barra lateral.
