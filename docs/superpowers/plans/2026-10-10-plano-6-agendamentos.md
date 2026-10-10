# PequenaVia — Plano 6: Módulo Agendamentos

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Passos com checkbox (`- [ ]`).

**Objetivo:** criar o módulo **Agendamentos**, que ajuda a secretaria a reservar salas e equipamentos da paróquia. Inclui calendário, espaços, equipamentos, grupos e pastorais, além de solicitações vindas de um formulário público. O menu lateral passa a ter duas sanfonas: **Certificados** (Modelos, Histórico) e **Agendamentos** (Calendário, Solicitações, Espaços, Equipamentos, Grupos e pastorais).

**Referência visual (fonte da verdade do layout):** os artboards em `docs/superpowers/design/agendamentos/*.dc.html` são HTML com estilos inline. Reproduza o layout com Tailwind, os tokens do tema e os componentes shadcn existentes. Os dados de exemplo dos artboards (nomes, telefones, "Ana Secretaria", "Paróquia [NOME]") **não** entram no app.

**Arquitetura:** Laravel 13 + Inertia 3 + React 19 (React Compiler) + Tailwind 4 + shadcn, Wayfinder, Pest, Postgres. Tudo é escopado por **dono da agenda**: cada usuário tem a própria agenda (espaços, equipamentos, grupos, reservas e solicitações com `user_id`). O formulário público é identificado por `users.agenda_slug`. Não há compartilhamento de agenda entre usuários nesta versão.

## Regras de negócio

- **Só a secretaria reserva.** Isso significa o usuário logado, dono da agenda. Pessoas de fora só **solicitam**, pelo formulário público.
- **Formulário público** (`/reservar/{slug}`, sem login), com os campos:
  - pastoral ou grupo (lista dos grupos ativos);
  - nome e telefone (WhatsApp);
  - atividade, data, início e fim;
  - espaço desejado: só os ativos com `aceita_solicitacoes`, ou "Qualquer espaço disponível";
  - equipamentos opcionais, em caixas de seleção, sem quantidade;
  - observações opcionais.

  O envio cria uma `Solicitacao` pendente. Proteção: campo honeypot invisível e `throttle` de 5 envios por minuto e 20 por hora por IP.
- **Solicitação:**
  - A secretaria pode editar espaço, data, início, fim, pessoas e quantidade dos equipamentos.
  - Cada cartão mostra o estado de conflito calculado na hora: "Sem conflito", "Conflito" (com o motivo e com sugestões) ou "Escolha um espaço" (sem espaço definido).
  - **Aprovar** só é possível sem conflito e com espaço definido. A aprovação cria a reserva, vincula `reserva_id` e muda o status para `aprovada`.
  - **Recusar** muda o status para `recusada`, com motivo opcional.
  - **Entrar em contato** é o link `https://wa.me/55{dígitos}?text={mensagem}`, que abre em nova aba. A mensagem fixa é: "Olá! Aqui é da secretaria da paróquia, sobre o seu pedido de reserva."
- **Conflito de reserva:** uma reserva candidata (espaço, data, início, fim, equipamentos, reserva ignorada) tem conflito quando acontece qualquer um destes casos:
  1. o espaço está inativo;
  2. o espaço não abre nesse dia da semana, ou o horário sai do intervalo de abertura;
  3. existe um bloqueio do espaço que cobre a data;
  4. existe outra reserva no mesmo espaço e data com sobreposição, considerando o `intervalo_min` do espaço. Duas reservas `[a,b)` e `[c,d)` conflitam se `a < d + intervalo` e `c < b + intervalo`;
  5. para algum equipamento, a soma das quantidades das reservas que se sobrepõem no horário (em qualquer espaço) mais a quantidade pedida passa da `quantidade` do equipamento. Equipamento em manutenção na data conta como 0 disponível.

  O `fim` precisa ser maior que o `inicio`. A duração máxima e a antecedência mínima do espaço valem **só para solicitações públicas**, nunca para a secretaria.
- **Sugestões no conflito:**
  - espaços ativos sem conflito no mesmo horário, com capacidade ≥ pessoas (quando informado), ordenados por capacidade crescente, no máximo 3;
  - o primeiro horário livre do mesmo espaço no mesmo dia, com a mesma duração, dentro do horário de abertura.
- **Recorrência** (só na reserva feita pela secretaria):
  - opções: não repete, toda semana, ou todo mês (mesmo "n-ésimo dia da semana" do mês, por exemplo o 2º sábado), até uma data final;
  - limite de 52 ocorrências;
  - as ocorrências compartilham `serie_id`;
  - o conflito é verificado por ocorrência, e a secretaria pode **pular** datas com conflito antes de salvar;
  - excluir ou editar oferece "só esta" ou "esta e as próximas".
- **Equipamentos:** cada reserva leva equipamentos com quantidade. O painel "Hoje: separar e recolher" lista os equipamentos das reservas do dia, com "Marcar entregue" (`entregue_em`) e "Marcar recolhido" (`recolhido_em`).
- **Fuso:** datas e horários de reserva são **hora local**, gravada como `date` + `time`. O "agora" (linha vermelha no calendário, "Hoje") usa `config('agendamentos.fuso')`, que vale `America/Sao_Paulo`. Não mude `app.timezone`.

## Restrições globais

- Texto visível em pt-BR. Inputs com `label`, botões só com ícone com `aria-label`, alvos de toque ≥ 44 px no celular. O site é só modo claro: não use classes `dark:`.
- Use os tokens existentes (`bg-primary`, `bg-card`, `bg-mesa`, `text-muted-foreground`, `text-sucesso`, `bg-sucesso-fundo`, `text-aviso`, `bg-aviso-fundo`, `font-serif`…). As 5 cores de espaço são uma paleta fixa, definida em um módulo TS. Não espalhe esses hex pelo código.
- **Paleta de espaços** (`resources/js/lib/agenda/cores.ts`): `bordo` {forte #6E1F24, fundo #F1DFDC, texto #521519}, `azul` {#1F2A44, #DFE4EE, #1F2A44}, `verde` {#24513F, #E2EEE8, #1B3D30}, `roxo` {#4B2E5C, #EADFF0, #3A2148}, `dourado` {#7A5A1E, #F3E8CF, #5A4214}.
- Nenhuma dependência nova. `@radix-ui/react-collapsible` já está no `package.json`. Recrie `resources/js/components/ui/collapsible.tsx` no padrão shadcn (wrapper de `Collapsible`, `CollapsibleTrigger` e `CollapsibleContent`).
- Toda rota interna fica em `auth` + `verified`. Policies e escopo por dono: acessar registro de outro usuário retorna **404** (`Response::denyAsNotFound`). Siga `app/Policies/ModeloCertificadoPolicy.php`.
- **Backend:** Form Requests com mensagens pt-BR, `php artisan make:*`, PHPDoc e tipos. Pint: `vendor/bin/pint --dirty --format agent`. Use Pest com factories, test-first.
- **Frontend:** ações via Wayfinder (`@/actions`, `@/routes`) e formulários com `useForm` ou `Form` do Inertia. Use `enviarJson` (`@/lib/http`) só onde precisar de JSON, por exemplo na verificação de conflito ao vivo. Erros viram toast com o padrão `components/modelos/erro-de-acao.ts`. Proteja contra duplo envio. Lógica pura vai para `resources/js/lib/agenda/*.ts`, com Vitest (`import … from 'vite-plus/test'`).
- **Verificação de cada task:** `npm run build`, `npx tsc --noEmit`, `npm run check`, `npx vp test run` e `php artisan test --compact`, todos verdes.
- **Commits:** em pt-BR, com o trailer `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`.

## Modelo de dados (Task 2 cria; todas as chaves `uuid`, `timestamps`)

| Tabela | Colunas |
| --- | --- |
| `users` (alterar) | `agenda_slug` string(40) único, nullable. É gerado na primeira visita ao módulo: `Str::lower(Str::random(10))`. |
| `grupos` | `user_id` fk cascade, `nome` string(120), `coordenacao` string(120) null, `contato` string(120) null, `observacao` text null, `ativo` bool default true. Único (`user_id`, `nome`). |
| `espacos` | `user_id`, `nome` string(120), `capacidade` int unsigned null, `localizacao` string(160) null, `cor` string(10) (um de: bordo, azul, verde, roxo, dourado), `recursos_fixos` jsonb default `[]`, `aceita_solicitacoes` bool default true, `antecedencia_min_horas` int unsigned default 0, `duracao_max_min` int unsigned null, `intervalo_min` int unsigned default 0, `ativo` bool default true. Único (`user_id`, `nome`). |
| `espaco_horarios` | `espaco_id` fk cascade, `dia_semana` tinyint (0 = domingo … 6 = sábado), `abre` time, `fecha` time. Único (`espaco_id`, `dia_semana`). Dia sem linha = fechado. |
| `espaco_bloqueios` | `espaco_id` fk cascade, `inicio` date, `fim` date, `motivo` string(160). |
| `equipamentos` | `user_id`, `nome` string(120), `descricao` string(160) null, `quantidade` int unsigned (≥ 1), `local_guarda` string(120) null, `manutencao_ate` date null. Único (`user_id`, `nome`). |
| `reservas` | `user_id`, `espaco_id` fk restrict, `grupo_id` fk null on delete set null, `titulo` string(160), `data` date, `inicio` time, `fim` time, `pessoas` int unsigned null, `observacoes` text null, `serie_id` uuid null (index), `solicitacao_id` uuid null. Index (`espaco_id`, `data`). |
| `reserva_equipamento` | `reserva_id` fk cascade, `equipamento_id` fk restrict, `quantidade` int unsigned, `entregue_em` timestamp null, `recolhido_em` timestamp null. PK (`reserva_id`, `equipamento_id`). |
| `solicitacoes` | `user_id` (dono da agenda), `grupo_id` fk null, `nome` string(120), `telefone` string(30), `titulo` string(160), `data` date, `inicio` time, `fim` time, `espaco_id` fk null (null = qualquer), `pessoas` int unsigned null, `observacoes` text null, `status` string(10) (`pendente`/`aprovada`/`recusada`, index), `motivo_recusa` string(200) null, `reserva_id` fk null. |
| `solicitacao_equipamento` | `solicitacao_id` fk cascade, `equipamento_id` fk cascade, `quantidade` int unsigned default 1. PK composta. |

Excluir um espaço ou um equipamento com reservas futuras é bloqueado com 422 e a mensagem "Há reservas futuras usando este espaço/equipamento. Desative em vez de excluir."

## Rotas (Task 1 registra o esqueleto; as tasks seguintes preenchem)

Internas (`auth`, `verified`), prefixo `agendamentos`, nomes `agendamentos.*`:

- `GET agendamentos` redireciona para `agendamentos.calendario`.
- `GET agendamentos/calendario?data=YYYY-MM-DD&visao=semana|dia` → `agendamentos.calendario`.
- `POST agendamentos/reservas` (`reservas.store`), `PUT agendamentos/reservas/{reserva}` (`reservas.update`, com `escopo=so_esta|esta_e_proximas`), `DELETE agendamentos/reservas/{reserva}` (`reservas.destroy`, mesmo `escopo`).
- `POST agendamentos/disponibilidade` (`disponibilidade`): recebe `{espaco_id, datas[], inicio, fim, equipamentos:[{id,quantidade}], ignorar_reserva_id?}` e devolve JSON `{datas: [{data, conflitos:[{tipo, mensagem}], sugestoes:{espacos:[{id,nome,capacidade}], horario:{inicio,fim}|null}}]}`.
- `PATCH agendamentos/reservas/{reserva}/equipamentos/{equipamento}` (`reservas.equipamentos.update`): `{acao: entregue|recolhido}`.
- `GET agendamentos/solicitacoes?status=pendente|aprovada|recusada` (`solicitacoes.index`), `PUT agendamentos/solicitacoes/{solicitacao}` (editar), `POST …/{solicitacao}/aprovar`, `POST …/{solicitacao}/recusar`.
- CRUD de espaços: `espacos.index`, `create`, `store`, `edit`, `update`, `destroy`. Horários e bloqueios vão no mesmo `update`, como arrays.
- CRUD de equipamentos: `equipamentos.index`, `store`, `update`, `destroy`. O index traz o painel "Hoje".
- CRUD de grupos: `grupos.index`, `store`, `update`, `destroy`.

Públicas (`guest` não é necessário; sem auth): `GET reservar/{slug}` (`reservar.create`), `POST reservar/{slug}` (`reservar.store`, `throttle:solicitacoes`), `GET reservar/{slug}/enviado` (`reservar.enviado`). Slug inexistente → 404.

---

### Task 1: Menu em sanfona, esqueleto do módulo e slug da agenda

**Referência:** a barra lateral de qualquer artboard em `docs/superpowers/design/agendamentos/` (por exemplo `Grupos.dc.html`, com as duas sanfonas abertas).

**Arquivos:** `resources/js/components/{app-sidebar,nav-main}.tsx`, novo `resources/js/components/ui/collapsible.tsx`, `routes/web.php` (grupo `agendamentos` com o redirect e páginas stub), migration `add_agenda_slug_to_users`, `config/agendamentos.php` (`fuso`), páginas stub `resources/js/pages/agendamentos/{calendario,solicitacoes,espacos/index,equipamentos/index,grupos/index}.tsx`, cada uma com `CabecalhoDaPagina` e um texto "Em construção". Teste em `tests/Feature/Agendamentos/MenuTest.php`.

- [ ] **Menu.** O `nav-main` vira uma lista de seções, cada uma com ícone (lucide `FileText` / `CalendarDays`), título e subitens.
  - Cada seção é um `Collapsible` com `SidebarMenuButton` e chevron, e os subitens usam `SidebarMenuSub` / `SidebarMenuSubButton`.
  - A seção que contém a rota atual começa aberta. O estado aberto/fechado de cada seção é lembrado em `localStorage` (`menu-secao-{id}`, com try/catch).
  - Com a barra recolhida (modo ícone), clicar na seção leva ao primeiro subitem, e o `tooltip` mostra o título.
  - O item "Solicitações" mostra um contador de pendentes (`SidebarMenuBadge`) vindo da prop compartilhada `agenda.solicitacoes_pendentes` (`HandleInertiaRequests`, só para usuário logado, com uma query `count` barata). Esconda o contador quando for 0.
- [ ] **Slug.** Implemente o `agenda_slug` gerado sob demanda (método `User::agendaSlug(): string`, que cria e salva se faltar).
- [ ] **Testes:** o redirect de `agendamentos` vai para o calendário; as páginas stub respondem 200 logado e redirecionam para login sem sessão; a prop `agenda.solicitacoes_pendentes` existe; `agendaSlug()` é estável.
- [ ] Verificar e commitar: `feat: menu em sanfona e esqueleto do módulo de agendamentos`.

### Task 2: Modelos, migrations, factories e policies

**Arquivos:** migrations e models (`Grupo`, `Espaco`, `EspacoHorario`, `EspacoBloqueio`, `Equipamento`, `Reserva`, `Solicitacao`), com tabelas pivô via `belongsToMany` e `withPivot`, conforme "Modelo de dados". Também factories com estados úteis (`Espaco::factory()->comHorarioComercial()`, `->inativo()`; `Equipamento::factory()->emManutencao()`; `Solicitacao::factory()->pendente()`), enum PHP `App\Enums\CorDoEspaco` e `App\Enums\StatusSolicitacao`, uma policy por recurso (dono = `user_id`, estranho → 404) e os relacionamentos em `User` (`espacos()`, `equipamentos()`, `grupos()`, `reservas()`, `solicitacoes()`).

- [ ] Casts: `data` → `date`, `recursos_fixos` → `array`, `cor` e `status` → enums.
- [ ] Testes: relacionamentos; unicidade por dono; policy (dono passa, estranho recebe 404); cascatas.
- [ ] Verificar e commitar: `feat: modelos do módulo de agendamentos`.

### Task 3: Serviço de disponibilidade e conflitos (núcleo do módulo)

**Arquivos:** `app/Services/Agenda/VerificadorDeDisponibilidade.php`, `app/Services/Agenda/Recorrencia.php`, `app/Services/Agenda/Conflito.php` (DTO readonly `{tipo, mensagem}`; tipos: `espaco_inativo`, `fora_do_horario`, `bloqueio`, `sala_ocupada`, `equipamento_indisponivel`), a rota `agendamentos.disponibilidade` e os testes em `tests/Feature/Agendamentos/DisponibilidadeTest.php` e `tests/Unit/RecorrenciaTest.php`.

- [ ] `VerificadorDeDisponibilidade::verificar(Espaco $espaco, CarbonImmutable $data, string $inicio, string $fim, array $equipamentos, ?string $ignorarReservaId): list<Conflito>` aplica as 5 regras de conflito. As mensagens são pt-BR e citam a reserva conflitante, por exemplo: "Já reservado para “Encontro de noivos”, 09:00 – 12:00."
- [ ] `sugestoes(...)` devolve espaços livres e o próximo horário livre, conforme "Sugestões no conflito".
- [ ] `Recorrencia::datas(CarbonImmutable $inicio, 'nao'|'semanal'|'mensal', ?CarbonImmutable $ate): list<CarbonImmutable>` cobre semanal e mensal pelo n-ésimo dia da semana. Quando o mês não tem o 5º sábado, a data é **pulada**. O limite é 52 ocorrências.
- [ ] O endpoint `disponibilidade` só aceita espaço e equipamentos do próprio dono (os de outro usuário retornam 404) e devolve o JSON descrito em "Rotas".
- [ ] Testes, cobrindo cada regra:
  - sobreposição na borda: 10:00–12:00 contra 12:00–13:00, sem conflito com intervalo 0 e com conflito com intervalo 30;
  - equipamento com 2 unidades: 2 reservas simultâneas usam tudo e a 3ª conflita;
  - manutenção, bloqueio, dia fechado e espaço inativo;
  - `ignorarReservaId` (ao editar, a própria reserva não conflita);
  - sugestões ordenadas por capacidade e limitadas a 3;
  - recorrência semanal e mensal com pulo e limite.
- [ ] Verificar e commitar: `feat: verificação de disponibilidade e recorrência das reservas`.

### Task 4: Grupos e pastorais

**Referência:** `Grupos.dc.html`. **Arquivos:** controller, Form Requests, página `agendamentos/grupos/index.tsx`, diálogo de criar/editar, testes.

- [ ] A tabela mostra Grupo, Coordenação (nome + contato), Reuniões fixas (derivadas das séries futuras de reservas do grupo, por exemplo "Seg 19:00 · Sala de catequese"; mostre no máximo 2) e Editar. Tem busca no cliente.
- [ ] O diálogo tem nome, coordenação, contato, observação e um switch "Ativo". Excluir é permitido; as reservas ficam sem grupo.
- [ ] Rodapé: "Os grupos ativos aparecem na lista “Pastoral ou grupo” do formulário de solicitação."
- [ ] Testes de CRUD e escopo.
- [ ] Commit: `feat: grupos e pastorais`.

### Task 5: Espaços

**Referência:** `Espacos.dc.html` e `EditarEspaco.dc.html`. **Arquivos:** controller, Requests, páginas `agendamentos/espacos/{index,form}.tsx` (o form serve para criar e editar), testes.

- [ ] **Índice em cartões**, cada um com:
  - ícone na cor do espaço, nome, "Até N pessoas · localização" e selo de estado: Ativo, Bloqueado (há bloqueio cobrindo hoje, com "Em … até dd/mm") ou Inativo;
  - os recursos fixos como chips e o resumo de horários, agrupando dias iguais ("Seg a sex 08:00–22:00 · Sáb 08:00–18:00");
  - "N reservas nesta semana" e o botão Editar.
- [ ] **Formulário**, com estas seções:
  - **Dados do espaço:** nome, capacidade, localização, cor (5 amostras com `aria-pressed`) e recursos fixos (chips alternáveis, mais "+ Novo recurso" para texto livre). Inclua a nota que aponta para Equipamentos.
  - **Regras de reserva:** "Aparece no formulário de solicitação", antecedência mínima (horas), duração máxima (min, opcional) e intervalo entre reservas (min).
  - **Horários disponíveis:** 7 linhas, cada uma com checkbox e abre/fecha, mais "Copiar seg. para todos".
  - **Bloqueios:** lista com adicionar e remover (início, fim, motivo).
  - **Desativar ou reativar:** o switch "ativo" fica em destaque no rodapé.
- [ ] **Validação:** `fecha > abre`; o bloqueio tem `fim ≥ inicio`; a capacidade fica entre 1 e 2000.
- [ ] Excluir é bloqueado quando há reservas futuras (422 com a mensagem do modelo de dados).
- [ ] Testes: criar com horários; atualizar substituindo horários e bloqueios; validações; exclusão bloqueada; escopo.
- [ ] Commit: `feat: espaços com horários, bloqueios e regras`.

### Task 6: Equipamentos

**Referência:** `Equipamentos.dc.html`. **Arquivos:** controller, Requests, página `agendamentos/equipamentos/index.tsx`, diálogo, rota `reservas.equipamentos.update`, testes.

- [ ] A tabela tem as colunas Equipamento (nome + descrição), Unidades, Onde fica guardado, Agora e Em uso nesta semana, mais o botão Editar.
  - **Agora:** "N em uso · {espaço}", "N livres" ou "Em manutenção até dd/mm", calculado no fuso da agenda.
  - **Em uso nesta semana:** 7 células "D n/q", com o pico de uso simultâneo do dia e tom mais forte quando `n == q`.
- [ ] Equipamento em manutenção fica esmaecido, com "não aparece para reservar".
- [ ] **Painel "Hoje: separar e recolher":**
  - para cada reserva de hoje com equipamentos, uma linha "Levar" no horário de início − 15 min e uma linha "Recolher" no fim;
  - os botões "Marcar entregue" e "Marcar recolhido" usam o PATCH, e o estado aparece como selo.
- [ ] A exclusão é bloqueada quando há reservas futuras.
- [ ] Teste do cálculo do pico de uso diário e do PATCH (inclusive o escopo).
- [ ] Commit: `feat: equipamentos com uso semanal e painel do dia`.

### Task 7: Calendário e reservas

**Referência:** `Calendario.dc.html`, `NovaReserva.dc.html`, `OcupacaoDia.dc.html` e `AgendaCelular.dc.html`. **Arquivos:** `ReservaController` (página `calendario` + store/update/destroy), Requests, `resources/js/pages/agendamentos/calendario.tsx`, componentes em `resources/js/components/agenda/` (`grade-da-semana`, `ocupacao-do-dia`, `painel-de-reserva`, `mini-calendario`, `filtro-de-espacos`, `agenda-celular`), `resources/js/lib/agenda/{datas,grade,cores}.ts` + testes, testes PHP.

- [ ] **Props da página:** `semana` (início e fim), `visao`, `data`, as reservas do intervalo (com espaço, grupo, equipamentos e `serie_id`), espaços ativos (cor, capacidade, horários), equipamentos disponíveis, grupos ativos, as `solicitacoes` pendentes do intervalo (para a visão dia) e `agora` (ISO no fuso).
- [ ] **Semana:**
  - grade de 07:00 a 22:00, com 48 px por hora;
  - blocos posicionados por `lib/agenda/grade.ts` (função pura e testada: topo e altura, mais colunas lado a lado quando há sobreposição no mesmo dia);
  - linha "agora" em vermelho, coluna de hoje destacada e ícone de recorrência;
  - clicar num bloco abre o painel em modo edição; clicar num espaço vazio abre "Nova reserva" naquele dia e hora;
  - filtro de espaços (checkboxes, lembrados em `localStorage`), mini calendário do mês e os botões Hoje, ‹ e ›.
- [ ] **Dia (ocupação):**
  - uma linha por espaço, com o eixo de horas;
  - os períodos fechados ou bloqueados aparecem hachurados, com o texto do motivo;
  - "Livre … · reservar" abre o painel;
  - a seção "Solicitações para estes dias" mostra as pendentes da data. Cada uma tem selo de conflito, **Entrar em contato** (wa.me), Recusar e "Aprovar" (sem conflito) ou "Resolver" (com conflito, leva a Solicitações).
- [ ] **Painel de reserva** (Sheet à direita, 560 px):
  - campos: título, grupo, pessoas, espaço (com capacidade e recursos fixos), data, início e fim, repetir (Não repete | Toda semana | Todo mês, mais "até" e o resumo "No 2º sábado de cada mês, até dd/mm/aaaa · N datas"), equipamentos (linhas com stepper − / + e a disponibilidade "x de q livres nas N datas") e observações;
  - a disponibilidade é consultada em `agendamentos.disponibilidade`, com debounce de 300 ms, abort da requisição anterior e mensagem de carregamento;
  - quando há conflito: alerta "N das M datas têm conflito", com motivos, sugestões clicáveis (trocar espaço ou horário) e "Pular esta data";
  - o botão salvar fica desabilitado até não haver conflito, com o texto "Resolva o conflito para salvar";
  - na edição, mostra "Excluir" com confirmação e, se a reserva for de série, a escolha "Só esta" ou "Esta e as próximas".
- [ ] **No servidor, o store e o update revalidam tudo com o verificador.** Não confie no cliente: com conflito, a resposta é 422 com a lista de conflitos. O store cria todas as ocorrências da série numa transação, com os equipamentos.
- [ ] **"Imprimir semana":** `window.print()` com CSS de impressão (`@media print`) que esconde menu e barras e imprime só a grade da semana em paisagem.
- [ ] **Celular** (abaixo de `md`), conforme `AgendaCelular.dc.html`: dias da semana como abas, lista de reservas do dia com o marcador "Agora", "Livres agora" e botão flutuante de nova reserva. O painel abre em tela cheia.
- [ ] **Testes PHP:** a página filtra pelo intervalo e pelo dono; store simples, store com série e datas puladas, store com conflito (422) e update ignorando a própria reserva; destroy com os dois escopos; escopo de dono em todas as rotas.
- [ ] **Testes Vitest:** a grade (posição e sobreposição), as datas (início da semana na segunda-feira, formatação pt-BR) e o resumo da recorrência.
- [ ] Commit: `feat: calendário semanal e diário com reservas, recorrência e equipamentos`.

### Task 8: Formulário público e Solicitações

**Referência:** `FormularioPublico.dc.html` e `Solicitacoes.dc.html`. **Arquivos:** `SolicitacaoPublicaController` (create/store/enviado), `SolicitacaoController` (index/update/aprovar/recusar), Requests, `RateLimiter::for('solicitacoes', …)` no `AppServiceProvider` (5/min e 20/h por IP), páginas `resources/js/pages/reservar/{create,enviado}.tsx` (layout próprio, público e responsivo) e `resources/js/pages/agendamentos/solicitacoes.tsx`, `resources/js/lib/agenda/whatsapp.ts` (`linkDoWhatsApp(telefone): string | null`, que normaliza para dígitos e acrescenta `55` quando o número tem 10–11 dígitos; teste), testes.

- [ ] **Página pública:**
  - cabeçalho bordô com o logo e "Paróquia {nome do dono da agenda}", com o título "Solicitar uma sala";
  - campos conforme as regras de negócio, com campo honeypot `site` escondido do usuário e de leitores de tela (`aria-hidden`, `tabindex=-1`, `autocomplete=off`); se vier preenchido, a resposta finge sucesso e não grava;
  - validação: telefone com 10–13 dígitos, data ≥ hoje, `fim > inicio`, respeito à antecedência mínima e à duração máxima do espaço escolhido, e espaço/grupo/equipamentos pertencentes ao dono e aceitos (o espaço precisa ter `aceita_solicitacoes` e estar ativo);
  - após o envio, redireciona para "enviado", que diz "Pedido enviado. A secretaria vai entrar em contato pelo WhatsApp." e oferece um link para fazer outro pedido.
- [ ] **Solicitações (secretaria):**
  - no topo, o link do formulário (`reservar.create` com o slug) em input somente leitura, com "Copiar" (clipboard + toast) e "Ver";
  - abas Pendentes (com contagem), Aprovadas e Recusadas.
- [ ] **Cartão da solicitação:**
  - cabeçalho com título, "nome · grupo · telefone", "Recebida há …" e o selo de estado (Conflito / Sem conflito / Escolha um espaço);
  - campos editáveis em linha (espaço, data, início, fim, pessoas e os equipamentos com quantidade), salvos com o `PUT` e debounce de 600 ms;
  - o alerta de conflito com motivos e sugestões clicáveis, as observações e o rodapé com **Entrar em contato** (link `wa.me`, `target=_blank`, `rel=noopener noreferrer`, com o rótulo acessível "pelo WhatsApp (abre em nova aba)"), Recusar (diálogo com motivo opcional) e Aprovar ("Aprovar e reservar" quando está tudo livre; desabilitado com explicação quando não está);
  - o estado de conflito é calculado no servidor para cada cartão, com o mesmo verificador, e devolvido nas props e na resposta do `PUT`.
- [ ] **Aprovar** revalida no servidor (422 se houver conflito) e cria a reserva com os equipamentos numa transação. Ela grava `solicitacao.reserva_id` e `status=aprovada`, a reserva recebe `solicitacao_id`, e o calendário passa a mostrá-la.
- [ ] **Testes:**
  - envio público válido;
  - honeypot;
  - throttle (o sexto envio no mesmo minuto recebe 429);
  - slug inválido (404);
  - espaço de outro dono ou sem `aceita_solicitacoes` (422);
  - edição pela secretaria;
  - aprovar sem conflito e com conflito;
  - recusar;
  - escopo de dono;
  - `linkDoWhatsApp` (Vitest).
- [ ] Commit: `feat: formulário público de solicitação e aprovação pela secretaria`.

### Task 9: Verificação no navegador (controlador)

- [ ] Percorrer no desktop e no celular: menu em sanfona (abrir e fechar, lembrar o estado, barra recolhida), cadastro de 3 espaços com horários e um bloqueio, 2 equipamentos e 2 grupos.
- [ ] Testar a reserva simples, a série mensal com conflito e pulo de data, o conflito de equipamento, editar e excluir uma série, a impressão da semana e a visão do dia.
- [ ] Enviar um pedido pelo formulário público em aba anônima e testar a solicitação: conflito no cartão, edição, aprovar e recusar, e o link do WhatsApp.
- [ ] Consertos via subagente.
