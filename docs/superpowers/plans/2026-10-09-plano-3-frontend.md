# PequenaVia Laravel — Plano 3: Frontend

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Portar toda a interface do PequenaVia (Next.js + HeroUI) para o monolito Laravel com Inertia + React + shadcn/ui, em português, com a identidade visual original: lista de modelos e compartilhamento, editor de campos com arrastar e soltar, geração de certificados (manual ou por Excel), histórico, landing, login e configurações.

**Architecture:** As páginas são Inertia (props do controller, sem busca de dados no cliente). Mutações simples (modelos, compartilhamentos) usam formulários do Inertia, com redirect e toast do servidor. O editor de campos e a geração falam JSON por `fetch` (com o token CSRF), porque salvam a cada alteração, sem recarregar a página. A lógica com regras (geometria do editor, planilha, estado das linhas, fontes, cliente HTTP) fica em módulos puros em `resources/js/lib/`, testados com o Vitest embutido no Vite+ (`vp test run`).

**Tech Stack:** Inertia 3, React 19 (React Compiler ligado), TypeScript, Tailwind 4, shadcn/ui (Radix, estilo new-york), Wayfinder, Vite+ (`vp`), SheetJS 0.20.3 (planilhas), Pest (backend).

**Spec:** `docs/superpowers/specs/2026-10-06-migracao-laravel-design.md`.
**Depende de:** Planos 1 e 2 (em `main`).
**Antes de começar:** `git checkout -b feat/frontend` a partir de `main`; `docker compose up -d` e o `export PATH="$HOME/.config/herd-lite/bin:$PATH"` do `php.new` precisam estar de pé.

## O que a leitura do app antigo mostrou (e por que o plano difere de um porte literal)

| Achado no app antigo (Next)                                                                                                     | Decisão neste plano                                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| O editor salva cada alteração na hora (`onBlur`, fim do arraste); o botão "Salvar" só repete tudo e mostra um toast.            | Sem botão "Salvar": cada alteração é um `PATCH` JSON com atualização otimista e reversão com toast em caso de erro.                                                                                                                                                            |
| O preview centralizava o texto medindo com `canvas.measureText`. A página de geração já usava `left: 50%` + `translateX(-50%)`. | O editor também usa `left: 50%` + `translateX(-50%)`: o navegador centraliza a caixa do próprio texto, que inclui o espaçamento depois da última letra. É exatamente o que o servidor faz desde o Plano 2. Sem canvas.                                                         |
| As fontes vinham do Google Fonts por `<link>`.                                                                                  | `@font-face` apontando para `fontes.show` (arquivos do próprio servidor, os mesmos que geram o PNG), só para os pesos que existem. O seletor de peso só oferece os pesos da família escolhida.                                                                                 |
| A página `gerar` (`/meus-certificados/{id}/gerar`) existia, mas o Plano 1 só criou a de `campos`.                               | Task 1 acrescenta `GET /meus-certificados/{modelo}/gerar`.                                                                                                                                                                                                                     |
| A lista passava `camposCountMap` para desabilitar "Gerar" em modelo sem campos.                                                 | Task 1 acrescenta `campos_count` a cada modelo.                                                                                                                                                                                                                                |
| O histórico mostrava os 3 primeiros valores na ordem do JSON, mas o `jsonb` do Postgres não guarda a ordem das chaves.          | Task 1 devolve `ordem_campos` (nomes na ordem do modelo) e a tela ordena por ela.                                                                                                                                                                                              |
| O app antigo importava Excel com `xlsx@0.18.5` do npm, que tem CVEs conhecidos (prototype pollution e ReDoS).                   | SheetJS **0.20.3**, instalado do CDN oficial da SheetJS (a versão corrigida não sai mais no npm).                                                                                                                                                                              |
| Animações de modal com `framer-motion`.                                                                                         | Sem `framer-motion`; os diálogos do shadcn bastam.                                                                                                                                                                                                                             |
| Os componentes eram HeroUI.                                                                                                     | shadcn/ui. O kit já traz `dialog`, `sheet`, `select`, `dropdown-menu`, `card`, `badge`, `input`, `label`, `checkbox`, `toggle`, `alert`, `skeleton`, `spinner`, `sonner`, `tooltip`; a Task 2 acrescenta `popover`, `command`, `switch`, `table`, `alert-dialog` e `textarea`. |
| A página `/certificados` era código morto.                                                                                      | Não é portada (já estava fora do escopo do spec).                                                                                                                                                                                                                              |
| O limite de 60 gerações por minuto (Plano 2) vale por usuário, e o lote do Excel faz uma requisição por linha.                  | O cliente trata `429` esperando o `Retry-After` e tentando de novo (até 4 tentativas).                                                                                                                                                                                         |
| Não há executor de teste de componentes no projeto (sem jsdom nem Testing Library).                                             | Testes unitários só da lógica pura, com `vp test run` (Vitest 4, já embutido). A interface é verificada por `tsc`, `vp check`, build e uma verificação manual no navegador no fim.                                                                                             |

## Global Constraints

- Inertia 3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui (Radix, estilo `new-york`). **HeroUI e `framer-motion` não entram no projeto.**
- Todo texto visível ao usuário em português do Brasil. Identificadores do domínio em português (como já estão nos models e rotas); nomes de arquivos em kebab-case.
- Rotas só pelos helpers do Wayfinder (`@/actions/App/Http/Controllers/...` e `@/routes/...`), nunca URLs escritas à mão. Eles são gerados pelo `npm run build`: rode-o antes do `npx tsc --noEmit`.
- Páginas devolvem um fragmento com `<Head>` e declaram `Pagina.layout = { breadcrumbs: [...] }` (padrão do kit). O layout é escolhido por prefixo no `app.tsx`.
- Toasts com `toast` do `sonner`; os toasts que o servidor manda (`Inertia::flash('toast', ...)`) já são tratados pelo hook do kit.
- React Compiler ligado: componentes puros, sem ler nem escrever `ref.current` durante a renderização (só em eventos e efeitos).
- Formatação e lint: `npx vp fmt <arquivos>` e `npm run check`. Código TS com 4 espaços e aspas simples (padrão do kit).
- Ao fim de cada tarefa: `npm run build`, `npx tsc --noEmit`, `npm run check`, `npx vp test run` e `php artisan test --compact` verdes.
- Backend: Pest, `vendor/bin/pint --dirty --format agent` depois de editar PHP, `php artisan make:*` com `--no-interaction`.
- Commits terminam com a linha `Co-Authored-By` indicada na sessão (nas execuções anteriores: `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>` para os subagentes).
- Todo shell novo precisa de `export PATH="$HOME/.config/herd-lite/bin:$PATH"` até o terminal ser reiniciado.

---

## Mapa de arquivos

```
app/Http/Controllers/ModeloCertificadoController.php       # Modify: campos_count (Task 1)
app/Http/Controllers/CampoCertificadoController.php        # Modify: respostas JSON (Task 1)
app/Http/Controllers/GeracaoCertificadoController.php      # Modify: página de geração (Task 1)
app/Http/Controllers/HistoricoController.php               # Modify: ordem_campos (Task 1)
routes/web.php                                             # Modify (Task 1)
lang/pt_BR.json, lang/pt_BR/{auth,passwords,validation}.php   # Create (Task 4)

resources/js/types/certificados.ts                         # Create (Task 2)
resources/js/lib/http.ts                                   # Create (Task 2)
resources/js/lib/fontes.ts                                 # Create (Task 2)
resources/js/lib/editor.ts                                 # Create (Task 2)
resources/js/lib/planilha.ts                               # Create (Task 2)
resources/js/lib/geracao.ts                                # Create (Task 2)
resources/js/lib/historico.ts                              # Create (Task 2)
resources/js/lib/*.test.ts                                 # Create (Task 2)
resources/js/hooks/use-fontes-do-catalogo.ts               # Create (Task 2)
resources/js/components/ui/{popover,command,switch,table,alert-dialog,textarea}.tsx   # Create via shadcn (Task 2)
package.json, package-lock.json                            # Modify: xlsx, script test (Task 2)

resources/css/app.css, vite.config.ts                      # Modify: paleta e fonte serifada (Task 3)
resources/views/app.blade.php                              # Modify: favicon (Task 3)
public/images/logo.png, public/favicon.ico                 # Create/replace (Task 3)
resources/js/components/{app-logo,app-logo-icon,app-sidebar,cabecalho-da-pagina}.tsx   # Modify/Create (Task 3)
resources/js/pages/welcome.tsx                             # Rewrite (Task 3)
resources/js/pages/dashboard.tsx                           # Delete (Task 3)

resources/js/pages/auth/*.tsx, pages/settings/*.tsx, layouts/**, components/*.tsx      # Translate (Task 4)

resources/js/pages/meus-certificados/index.tsx             # Rewrite (Task 5)
resources/js/components/modelos/{cartao-de-modelo,dialogo-novo-modelo,dialogo-alterar-modelo,dialogo-opcoes-do-modelo,painel-de-compartilhamento,secao-compartilhados}.tsx   # Create (Task 5)

resources/js/pages/meus-certificados/campos.tsx            # Rewrite (Task 6)
resources/js/components/campos/{seletor-de-fonte,campo-numerico,campo-de-texto,cartao-do-campo,preview-do-modelo,campos-editor}.tsx   # Create (Task 6)

resources/js/pages/meus-certificados/gerar.tsx             # Create (Task 7)
resources/js/components/gerar/{tabela-de-geracao,dialogo-de-importacao,painel-de-preview,gerar-certificados}.tsx   # Create (Task 7)

resources/js/pages/historico/index.tsx                     # Rewrite (Task 8)
resources/js/components/historico/{tabela-do-historico,dialogo-de-detalhes}.tsx   # Create (Task 8)

tests/Feature/...                                          # Modify/append (Task 1)
```

---

### Task 1: Ajustes de backend que o frontend exige

**Files:**

- Modify: `app/Http/Controllers/ModeloCertificadoController.php`, `app/Http/Controllers/CampoCertificadoController.php`, `app/Http/Controllers/GeracaoCertificadoController.php`, `app/Http/Controllers/HistoricoController.php`, `routes/web.php`
- Test: `tests/Feature/Modelos/ModeloCertificadoControllerTest.php`, `tests/Feature/Campos/CampoCertificadoControllerTest.php`, `tests/Feature/Certificados/GeracaoCertificadoControllerTest.php`, `tests/Feature/Historico/HistoricoControllerTest.php`, `tests/Feature/DashboardTest.php`

**Interfaces:**

- Consumes: controllers e rotas dos Planos 1 e 2; `CatalogoFontes::grupos()` e `CatalogoFontes::pesosPorPasta()`.
- Produces:
    - `modelos[].campos_count` e `compartilhados[].campos_count` (`int`) na página `meus-certificados/index`.
    - Página `meus-certificados/gerar` (rota `meus-certificados.gerar`, `GET /meus-certificados/{modelo}/gerar`) com as props `modelo: {id, title, imagem_url}`, `campos` (todos os atributos, na ordem do modelo), `fontes` e `pesos_das_fontes`.
    - `POST /modelos/{modelo}/campos` responde `201` + JSON do campo completo, `PATCH .../campos/{campo}` responde `200` + JSON do campo e `DELETE` responde `204`, **quando a requisição espera JSON**; sem JSON continuam redirecionando (`back()`).
    - `geracoes[].ordem_campos: list<string>` na página `historico/index` (nomes dos campos do modelo na ordem dele; `[]` se o modelo foi excluído).
    - A rota `dashboard` passa a redirecionar para `meus-certificados.index`.

- [ ] **Step 1: Escrever os testes que falham**

**1a.** Em `tests/Feature/Modelos/ModeloCertificadoControllerTest.php`, acrescente `use App\Models\CampoCertificado;` aos imports. No teste `lista os modelos próprios e, à parte, os compartilhados com o usuário`, dentro de `->has('modelos.0', fn ($modelo) => $modelo ...)`, acrescente `->where('campos_count', 0)` logo depois de `->where('pode_editar', true)`. E acrescente no fim do arquivo:

```php
it('informa quantos campos cada modelo tem', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $proprio = ModeloCertificado::factory()->for($user)->create();
    CampoCertificado::factory()->for($proprio, 'modelo')->count(2)->create();
    $compartilhado = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($compartilhado, 'modelo')->create();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->create(['email' => 'ana@example.com']);

    $this->actingAs($user)->get(route('meus-certificados.index'))
        ->assertInertia(fn ($page) => $page
            ->where('modelos.0.campos_count', 2)
            ->where('compartilhados.0.campos_count', 1)
            ->etc());
});
```

**1b.** Em `tests/Feature/Certificados/GeracaoCertificadoControllerTest.php`, acrescente no fim:

```php
it('mostra a página de geração com o modelo, os campos em ordem e as fontes', function () {
    $modelo = ModeloCertificado::factory()->create(['title' => 'Batismo']);
    [$primeiro, $segundo] = CampoCertificado::factory()
        ->for($modelo, 'modelo')
        ->count(2)
        ->sequence(
            ['nome' => 'Nome', 'created_at' => now()->subMinute()],
            ['nome' => 'Data', 'created_at' => now()],
        )
        ->create();

    $this->actingAs($modelo->user)->get(route('meus-certificados.gerar', $modelo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/gerar')
            ->where('modelo.id', $modelo->id)
            ->where('modelo.title', 'Batismo')
            ->where('modelo.imagem_url', route('modelos.imagem', $modelo))
            ->has('campos', 2)
            ->where('campos.0.id', $primeiro->id)
            ->where('campos.1.id', $segundo->id)
            ->where('fontes.Sistema.Georgia', 'gelasio')
            ->where('pesos_das_fontes.lato', [100, 300, 400, 700, 900]));
});

it('mostra a página de geração ao leitor compartilhado', function () {
    $modelo = ModeloCertificado::factory()->create();
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)->get(route('meus-certificados.gerar', $modelo))->assertOk();
});

it('responde 404 na página de geração a quem não pode ver o modelo', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('meus-certificados.gerar', $modelo))
        ->assertNotFound();
});

it('redireciona o visitante da página de geração para o login', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->get(route('meus-certificados.gerar', $modelo))->assertRedirect(route('login'));
});
```

**1c.** Em `tests/Feature/Campos/CampoCertificadoControllerTest.php`, acrescente no fim:

```php
it('cria o campo e devolve o JSON completo quando a requisição espera JSON', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->postJson(route('campos.store', $modelo), ['fonte_familia' => 'Cinzel', 'x' => 50, 'y' => 80])
        ->assertCreated()
        ->assertJsonPath('nome', 'Campo 1')
        ->assertJsonPath('fonte_familia', 'Cinzel')
        ->assertJsonPath('x', 50)
        ->assertJsonPath('peso_fonte', 600)
        ->assertJsonStructure([
            'id', 'modelo_id', 'nome', 'label', 'x', 'y', 'cor', 'tamanho_fonte',
            'fonte_familia', 'peso_fonte', 'espacamento_letras', 'centralizado',
        ]);

    expect($modelo->campos()->count())->toBe(1);
});

it('atualiza o campo e devolve o JSON quando a requisição espera JSON', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['x' => 10]);

    $this->actingAs($modelo->user)
        ->patchJson(route('campos.update', [$modelo, $campo]), ['x' => 320])
        ->assertOk()
        ->assertJsonPath('id', $campo->id)
        ->assertJsonPath('x', 320);
});

it('recusa o valor inválido com 422 em JSON', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patchJson(route('campos.update', [$modelo, $campo]), ['x' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['x' => 'A posição não pode ser negativa.']);
});

it('exclui o campo e responde 204 quando a requisição espera JSON', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->deleteJson(route('campos.destroy', [$modelo, $campo]))
        ->assertNoContent();

    $this->assertModelMissing($campo);
});
```

**1d.** Em `tests/Feature/Historico/HistoricoControllerTest.php`, no teste `entrega de cada geração o modelo, os valores e a data`: crie os campos do modelo antes da geração, trocando o bloco de criação por

```php
    $modelo = ModeloCertificado::factory()->create(['title' => 'Crisma 2024']);
    CampoCertificado::factory()->for($modelo, 'modelo')->count(2)->sequence(
        ['nome' => 'Nome', 'created_at' => now()->subMinute()],
        ['nome' => 'Data', 'created_at' => now()],
    )->create();
    $geracao = GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create([
        'modelo_title' => 'Crisma 2024',
        'campos_valores' => ['Nome' => 'Maria'],
    ]);
```

(mantendo a linha `$user = User::factory()->create();` acima) e acrescente `->where('ordem_campos', ['Nome', 'Data'])` depois de `->where('campos_valores', ['Nome' => 'Maria'])`. Acrescente o import `use App\Models\CampoCertificado;`. E acrescente no fim do arquivo:

```php
it('devolve a ordem dos campos vazia quando o modelo da geração foi excluído', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->create();
    GeracaoCertificado::factory()->for($user)->for($modelo, 'modelo')->create(['campos_valores' => ['Nome' => 'Maria']]);
    $modelo->delete();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->where('geracoes.0.modelo_id', null)
            ->where('geracoes.0.ordem_campos', []));
});
```

**1e.** Em `tests/Feature/DashboardTest.php`, troque o método `test_authenticated_users_can_visit_the_dashboard` por:

```php
    public function test_authenticated_users_are_sent_to_their_certificates()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('meus-certificados.index'));
    }
```

- [ ] **Step 2: Rodar e ver falhar**

```bash
php artisan test --compact tests/Feature/Modelos tests/Feature/Campos tests/Feature/Certificados tests/Feature/Historico tests/Feature/DashboardTest.php
```

Expected: FAIL nos testes novos e alterados (`campos_count` ausente, rota `meus-certificados.gerar` inexistente, controller devolve redirect no lugar de JSON, `ordem_campos` ausente, dashboard ainda responde 200).

- [ ] **Step 3: `campos_count` em `ModeloCertificadoController`**

No método `index`, troque `->with(['user:id,name', 'compartilhamentos'])` por `->with(['user:id,name', 'compartilhamentos'])->withCount('campos')`. No `resumo()`, acrescente `'campos_count' => (int) $modelo->campos_count,` depois de `'pode_editar' => ...` e atualize o `@return` do docblock para incluir `campos_count: int`.

- [ ] **Step 4: Respostas JSON em `CampoCertificadoController`**

Acrescente os imports `use Illuminate\Http\JsonResponse;` e `use Illuminate\Http\Response as HttpResponse;`. Substitua os métodos `store`, `update` e `destroy` por:

```php
    /**
     * Cria um campo. O nome, se não vier, é o primeiro "Campo N" livre.
     */
    public function store(SalvarCampoCertificadoRequest $request, ModeloCertificado $modelo): RedirectResponse|JsonResponse
    {
        $campo = $modelo->campos()->create([
            ...$request->validated(),
            'nome' => $request->validated('nome') ?? $this->proximoNome($modelo),
        ])->refresh();

        return $request->expectsJson() ? response()->json($campo, 201) : back();
    }

    public function update(SalvarCampoCertificadoRequest $request, ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse|JsonResponse
    {
        $campo->update($request->validated());

        return $request->expectsJson() ? response()->json($campo) : back();
    }

    public function destroy(Request $request, ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse|HttpResponse
    {
        Gate::authorize('editarCampos', $modelo);

        $campo->delete();

        return $request->expectsJson() ? response()->noContent() : back();
    }
```

(`->refresh()` no `store` traz do banco os valores padrão das colunas que a requisição não enviou.)

- [ ] **Step 5: Página de geração**

Em `app/Http/Controllers/GeracaoCertificadoController.php`, acrescente os imports `use App\Services\CatalogoFontes;`, `use Illuminate\Support\Facades\Gate;`, `use Inertia\Inertia;` e `use Inertia\Response as InertiaResponse;`, e este método antes de `store`:

```php
    /**
     * Mostra a página onde se preenchem os dados e se geram os certificados do modelo.
     */
    public function create(ModeloCertificado $modelo): InertiaResponse
    {
        Gate::authorize('gerar', $modelo);

        return Inertia::render('meus-certificados/gerar', [
            'modelo' => [
                'id' => $modelo->id,
                'title' => $modelo->title,
                'imagem_url' => route('modelos.imagem', $modelo),
            ],
            'campos' => $modelo->campos,
            'fontes' => CatalogoFontes::grupos(),
            'pesos_das_fontes' => CatalogoFontes::pesosPorPasta(),
        ]);
    }
```

Em `routes/web.php`, dentro do grupo `auth` + `verified`, troque a rota do dashboard e acrescente a de geração:

```php
    Route::get('dashboard', fn () => to_route('meus-certificados.index'))->name('dashboard');
```

```php
    Route::get('meus-certificados/{modelo}/gerar', [GeracaoCertificadoController::class, 'create'])->name('meus-certificados.gerar');
```

(coloque a segunda perto da rota `meus-certificados.campos`; o import de `GeracaoCertificadoController` já existe.)

- [ ] **Step 6: `ordem_campos` no histórico**

Substitua o método `index` de `app/Http/Controllers/HistoricoController.php` por (e acrescente `use App\Models\GeracaoCertificado;`):

```php
    public function index(Request $request): Response
    {
        $geracoes = $request->user()->geracoes()
            ->with('modelo.campos:id,modelo_id,nome,created_at')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (GeracaoCertificado $geracao) => [
                'id' => $geracao->id,
                'modelo_id' => $geracao->modelo_id,
                'modelo_title' => $geracao->modelo_title,
                'campos_valores' => $geracao->campos_valores,
                'ordem_campos' => $geracao->modelo?->campos->pluck('nome')->values()->all() ?? [],
                'created_at' => $geracao->created_at,
            ]);

        return Inertia::render('historico/index', ['geracoes' => $geracoes]);
    }
```

O `jsonb` do Postgres não guarda a ordem das chaves de `campos_valores`; `ordem_campos` é o que permite à tela mostrar os valores na ordem do modelo.

- [ ] **Step 7: Rodar e ver passar; suíte inteira**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Modelos tests/Feature/Campos tests/Feature/Certificados tests/Feature/Historico tests/Feature/DashboardTest.php
php artisan test --compact
npm run build && npx tsc --noEmit
```

Expected: tudo verde. O `tsc` pode acusar erros em `resources/js/pages/meus-certificados/index.tsx` ou `historico/index.tsx` se algum tipo ficar incompatível; os tipos mudam na Task 2 e as páginas são reescritas depois, então se isso acontecer ajuste só o necessário para o `tsc` passar.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: ajustes de backend para o frontend (campos_count, página de geração, JSON de campos, ordem do histórico)

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Fundação do frontend (dependências, componentes, tipos e módulos puros)

**Files:**

- Modify: `package.json`, `package-lock.json`
- Create: `resources/js/components/ui/{popover,command,switch,table,alert-dialog,textarea}.tsx` (via shadcn), `resources/js/types/certificados.ts`, `resources/js/lib/{http,fontes,editor,planilha,geracao,historico}.ts`, `resources/js/lib/{http,fontes,editor,planilha,geracao,historico}.test.ts`, `resources/js/hooks/use-fontes-do-catalogo.ts`

**Interfaces:**

- Consumes: rotas `fontes.show` (Plano 2) e `certificados.store`, `campos.*` (Planos 1 e 2) via Wayfinder.
- Produces (usados pelas Tasks 5 a 8):
    - `@/types/certificados`: `Campo`, `GruposFontes`, `PesosDasFontes`, `ModeloBasico`, `Compartilhamento`, `ModeloResumo`, `Geracao`.
    - `@/lib/http`: `ErroHttp` (`status`, `erros`, `esperarSegundos`), `enviarJson<T>(metodo: 'POST' | 'PATCH' | 'DELETE', url: string, corpo?: unknown): Promise<T | null>`, `enviarParaArquivo(url: string, corpo: unknown, nomePadrao: string): Promise<ArquivoBaixado>`, `ArquivoBaixado = { blob: Blob; nome: string; tipo: string }`, `mensagemDoErro(erro: unknown): string`, `lerTokenXsrf(cookies: string): string`, `nomeDoArquivoDoCabecalho(cabecalho: string | null, nomePadrao: string): string`.
    - `@/lib/fontes`: `rotuloDoPeso(peso: number): string`, `pesoMaisProximo(pesos: number[], pedido: number): number`, `pastaDaFamilia(fontes: GruposFontes, familia: string): string | undefined`, `pesosDaFamilia(fontes, pesos, familia, padrao): number[]`, `gerarFontFaces(fontes, pesos, urlDaFonte): string`.
    - `@/lib/editor`: `escalaDoPreview(larguraDoContainer: number, larguraNatural: number): number`, `limitarPosicao(valor: number): number`, `moverCampo(inicio: {x: number; y: number}, deslocamento: {dx: number; dy: number}, escala: number, centralizado: boolean): { x?: number; y: number }`, `valoresDoNovoCampo(ultimo: Campo | undefined)`.
    - `@/lib/planilha`: `lerPlanilha(conteudo: ArrayBuffer, campos: {nome: string}[]): Record<string, string>[]`, `criarPlanilhaModelo(campos: {nome: string}[]): ArrayBuffer`.
    - `@/lib/geracao`: tipos `StatusDaLinha`, `ArquivoGerado`, `LinhaDeGeracao`; funções `valoresVazios`, `novaLinha`, `editarCelula`, `removerLinha`, `marcarGerando`, `marcarPronto`, `marcarErro`, `substituirLinhas`, `urlsDasLinhas`, `gerarCertificado`.
    - `@/lib/historico`: `ordenarValores`, `resumoDosValores`, `formatarData`.
    - `@/hooks/use-fontes-do-catalogo`: `useFontesDoCatalogo(fontes: GruposFontes, pesos: PesosDasFontes): void`.

- [ ] **Step 1: Dependências, componentes shadcn e script de teste**

```bash
npm install https://cdn.sheetjs.com/xlsx-0.20.3/xlsx-0.20.3.tgz
npx shadcn@latest add popover command switch table alert-dialog textarea --yes
ls resources/js/components/ui | tr '\n' ' '
```

Expected: `xlsx` aparece em `package.json` com o endereço do CDN (versão `0.20.3`); os seis arquivos novos existem em `resources/js/components/ui/`. Se o `shadcn` reclamar de conflito de peer dependencies do `cmdk` com o React 19, repita com `--force` na instalação (o CLI pergunta como prosseguir; em modo não interativo use `npm install cmdk --legacy-peer-deps` e rode o `add` de novo).

Em `package.json`, na seção `scripts`, acrescente `"test": "vp test run"` (depois de `"check:fix"`, mantendo a vírgula). Confira:

```bash
npx vp test run 2>&1 | tail -4
```

Expected: `No test files found` (ainda não há testes) sem erro de configuração.

- [ ] **Step 2: Tipos compartilhados**

`resources/js/types/certificados.ts`:

```ts
export type Campo = {
    id: string;
    modelo_id: string;
    nome: string;
    label: string | null;
    x: number;
    y: number;
    cor: string;
    tamanho_fonte: number;
    fonte_familia: string;
    peso_fonte: number;
    espacamento_letras: number;
    centralizado: boolean;
};

/** Grupo => (família exibida => pasta dos arquivos de fonte). */
export type GruposFontes = Record<string, Record<string, string>>;

/** Pasta => pesos que têm arquivo. */
export type PesosDasFontes = Record<string, number[]>;

export type ModeloBasico = {
    id: string;
    title: string;
    imagem_url: string;
};

export type Compartilhamento = {
    id: string;
    email: string;
    pode_editar: boolean;
};

export type ModeloResumo = {
    id: string;
    title: string;
    description: string | null;
    imagem_url: string;
    dono_nome: string;
    eh_dono: boolean;
    pode_editar: boolean;
    campos_count: number;
    compartilhamentos: Compartilhamento[];
};

export type Geracao = {
    id: string;
    modelo_id: string | null;
    modelo_title: string;
    campos_valores: Record<string, string>;
    ordem_campos: string[];
    created_at: string;
};
```

- [ ] **Step 3: Cliente HTTP (`lib/http.ts`) — teste primeiro**

`resources/js/lib/http.test.ts`:

```ts
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    enviarJson,
    enviarParaArquivo,
    ErroHttp,
    lerTokenXsrf,
    mensagemDoErro,
    nomeDoArquivoDoCabecalho,
} from './http';

afterEach(() => {
    vi.unstubAllGlobals();
});

function respostaJson(corpo: unknown, init: ResponseInit = {}): Response {
    return new Response(JSON.stringify(corpo), {
        headers: { 'Content-Type': 'application/json' },
        ...init,
    });
}

function fingirFetch(resposta: Response) {
    const falso = vi.fn().mockResolvedValue(resposta);
    vi.stubGlobal('fetch', falso);
    vi.stubGlobal('document', { cookie: 'a=1; XSRF-TOKEN=tok%3D; b=2' });

    return falso;
}

describe('lerTokenXsrf', () => {
    it('lê e decodifica o token do cookie', () => {
        expect(lerTokenXsrf('a=1; XSRF-TOKEN=abc%3D%3D; b=2')).toBe('abc==');
    });

    it('devolve texto vazio quando não há cookie do token', () => {
        expect(lerTokenXsrf('a=1; b=2')).toBe('');
    });
});

describe('nomeDoArquivoDoCabecalho', () => {
    it('prefere o nome em UTF-8', () => {
        const cabecalho =
            'attachment; filename="Batismo Joao.png"; filename*=utf-8\'\'Batismo%20Jo%C3%A3o.png';

        expect(nomeDoArquivoDoCabecalho(cabecalho, 'x.png')).toBe(
            'Batismo João.png',
        );
    });

    it('usa o nome entre aspas quando não há UTF-8', () => {
        expect(
            nomeDoArquivoDoCabecalho('attachment; filename="a b.png"', 'x.png'),
        ).toBe('a b.png');
    });

    it('usa o nome padrão quando não há cabeçalho', () => {
        expect(nomeDoArquivoDoCabecalho(null, 'padrao.png')).toBe('padrao.png');
    });
});

describe('enviarJson', () => {
    it('envia o corpo como JSON com o token CSRF e devolve o JSON da resposta', async () => {
        const fetchFalso = fingirFetch(
            respostaJson({ id: '1' }, { status: 201 }),
        );

        const resultado = await enviarJson<{ id: string }>('POST', '/campos', {
            x: 5,
        });

        const [url, opcoes] = fetchFalso.mock.calls[0];
        expect(url).toBe('/campos');
        expect(opcoes.method).toBe('POST');
        expect(opcoes.credentials).toBe('same-origin');
        expect(opcoes.headers).toMatchObject({
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': 'tok=',
        });
        expect(opcoes.body).toBe('{"x":5}');
        expect(resultado).toEqual({ id: '1' });
    });

    it('devolve null quando a resposta é 204', async () => {
        fingirFetch(new Response(null, { status: 204 }));

        expect(await enviarJson('DELETE', '/campos/1')).toBeNull();
    });

    it('lança ErroHttp com a mensagem e os erros de validação do servidor', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Inválido.', errors: { x: ['Negativo.'] } },
                { status: 422 },
            ),
        );

        const erro = await enviarJson('PATCH', '/campos/1', { x: -1 }).catch(
            (e: unknown) => e,
        );

        expect(erro).toBeInstanceOf(ErroHttp);
        expect(erro).toMatchObject({
            status: 422,
            message: 'Inválido.',
            erros: { x: ['Negativo.'] },
        });
    });

    it('guarda o Retry-After de uma resposta 429', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Too Many Attempts.' },
                { status: 429, headers: { 'Retry-After': '7' } },
            ),
        );

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({ status: 429, esperarSegundos: 7 });
    });

    it('usa uma mensagem padrão quando o erro não vem em JSON', async () => {
        fingirFetch(new Response('<html>erro</html>', { status: 500 }));

        const erro = await enviarJson('POST', '/x').catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 500,
            message: 'Erro no servidor. Tente novamente.',
        });
    });
});

describe('enviarParaArquivo', () => {
    it('devolve o arquivo com o nome do cabeçalho', async () => {
        fingirFetch(
            new Response(new Blob(['png'], { type: 'image/png' }), {
                headers: {
                    'Content-Type': 'image/png',
                    'Content-Disposition':
                        'attachment; filename="a.png"; filename*=utf-8\'\'Jo%C3%A3o.png',
                },
            }),
        );

        const arquivo = await enviarParaArquivo(
            '/gerar',
            { valores: {} },
            'padrao.png',
        );

        expect(arquivo.nome).toBe('João.png');
        expect(arquivo.tipo).toBe('image/png');
        expect(arquivo.blob.size).toBe(3);
    });

    it('lança ErroHttp com a mensagem do servidor quando a geração falha', async () => {
        fingirFetch(
            respostaJson(
                { message: 'Não foi possível ler a imagem do modelo.' },
                { status: 422 },
            ),
        );

        const erro = await enviarParaArquivo(
            '/gerar',
            { valores: {} },
            'x.png',
        ).catch((e: unknown) => e);

        expect(erro).toMatchObject({
            status: 422,
            message: 'Não foi possível ler a imagem do modelo.',
        });
    });
});

describe('mensagemDoErro', () => {
    it('devolve a mensagem de um ErroHttp', () => {
        expect(mensagemDoErro(new ErroHttp(422, 'Inválido.'))).toBe(
            'Inválido.',
        );
    });

    it('trata falha de conexão', () => {
        expect(mensagemDoErro(new TypeError('Failed to fetch'))).toBe(
            'Não foi possível conectar ao servidor. Verifique sua conexão.',
        );
    });

    it('trata qualquer outro erro', () => {
        expect(mensagemDoErro('x')).toBe('Não foi possível concluir a ação.');
    });
});
```

Rode `npx vp test run resources/js/lib/http.test.ts` e veja **falhar** (módulo `./http` inexistente). Depois crie `resources/js/lib/http.ts`:

```ts
export class ErroHttp extends Error {
    readonly status: number;

    readonly erros: Record<string, string[]>;

    readonly esperarSegundos: number | null;

    constructor(
        status: number,
        mensagem: string,
        erros: Record<string, string[]> = {},
        esperarSegundos: number | null = null,
    ) {
        super(mensagem);
        this.name = 'ErroHttp';
        this.status = status;
        this.erros = erros;
        this.esperarSegundos = esperarSegundos;
    }
}

export type ArquivoBaixado = {
    blob: Blob;
    nome: string;
    tipo: string;
};

/** Lê o token CSRF que o Laravel grava no cookie XSRF-TOKEN. */
export function lerTokenXsrf(cookies: string): string {
    const par = cookies
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return par ? decodeURIComponent(par.slice('XSRF-TOKEN='.length)) : '';
}

/** Nome do arquivo do `Content-Disposition`: prefere `filename*` (UTF-8) e cai para `filename`. */
export function nomeDoArquivoDoCabecalho(
    cabecalho: string | null,
    nomePadrao: string,
): string {
    if (cabecalho === null) {
        return nomePadrao;
    }

    const utf8 = cabecalho.match(/filename\*=utf-8''([^;]+)/i);

    if (utf8) {
        return decodeURIComponent(utf8[1]);
    }

    const simples = cabecalho.match(/filename="?([^";]+)"?/i);

    return simples ? simples[1] : nomePadrao;
}

function mensagemPadrao(status: number): string {
    if (status === 419) {
        return 'Sua sessão expirou. Recarregue a página.';
    }

    if (status === 429) {
        return 'Muitas tentativas. Aguarde alguns segundos.';
    }

    if (status === 403) {
        return 'Você não tem permissão para isso.';
    }

    if (status === 404) {
        return 'Não encontrado.';
    }

    return status >= 500
        ? 'Erro no servidor. Tente novamente.'
        : 'Não foi possível concluir a ação.';
}

export function mensagemDoErro(erro: unknown): string {
    if (erro instanceof ErroHttp) {
        return erro.message;
    }

    if (erro instanceof TypeError) {
        return 'Não foi possível conectar ao servidor. Verifique sua conexão.';
    }

    return 'Não foi possível concluir a ação.';
}

function cabecalhos(
    extras: Record<string, string> = {},
): Record<string, string> {
    const cookies = typeof document === 'undefined' ? '' : document.cookie;

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': lerTokenXsrf(cookies),
        ...extras,
    };
}

async function erroDaResposta(resposta: Response): Promise<ErroHttp> {
    let dados: { message?: string; errors?: Record<string, string[]> } = {};

    try {
        dados = await resposta.json();
    } catch {
        dados = {};
    }

    const espera = resposta.headers.get('Retry-After');

    return new ErroHttp(
        resposta.status,
        dados.message ?? mensagemPadrao(resposta.status),
        dados.errors ?? {},
        espera === null ? null : Number(espera),
    );
}

/** Envia JSON ao servidor (com CSRF) e devolve o JSON da resposta; `null` para 204. */
export async function enviarJson<T>(
    metodo: 'POST' | 'PATCH' | 'DELETE',
    url: string,
    corpo?: unknown,
): Promise<T | null> {
    const resposta = await fetch(url, {
        method: metodo,
        credentials: 'same-origin',
        headers: cabecalhos(
            corpo === undefined ? {} : { 'Content-Type': 'application/json' },
        ),
        body: corpo === undefined ? undefined : JSON.stringify(corpo),
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    return resposta.status === 204 ? null : ((await resposta.json()) as T);
}

/** Envia JSON ao servidor e devolve a resposta como arquivo (o PNG do certificado). */
export async function enviarParaArquivo(
    url: string,
    corpo: unknown,
    nomePadrao: string,
): Promise<ArquivoBaixado> {
    const resposta = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: cabecalhos({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(corpo),
    });

    if (!resposta.ok) {
        throw await erroDaResposta(resposta);
    }

    return {
        blob: await resposta.blob(),
        nome: nomeDoArquivoDoCabecalho(
            resposta.headers.get('Content-Disposition'),
            nomePadrao,
        ),
        tipo:
            resposta.headers.get('Content-Type')?.split(';')[0].trim() ??
            'application/octet-stream',
    };
}
```

Rode `npx vp test run resources/js/lib/http.test.ts` e veja **passar**.

- [ ] **Step 4: Fontes (`lib/fontes.ts`) — teste primeiro**

`resources/js/lib/fontes.test.ts`:

```ts
import { describe, expect, it } from 'vite-plus/test';
import {
    gerarFontFaces,
    pastaDaFamilia,
    pesoMaisProximo,
    pesosDaFamilia,
    rotuloDoPeso,
} from './fontes';

const fontes = {
    Serif: { Lora: 'lora', 'Dancing Script': 'dancing-script' },
    Sistema: { Georgia: 'gelasio' },
};

describe('pesoMaisProximo', () => {
    it.each([
        [[400, 700], 450, 400],
        [[400, 700], 650, 700],
        [[400, 700], 550, 700],
        [[400, 700], 100, 400],
        [[400, 700], 900, 700],
        [[400, 700], 700, 700],
        [[300], 600, 300],
    ])(
        'com os pesos %j e o pedido %d devolve %d (no empate vence o mais pesado)',
        (pesos, pedido, esperado) => {
            expect(pesoMaisProximo(pesos, pedido)).toBe(esperado);
        },
    );

    it('devolve o pedido quando não há nenhum peso', () => {
        expect(pesoMaisProximo([], 600)).toBe(600);
    });
});

describe('pastaDaFamilia', () => {
    it('acha a pasta em qualquer grupo', () => {
        expect(pastaDaFamilia(fontes, 'Georgia')).toBe('gelasio');
        expect(pastaDaFamilia(fontes, 'Dancing Script')).toBe('dancing-script');
    });

    it('devolve undefined para família fora do catálogo', () => {
        expect(pastaDaFamilia(fontes, 'Comic Sans MS')).toBeUndefined();
    });
});

describe('pesosDaFamilia', () => {
    it('devolve os pesos da pasta da família', () => {
        expect(
            pesosDaFamilia(fontes, { lora: [400, 700] }, 'Lora', 600),
        ).toEqual([400, 700]);
    });

    it('cai para o peso padrão quando a pasta não tem arquivos', () => {
        expect(pesosDaFamilia(fontes, { lora: [] }, 'Lora', 600)).toEqual([
            600,
        ]);
        expect(pesosDaFamilia(fontes, {}, 'Comic Sans MS', 600)).toEqual([600]);
    });
});

describe('rotuloDoPeso', () => {
    it('nomeia os pesos conhecidos e devolve o número para os demais', () => {
        expect(rotuloDoPeso(600)).toBe('Semi Bold');
        expect(rotuloDoPeso(450)).toBe('450');
    });
});

describe('gerarFontFaces', () => {
    const url = (pasta: string, peso: number) => `/fontes/${pasta}/${peso}.ttf`;

    it('gera um @font-face por peso que tem arquivo', () => {
        const css = gerarFontFaces(
            fontes,
            { lora: [400, 700], gelasio: [600] },
            url,
        );

        expect(css).toContain(
            '@font-face{font-family:"Lora";font-weight:400;font-style:normal;font-display:swap;src:url("/fontes/lora/400.ttf") format("truetype");}',
        );
        expect(css).toContain('font-family:"Lora";font-weight:700;');
        expect(css).toContain('font-family:"Georgia";font-weight:600;');
    });

    it('não gera nada para famílias sem arquivos', () => {
        const css = gerarFontFaces(
            fontes,
            { lora: [400], 'dancing-script': [] },
            url,
        );

        expect(css).not.toContain('Dancing Script');
        expect(css.match(/@font-face/g)).toHaveLength(1);
    });

    it('devolve texto vazio quando não há pesos', () => {
        expect(gerarFontFaces(fontes, {}, url)).toBe('');
    });
});
```

Veja **falhar**, depois crie `resources/js/lib/fontes.ts`:

```ts
import type { GruposFontes, PesosDasFontes } from '@/types/certificados';

const ROTULOS_DE_PESO: Record<number, string> = {
    100: 'Thin',
    200: 'Extra Light',
    300: 'Light',
    400: 'Regular',
    500: 'Medium',
    600: 'Semi Bold',
    700: 'Bold',
    800: 'Extra Bold',
    900: 'Black',
};

export function rotuloDoPeso(peso: number): string {
    return ROTULOS_DE_PESO[peso] ?? String(peso);
}

/** Mesma regra do servidor: o peso mais próximo; no empate, o mais pesado. */
export function pesoMaisProximo(pesos: number[], pedido: number): number {
    return pesos.reduce((melhor, peso) => {
        const distancia = Math.abs(peso - pedido);
        const melhorDistancia = Math.abs(melhor - pedido);

        return distancia < melhorDistancia ||
            (distancia === melhorDistancia && peso > melhor)
            ? peso
            : melhor;
    }, pesos[0] ?? pedido);
}

export function pastaDaFamilia(
    fontes: GruposFontes,
    familia: string,
): string | undefined {
    for (const familias of Object.values(fontes)) {
        if (familia in familias) {
            return familias[familia];
        }
    }

    return undefined;
}

/** Pesos que a família tem; se a pasta não tem arquivos, só o peso padrão. */
export function pesosDaFamilia(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
    familia: string,
    padrao: number,
): number[] {
    const pasta = pastaDaFamilia(fontes, familia);
    const disponiveis = pasta ? (pesos[pasta] ?? []) : [];

    return disponiveis.length > 0 ? disponiveis : [padrao];
}

/** CSS com um @font-face por peso que tem arquivo, apontando para as URLs do próprio servidor. */
export function gerarFontFaces(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
    urlDaFonte: (pasta: string, peso: number) => string,
): string {
    const regras: string[] = [];

    for (const familias of Object.values(fontes)) {
        for (const [familia, pasta] of Object.entries(familias)) {
            for (const peso of pesos[pasta] ?? []) {
                regras.push(
                    `@font-face{font-family:"${familia}";font-weight:${peso};font-style:normal;font-display:swap;src:url("${urlDaFonte(pasta, peso)}") format("truetype");}`,
                );
            }
        }
    }

    return regras.join('\n');
}
```

Veja **passar**.

- [ ] **Step 5: Geometria do editor (`lib/editor.ts`) — teste primeiro**

`resources/js/lib/editor.test.ts`:

```ts
import { describe, expect, it } from 'vite-plus/test';
import {
    escalaDoPreview,
    limitarPosicao,
    moverCampo,
    valoresDoNovoCampo,
} from './editor';

describe('escalaDoPreview', () => {
    it('divide a largura mostrada pela largura natural da imagem', () => {
        expect(escalaDoPreview(500, 2000)).toBe(0.25);
    });

    it('usa 1 quando ainda não há medidas', () => {
        expect(escalaDoPreview(0, 2000)).toBe(1);
        expect(escalaDoPreview(500, 0)).toBe(1);
    });
});

describe('limitarPosicao', () => {
    it('arredonda e mantém a posição entre 0 e 10000', () => {
        expect(limitarPosicao(10.6)).toBe(11);
        expect(limitarPosicao(-5)).toBe(0);
        expect(limitarPosicao(20000)).toBe(10000);
    });
});

describe('moverCampo', () => {
    it('converte o deslocamento da tela para pixels da imagem original', () => {
        expect(
            moverCampo({ x: 100, y: 200 }, { dx: 50, dy: -25 }, 0.5, false),
        ).toEqual({ x: 200, y: 150 });
    });

    it('só move na vertical quando o campo é centralizado', () => {
        expect(
            moverCampo({ x: 100, y: 200 }, { dx: 50, dy: 10 }, 0.5, true),
        ).toEqual({ y: 220 });
    });

    it('não deixa o campo sair da imagem pela esquerda ou pelo topo', () => {
        expect(
            moverCampo({ x: 10, y: 10 }, { dx: -500, dy: -500 }, 1, false),
        ).toEqual({ x: 0, y: 0 });
    });
});

describe('valoresDoNovoCampo', () => {
    it('herda o estilo do último campo e começa em 50, 50', () => {
        const ultimo = {
            id: 'a',
            modelo_id: 'm',
            nome: 'Nome',
            label: null,
            x: 10,
            y: 20,
            cor: '#ffffff',
            tamanho_fonte: 80,
            fonte_familia: 'Cinzel',
            peso_fonte: 700,
            espacamento_letras: 2,
            centralizado: true,
        };

        expect(valoresDoNovoCampo(ultimo)).toEqual({
            x: 50,
            y: 50,
            cor: '#ffffff',
            tamanho_fonte: 80,
            fonte_familia: 'Cinzel',
            peso_fonte: 700,
            espacamento_letras: 2,
            centralizado: true,
        });
    });

    it('usa os padrões quando não há campo anterior', () => {
        expect(valoresDoNovoCampo(undefined)).toEqual({
            x: 50,
            y: 50,
            cor: '#000000',
            tamanho_fonte: 24,
            fonte_familia: 'Georgia',
            peso_fonte: 600,
            espacamento_letras: 0,
            centralizado: false,
        });
    });
});
```

Veja **falhar**, depois crie `resources/js/lib/editor.ts`:

```ts
import type { Campo } from '@/types/certificados';

export const POSICAO_MAXIMA = 10000;

export function escalaDoPreview(
    larguraDoContainer: number,
    larguraNatural: number,
): number {
    return larguraNatural > 0 && larguraDoContainer > 0
        ? larguraDoContainer / larguraNatural
        : 1;
}

export function limitarPosicao(valor: number): number {
    return Math.min(POSICAO_MAXIMA, Math.max(0, Math.round(valor)));
}

/** Nova posição, em pixels da imagem original, depois de arrastar o campo na tela. */
export function moverCampo(
    inicio: { x: number; y: number },
    deslocamento: { dx: number; dy: number },
    escala: number,
    centralizado: boolean,
): { x?: number; y: number } {
    const y = limitarPosicao(inicio.y + deslocamento.dy / escala);

    return centralizado
        ? { y }
        : { x: limitarPosicao(inicio.x + deslocamento.dx / escala), y };
}

/** Atributos de um campo novo: herda o estilo do último campo e começa em 50, 50. */
export function valoresDoNovoCampo(ultimo: Campo | undefined) {
    return {
        x: 50,
        y: 50,
        cor: ultimo?.cor ?? '#000000',
        tamanho_fonte: ultimo?.tamanho_fonte ?? 24,
        fonte_familia: ultimo?.fonte_familia ?? 'Georgia',
        peso_fonte: ultimo?.peso_fonte ?? 600,
        espacamento_letras: ultimo?.espacamento_letras ?? 0,
        centralizado: ultimo?.centralizado ?? false,
    };
}
```

Veja **passar**.

- [ ] **Step 6: Planilha (`lib/planilha.ts`) — teste primeiro**

`resources/js/lib/planilha.test.ts`:

```ts
import * as XLSX from 'xlsx';
import { describe, expect, it } from 'vite-plus/test';
import { criarPlanilhaModelo, lerPlanilha } from './planilha';

const campos = [{ nome: 'Nome' }, { nome: 'Data' }];

function planilha(linhas: unknown[][]): ArrayBuffer {
    const aba = XLSX.utils.aoa_to_sheet(linhas, { cellDates: true });
    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, aba, 'Dados');

    return XLSX.write(livro, {
        type: 'array',
        bookType: 'xlsx',
        cellDates: true,
    }) as ArrayBuffer;
}

describe('lerPlanilha', () => {
    it('mapeia as colunas pelo nome dos campos, ignora colunas a mais e preenche as que faltam com texto vazio', () => {
        const conteudo = planilha([
            ['Nome', 'Outra'],
            ['Maria', 'x'],
            ['João', 'y'],
        ]);

        expect(lerPlanilha(conteudo, campos)).toEqual([
            { Nome: 'Maria', Data: '' },
            { Nome: 'João', Data: '' },
        ]);
    });

    it('devolve números como texto', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', 2024],
        ]);

        expect(lerPlanilha(conteudo, campos)[0].Data).toBe('2024');
    });

    it('formata datas como dd/mm/aaaa', () => {
        const conteudo = planilha([
            ['Nome', 'Data'],
            ['Maria', new Date(2026, 9, 9, 12, 0, 0)],
        ]);

        expect(lerPlanilha(conteudo, campos)[0].Data).toBe('09/10/2026');
    });

    it('devolve lista vazia quando há só o cabeçalho', () => {
        expect(lerPlanilha(planilha([['Nome', 'Data']]), campos)).toEqual([]);
    });
});

describe('criarPlanilhaModelo', () => {
    it('cria uma aba "Dados" com os nomes dos campos como cabeçalho', () => {
        const livro = XLSX.read(criarPlanilhaModelo(campos), { type: 'array' });

        expect(livro.SheetNames).toEqual(['Dados']);
        expect(
            XLSX.utils.sheet_to_json(livro.Sheets.Dados, { header: 1 }),
        ).toEqual([['Nome', 'Data']]);
    });
});
```

Veja **falhar**, depois crie `resources/js/lib/planilha.ts`:

```ts
import * as XLSX from 'xlsx';

/** Lê a primeira aba e devolve uma linha por registro, com uma chave por campo do modelo. */
export function lerPlanilha(
    conteudo: ArrayBuffer,
    campos: { nome: string }[],
): Record<string, string>[] {
    const livro = XLSX.read(conteudo, { type: 'array' });
    const aba = livro.Sheets[livro.SheetNames[0]];

    if (!aba) {
        return [];
    }

    const linhas = XLSX.utils.sheet_to_json<Record<string, unknown>>(aba, {
        raw: false,
        dateNF: 'dd/mm/yyyy',
        defval: '',
    });

    return linhas.map((linha) =>
        Object.fromEntries(
            campos.map((campo) => [
                campo.nome,
                String(linha[campo.nome] ?? ''),
            ]),
        ),
    );
}

/** Planilha vazia com os nomes dos campos como cabeçalho, para o usuário preencher. */
export function criarPlanilhaModelo(campos: { nome: string }[]): ArrayBuffer {
    const cabecalhos = campos.map((campo) => campo.nome);
    const aba = XLSX.utils.aoa_to_sheet([cabecalhos]);
    aba['!cols'] = cabecalhos.map((nome) => ({
        wch: Math.max(nome.length + 2, 20),
    }));

    const livro = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(livro, aba, 'Dados');

    return XLSX.write(livro, {
        type: 'array',
        bookType: 'xlsx',
    }) as ArrayBuffer;
}
```

Veja **passar**. Se o teste de data falhar porque o SheetJS devolve outro formato (por exemplo `10/9/26`), a opção `dateNF` não foi aplicada: leia o valor real, e troque a formatação por uma função que converta o número serial do Excel com `XLSX.SSF.parse_date_code` e monte `dd/mm/aaaa`; mantenha o teste.

- [ ] **Step 7: Estado da geração (`lib/geracao.ts`) — teste primeiro**

`resources/js/lib/geracao.test.ts`:

```ts
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import {
    editarCelula,
    gerarCertificado,
    marcarErro,
    marcarGerando,
    marcarPronto,
    novaLinha,
    removerLinha,
    substituirLinhas,
    urlsDasLinhas,
    valoresVazios,
} from './geracao';

const campos = [{ nome: 'Nome' }, { nome: 'Data' }];
const arquivo = { url: 'blob:1', nome: 'a.png', tipo: 'image/png' };

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('linhas de geração', () => {
    it('cria valores vazios para cada campo', () => {
        expect(valoresVazios(campos)).toEqual({ Nome: '', Data: '' });
    });

    it('cria uma linha ociosa, sem arquivo nem erro', () => {
        expect(novaLinha(1, campos)).toEqual({
            id: 1,
            valores: { Nome: '', Data: '' },
            status: 'ocioso',
            arquivo: null,
            erro: null,
        });
    });

    it('edita só a célula pedida, sem tocar nas outras linhas', () => {
        const linhas = [novaLinha(1, campos), novaLinha(2, campos)];

        const novas = editarCelula(linhas, 1, 'Nome', 'Maria');

        expect(novas[0].valores.Nome).toBe('Maria');
        expect(novas[1]).toBe(linhas[1]);
    });

    it('volta ao estado ocioso, sem arquivo, quando se edita uma linha pronta ou com erro', () => {
        const pronta = marcarPronto([novaLinha(1, campos)], 1, arquivo);
        const comErro = marcarErro([novaLinha(2, campos)], 2, 'falhou');

        expect(editarCelula(pronta, 1, 'Nome', 'x')[0]).toMatchObject({
            status: 'ocioso',
            arquivo: null,
            erro: null,
        });
        expect(editarCelula(comErro, 2, 'Nome', 'x')[0]).toMatchObject({
            status: 'ocioso',
            erro: null,
        });
    });

    it('marca a linha como gerando, pronta ou com erro', () => {
        const linhas = [novaLinha(1, campos)];

        expect(marcarGerando(linhas, 1)[0]).toMatchObject({
            status: 'gerando',
            erro: null,
        });
        expect(marcarPronto(linhas, 1, arquivo)[0]).toMatchObject({
            status: 'pronto',
            arquivo,
        });
        expect(marcarErro(linhas, 1, 'falhou')[0]).toMatchObject({
            status: 'erro',
            erro: 'falhou',
        });
    });

    it('remove a linha pedida', () => {
        const linhas = [novaLinha(1, campos), novaLinha(2, campos)];

        expect(removerLinha(linhas, 1).map((l) => l.id)).toEqual([2]);
    });

    it('substitui as linhas pelas importadas, numerando a partir do id dado', () => {
        const importadas = [
            { Nome: 'Maria', Data: '01/01/2026' },
            { Nome: 'João', Data: '' },
        ];

        const linhas = substituirLinhas(importadas, 10);

        expect(linhas.map((l) => l.id)).toEqual([10, 11]);
        expect(linhas[0]).toMatchObject({
            valores: importadas[0],
            status: 'ocioso',
        });
    });

    it('lista as URLs dos arquivos gerados, para revogá-las', () => {
        const linhas = [
            marcarPronto([novaLinha(1, campos)], 1, arquivo)[0],
            novaLinha(2, campos),
        ];

        expect(urlsDasLinhas(linhas)).toEqual(['blob:1']);
    });
});

describe('gerarCertificado', () => {
    function fingir(...respostas: Response[]) {
        const falso = vi.fn();
        respostas.forEach((resposta) => falso.mockResolvedValueOnce(resposta));
        vi.stubGlobal('fetch', falso);
        vi.stubGlobal('document', { cookie: '' });

        return falso;
    }

    const png = () =>
        new Response(new Blob(['png'], { type: 'image/png' }), {
            headers: {
                'Content-Type': 'image/png',
                'Content-Disposition': 'attachment; filename="a.png"',
            },
        });

    const limite = () =>
        new Response(JSON.stringify({ message: 'Too Many Attempts.' }), {
            status: 429,
            headers: { 'Retry-After': '7', 'Content-Type': 'application/json' },
        });

    it('envia os valores e devolve o arquivo', async () => {
        const falso = fingir(png());

        const resultado = await gerarCertificado(
            '/gerar',
            { Nome: 'Maria' },
            { nomePadrao: 'x.png' },
        );

        expect(JSON.parse(falso.mock.calls[0][1].body)).toEqual({
            valores: { Nome: 'Maria' },
        });
        expect(resultado.nome).toBe('a.png');
    });

    it('não tenta de novo quando o erro não é 429', async () => {
        const falso = fingir(
            new Response(JSON.stringify({ message: 'Imagem ruim.' }), {
                status: 422,
            }),
        );

        await expect(
            gerarCertificado('/gerar', {}, { nomePadrao: 'x.png' }),
        ).rejects.toMatchObject({ status: 422 });
        expect(falso).toHaveBeenCalledTimes(1);
    });

    it('espera o Retry-After e tenta de novo quando o servidor responde 429', async () => {
        const falso = fingir(limite(), png());
        const esperar = vi.fn().mockResolvedValue(undefined);

        const resultado = await gerarCertificado(
            '/gerar',
            {},
            { nomePadrao: 'x.png', esperar },
        );

        expect(esperar).toHaveBeenCalledWith(7000);
        expect(falso).toHaveBeenCalledTimes(2);
        expect(resultado.nome).toBe('a.png');
    });

    it('desiste depois do número máximo de tentativas', async () => {
        const falso = fingir(limite(), limite(), limite());
        const esperar = vi.fn().mockResolvedValue(undefined);

        await expect(
            gerarCertificado(
                '/gerar',
                {},
                { nomePadrao: 'x.png', esperar, tentativas: 3 },
            ),
        ).rejects.toMatchObject({ status: 429 });
        expect(falso).toHaveBeenCalledTimes(3);
        expect(esperar).toHaveBeenCalledTimes(2);
    });
});
```

Veja **falhar**, depois crie `resources/js/lib/geracao.ts`:

```ts
import { enviarParaArquivo, ErroHttp, type ArquivoBaixado } from './http';

export type StatusDaLinha = 'ocioso' | 'gerando' | 'pronto' | 'erro';

export type ArquivoGerado = {
    url: string;
    nome: string;
    tipo: string;
};

export type LinhaDeGeracao = {
    id: number;
    valores: Record<string, string>;
    status: StatusDaLinha;
    arquivo: ArquivoGerado | null;
    erro: string | null;
};

export function valoresVazios(
    campos: { nome: string }[],
): Record<string, string> {
    return Object.fromEntries(campos.map((campo) => [campo.nome, '']));
}

export function novaLinha(
    id: number,
    campos: { nome: string }[],
    valores?: Record<string, string>,
): LinhaDeGeracao {
    return {
        id,
        valores: valores ?? valoresVazios(campos),
        status: 'ocioso',
        arquivo: null,
        erro: null,
    };
}

function alterar(
    linhas: LinhaDeGeracao[],
    id: number,
    mudar: (linha: LinhaDeGeracao) => LinhaDeGeracao,
): LinhaDeGeracao[] {
    return linhas.map((linha) => (linha.id === id ? mudar(linha) : linha));
}

/** Editar uma linha já gerada (ou com erro) a deixa pronta para gerar de novo. */
export function editarCelula(
    linhas: LinhaDeGeracao[],
    id: number,
    nome: string,
    valor: string,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        valores: { ...linha.valores, [nome]: valor },
        ...(linha.status === 'pronto' || linha.status === 'erro'
            ? { status: 'ocioso' as const, arquivo: null, erro: null }
            : {}),
    }));
}

export function removerLinha(
    linhas: LinhaDeGeracao[],
    id: number,
): LinhaDeGeracao[] {
    return linhas.filter((linha) => linha.id !== id);
}

export function marcarGerando(
    linhas: LinhaDeGeracao[],
    id: number,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'gerando',
        erro: null,
    }));
}

export function marcarPronto(
    linhas: LinhaDeGeracao[],
    id: number,
    arquivo: ArquivoGerado,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'pronto',
        arquivo,
        erro: null,
    }));
}

export function marcarErro(
    linhas: LinhaDeGeracao[],
    id: number,
    mensagem: string,
): LinhaDeGeracao[] {
    return alterar(linhas, id, (linha) => ({
        ...linha,
        status: 'erro',
        arquivo: null,
        erro: mensagem,
    }));
}

/** Linhas novas a partir de uma planilha importada, numeradas a partir de `primeiroId`. */
export function substituirLinhas(
    importadas: Record<string, string>[],
    primeiroId: number,
): LinhaDeGeracao[] {
    return importadas.map((valores, indice) =>
        novaLinha(primeiroId + indice, [], valores),
    );
}

export function urlsDasLinhas(linhas: LinhaDeGeracao[]): string[] {
    return linhas.flatMap((linha) =>
        linha.arquivo ? [linha.arquivo.url] : [],
    );
}

export type OpcoesDeGeracao = {
    nomePadrao: string;
    tentativas?: number;
    esperar?: (milissegundos: number) => Promise<void>;
};

/**
 * Pede ao servidor o PNG de uma linha. Se o limite por minuto estourar (429), espera o tempo que o
 * servidor mandar e tenta de novo, até `tentativas` vezes (padrão 4).
 */
export async function gerarCertificado(
    url: string,
    valores: Record<string, string>,
    opcoes: OpcoesDeGeracao,
): Promise<ArquivoBaixado> {
    const maximo = opcoes.tentativas ?? 4;
    const esperar =
        opcoes.esperar ??
        ((ms: number) =>
            new Promise<void>((resolver) => setTimeout(resolver, ms)));

    for (let tentativa = 1; ; tentativa++) {
        try {
            return await enviarParaArquivo(url, { valores }, opcoes.nomePadrao);
        } catch (erro) {
            if (
                !(erro instanceof ErroHttp) ||
                erro.status !== 429 ||
                tentativa >= maximo
            ) {
                throw erro;
            }

            await esperar((erro.esperarSegundos ?? 5) * 1000);
        }
    }
}
```

Veja **passar**.

- [ ] **Step 8: Histórico (`lib/historico.ts`) — teste primeiro**

`resources/js/lib/historico.test.ts`:

```ts
import { describe, expect, it } from 'vite-plus/test';
import { formatarData, ordenarValores, resumoDosValores } from './historico';

describe('ordenarValores', () => {
    it('põe primeiro os campos na ordem do modelo e depois os demais, em ordem alfabética', () => {
        const valores = { Zeta: '3', Data: 'b', Nome: 'a', Alfa: '4' };

        expect(ordenarValores(valores, ['Nome', 'Data'])).toEqual([
            ['Nome', 'a'],
            ['Data', 'b'],
            ['Alfa', '4'],
            ['Zeta', '3'],
        ]);
    });

    it('ordena alfabeticamente quando o modelo foi excluído', () => {
        expect(ordenarValores({ B: '2', A: '1' }, [])).toEqual([
            ['A', '1'],
            ['B', '2'],
        ]);
    });

    it('trata uma lista vazia, que é como o PHP serializa um array vazio', () => {
        expect(
            ordenarValores([] as unknown as Record<string, string>, []),
        ).toEqual([]);
    });
});

describe('resumoDosValores', () => {
    it('junta os 3 primeiros com " · "', () => {
        const entradas: [string, string][] = [
            ['A', '1'],
            ['B', '2'],
            ['C', '3'],
            ['D', '4'],
        ];

        expect(resumoDosValores(entradas)).toBe('A: 1 · B: 2 · C: 3');
    });
});

describe('formatarData', () => {
    it('formata em português, com dia, mês, ano, hora e minuto', () => {
        expect(formatarData('2026-10-09T15:04:00Z', 'UTC')).toBe(
            '09/10/2026, 15:04',
        );
    });
});
```

Veja **falhar**, depois crie `resources/js/lib/historico.ts`:

```ts
/** Valores na ordem dos campos do modelo; os que não estão no modelo vão depois, em ordem alfabética. */
export function ordenarValores(
    valores: Record<string, string>,
    ordem: string[],
): [string, string][] {
    const entradas = Object.entries(valores);
    const doModelo = ordem.flatMap((nome) =>
        entradas.filter(([chave]) => chave === nome),
    );
    const demais = entradas
        .filter(([chave]) => !ordem.includes(chave))
        .sort(([a], [b]) => a.localeCompare(b, 'pt-BR'));

    return [...doModelo, ...demais];
}

export function resumoDosValores(
    entradas: [string, string][],
    limite = 3,
): string {
    return entradas
        .slice(0, limite)
        .map(([chave, valor]) => `${chave}: ${valor}`)
        .join(' · ');
}

export function formatarData(iso: string, fusoHorario?: string): string {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: fusoHorario,
    });
}
```

Veja **passar**. Se o teste de `formatarData` falhar só pelo formato exato (ex.: `09/10/2026 15:04` sem vírgula, que varia com a versão do ICU), ajuste a expectativa para o valor real, mantendo dia, mês, ano, hora e minuto.

- [ ] **Step 9: Hook das fontes**

`resources/js/hooks/use-fontes-do-catalogo.ts`:

```ts
import { useEffect } from 'react';
import { gerarFontFaces } from '@/lib/fontes';
import { show } from '@/routes/fontes';
import type { GruposFontes, PesosDasFontes } from '@/types/certificados';

/** Declara os @font-face do catálogo, com os mesmos arquivos que o servidor usa para gerar o PNG. */
export function useFontesDoCatalogo(
    fontes: GruposFontes,
    pesos: PesosDasFontes,
): void {
    useEffect(() => {
        const estilo = document.createElement('style');
        estilo.dataset.fontesDoCatalogo = 'true';
        estilo.textContent = gerarFontFaces(fontes, pesos, (pasta, peso) =>
            show.url({ pasta, peso }),
        );
        document.head.appendChild(estilo);

        return () => estilo.remove();
    }, [fontes, pesos]);
}
```

- [ ] **Step 10: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js/lib resources/js/types resources/js/hooks resources/js/components/ui
npm run check
npx vp test run
php artisan test --compact
```

Expected: build e `tsc` sem erros (o build gera `@/routes/fontes` com o `show`), `check` limpo, `vp test run` com todos os testes dos 6 módulos passando e a suíte PHP verde. Os componentes novos do shadcn podem exigir reformatação com `vp fmt` (já incluída acima).

```bash
git add -A
git commit -m "feat: fundação do frontend (tipos, módulos puros testados, hook das fontes e componentes shadcn)

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Identidade visual, navegação e landing

**Files:**

- Modify: `resources/css/app.css`, `vite.config.ts`, `resources/views/app.blade.php`, `resources/js/components/{app-logo-icon,app-logo,app-sidebar}.tsx`
- Create: `public/images/logo.png`, `resources/js/components/cabecalho-da-pagina.tsx`
- Replace: `public/favicon.ico`, `public/apple-touch-icon.png`; Delete: `public/favicon.svg`, `resources/js/pages/dashboard.tsx`
- Rewrite: `resources/js/pages/welcome.tsx`

**Interfaces:**

- Consumes: rotas `home`, `login`, `register` (`@/routes`), `index` de `@/routes/meus-certificados` e de `@/routes/historico` (Wayfinder).
- Produces:
    - Tema do PequenaVia em CSS (variáveis do shadcn) e a utilidade `font-serif` (Playfair Display).
    - `CabecalhoDaPagina` (`@/components/cabecalho-da-pagina`, export default) com props `{ titulo: string; subtitulo?: string; modelo?: string; acoes?: ReactNode }`: com `modelo`, mostra "← Meus certificados / {modelo}" à esquerda e o título à direita (páginas de campos e de geração); sem `modelo`, só o título (lista e histórico).
    - Sidebar com "Meus certificados" e "Histórico"; logo do PequenaVia no lugar do logo do Laravel.

- [ ] **Step 1: Logo e ícones**

```bash
cp /Users/jpbetanza/Projetos/pequenavia/public/images/logo.png public/images/logo.png
cp /Users/jpbetanza/Projetos/pequenavia/public/favicon.ico public/favicon.ico
php -r '$i = new Imagick("public/images/logo.png"); $i->setImageBackgroundColor("transparent"); $i->thumbnailImage(180, 180, true, true); $i->setImageFormat("png"); $i->writeImage("public/apple-touch-icon.png");'
git rm -q public/favicon.svg
ls -la public/images public/favicon.ico public/apple-touch-icon.png
```

Em `resources/views/app.blade.php`, apague a linha `<link rel="icon" href="/favicon.svg" type="image/svg+xml">` (mantenha as de `favicon.ico` e `apple-touch-icon.png`). Se `public/images` não existir, crie-a antes do `cp`.

- [ ] **Step 2: Paleta e fonte serifada**

Em `resources/css/app.css`, troque **todos os valores** das variáveis dos blocos `:root` e `.dark` pelos abaixo (mantenha `--radius`, as `--chart-*` e os nomes; só os valores mudam). `:root`:

```css
--background: #f9f6f2;
--foreground: #2e1e1e;
--card: #fdfbf8;
--card-foreground: #2e1e1e;
--popover: #fdfbf8;
--popover-foreground: #2e1e1e;
--primary: #732626;
--primary-foreground: #f9f6f2;
--secondary: #efebe6;
--secondary-foreground: #2e1e1e;
--muted: #efebe6;
--muted-foreground: #7a6a68;
--accent: #efebe6;
--accent-foreground: #2e1e1e;
--destructive: #dc2626;
--destructive-foreground: #f9f6f2;
--border: #e2dbd3;
--input: #e2dbd3;
--ring: #732626;
--sidebar: #f3eee8;
--sidebar-foreground: #2e1e1e;
--sidebar-primary: #732626;
--sidebar-primary-foreground: #f9f6f2;
--sidebar-accent: #e9e2da;
--sidebar-accent-foreground: #2e1e1e;
--sidebar-border: #e2dbd3;
--sidebar-ring: #732626;
```

`.dark`:

```css
--background: #171111;
--foreground: #efece9;
--card: #1f1717;
--card-foreground: #efece9;
--popover: #1f1717;
--popover-foreground: #efece9;
--primary: #c45454;
--primary-foreground: #171111;
--secondary: #2c2525;
--secondary-foreground: #efece9;
--muted: #2c2525;
--muted-foreground: #a39592;
--accent: #2c2525;
--accent-foreground: #efece9;
--destructive: #dc2626;
--destructive-foreground: #efece9;
--border: #3a2f2f;
--input: #3a2f2f;
--ring: #c45454;
--sidebar: #1c1414;
--sidebar-foreground: #efece9;
--sidebar-primary: #c45454;
--sidebar-primary-foreground: #171111;
--sidebar-accent: #2c2525;
--sidebar-accent-foreground: #efece9;
--sidebar-border: #3a2f2f;
--sidebar-ring: #c45454;
```

No bloco `@theme`, logo abaixo de `--font-sans: ...;`, acrescente:

```css
--font-serif: 'Playfair Display', ui-serif, Georgia, serif;
```

Em `vite.config.ts`, no array `fonts` do plugin do Laravel, acrescente depois do `bunny('Instrument Sans', ...)`:

```ts
                bunny('Playfair Display', {
                    weights: [600, 700],
                }),
```

- [ ] **Step 3: Logo do app**

`resources/js/components/app-logo-icon.tsx` (substitua o arquivo):

```tsx
import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img src="/images/logo.png" alt="PequenaVia" {...props} />;
}
```

`resources/js/components/app-logo.tsx` (substitua o arquivo):

```tsx
import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-8 rounded-md object-cover" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-serif leading-tight font-bold">
                    {name}
                </span>
            </div>
        </>
    );
}
```

Os outros usos de `AppLogoIcon` (`app-header.tsx` e `layouts/auth/*.tsx`) passam classes como `fill-current text-white`, que não fazem efeito numa imagem; troque-as por `object-contain` mantendo o tamanho (`size-9`, `h-6 w-6` etc.), para o logo não ficar distorcido.

- [ ] **Step 4: Navegação lateral**

Em `resources/js/components/app-sidebar.tsx`: troque os ícones importados por `import { FileText, History } from 'lucide-react';`, remova `footerNavItems`, o `NavFooter` e seu import, e deixe:

```tsx
import { index as historico } from '@/routes/historico';
import { index as meusCertificados } from '@/routes/meus-certificados';
```

```tsx
const mainNavItems: NavItem[] = [
    {
        title: 'Meus certificados',
        href: meusCertificados(),
        icon: FileText,
    },
    {
        title: 'Histórico',
        href: historico(),
        icon: History,
    },
];
```

No `SidebarHeader`, o `Link` passa a apontar para `meusCertificados()` (no lugar de `dashboard()`), e o `SidebarFooter` fica só com `<NavUser />`. Se `app-header.tsx` também usar `dashboard`/itens de navegação do kit, aplique a mesma troca (itens "Meus certificados" e "Histórico", sem links de repositório e documentação).

- [ ] **Step 5: Cabeçalho de página**

`resources/js/components/cabecalho-da-pagina.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { index } from '@/routes/meus-certificados';

type Props = {
    titulo: string;
    subtitulo?: string;
    /** Título do modelo: mostra o caminho "← Meus certificados / {modelo}" à esquerda. */
    modelo?: string;
    acoes?: ReactNode;
};

export default function CabecalhoDaPagina({
    titulo,
    subtitulo,
    modelo,
    acoes,
}: Props) {
    return (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
            {modelo !== undefined && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link
                        href={index()}
                        className="flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" />
                        Meus certificados
                    </Link>
                    <span>/</span>
                    <span className="font-medium text-foreground">
                        {modelo}
                    </span>
                </div>
            )}
            <div className={modelo !== undefined ? 'text-right' : ''}>
                <h1 className="font-serif text-3xl font-bold">{titulo}</h1>
                {subtitulo && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subtitulo}
                    </p>
                )}
            </div>
            {acoes}
        </div>
    );
}
```

- [ ] **Step 6: Landing (`pages/welcome.tsx`)**

Substitua o arquivo por:

```tsx
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
```

- [ ] **Step 7: Remover o dashboard e conferir**

```bash
git rm -q resources/js/pages/dashboard.tsx
npm run build
npx tsc --noEmit
npx vp fmt resources/js/components resources/js/pages/welcome.tsx resources/css/app.css vite.config.ts
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde (o `ExampleTest` do kit abre `/` e espera 200). Se algum componente do kit ainda importar `dashboard` de `@/routes` para um link visível ao usuário (por exemplo no `app-header.tsx`), troque para `meusCertificados()`. O `passkey-verify.tsx` pode continuar usando `dashboard` (a rota existe e redireciona).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: identidade visual do PequenaVia, navegação e landing em português

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Tradução das telas do kit e das mensagens do Laravel

**Files:**

- Create: `lang/pt_BR/{auth,passwords,validation,pagination}.php`, `lang/pt_BR.json`, `tests/Feature/Localizacao/TraducaoTest.php`
- Modify (traduzir o texto visível): `resources/js/pages/auth/*.tsx`, `resources/js/pages/settings/*.tsx`, `resources/js/layouts/**/*.tsx`, e os componentes do kit em `resources/js/components/` (`alert-error`, `app-header`, `appearance-tabs`, `breadcrumbs`, `delete-user`, `manage-passkeys`, `manage-two-factor`, `nav-user`, `passkey-item`, `passkey-register`, `passkey-verify`, `password-input`, `two-factor-recovery-codes`, `two-factor-setup-modal`, `user-menu-content`) e textos só-leitura de `components/ui/*` (rótulos `sr-only`, como "Close")

**Interfaces:**

- Consumes: nada novo.
- Produces: interface e mensagens do servidor em português do Brasil (locale `pt_BR`, já em `APP_LOCALE`).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Localizacao/TraducaoTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

beforeEach(fn () => App::setLocale('pt_BR'));

it('traduz as mensagens de autenticação', function () {
    expect(__('auth.failed'))->toBe('Essas credenciais não correspondem aos nossos registros.')
        ->and(__('auth.password'))->toBe('A senha informada está incorreta.')
        ->and(__('auth.throttle', ['seconds' => 30]))->toBe('Muitas tentativas de login. Tente novamente em 30 segundos.');
});

it('traduz as mensagens de redefinição de senha', function () {
    expect(__('passwords.sent'))->toBe('Enviamos por e-mail o link de redefinição de senha.')
        ->and(__('passwords.reset'))->toBe('Sua senha foi redefinida.')
        ->and(__('passwords.user'))->toBe('Não encontramos um usuário com esse endereço de e-mail.');
});

it('traduz a validação usando o nome amigável dos campos', function () {
    $erros = Validator::make(['email' => '', 'password' => 'curta'], [
        'email' => 'required',
        'password' => 'min:8',
    ])->errors();

    expect($erros->first('email'))->toBe('O campo e-mail é obrigatório.')
        ->and($erros->first('password'))->toBe('O campo senha deve ter pelo menos 8 caracteres.');
});

it('traduz as frases dos e-mails do Laravel', function () {
    expect(__('Verify Email Address'))->toBe('Verificar endereço de e-mail')
        ->and(__('Reset Password'))->toBe('Redefinir senha')
        ->and(__('Hello!'))->toBe('Olá!')
        ->and(__('Regards,'))->toBe('Atenciosamente,');
});

it('traduz as mensagens de validação dos formulários do app (sem texto em inglês)', function () {
    $erros = Validator::make(['label' => str_repeat('a', 200), 'tamanho_fonte' => 'x'], [
        'label' => 'max:150',
        'tamanho_fonte' => 'integer',
    ])->errors();

    expect($erros->first('label'))->not->toMatch('/\\bmay not\\b|\\bmust\\b|\\bcharacters\\b/')
        ->and($erros->first('tamanho_fonte'))->not->toMatch('/\\bmust\\b/');
});

it('mostra em português o erro de login com credenciais erradas', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'senha-errada'])
        ->assertSessionHasErrors(['email' => 'Essas credenciais não correspondem aos nossos registros.']);
});
```

Rode `php artisan test --compact tests/Feature/Localizacao` e veja **falhar** (as mensagens saem em inglês). Se a rota `login.store` não existir com esse nome, rode `php artisan route:list --name=login` e use o nome da rota `POST /login`.

- [ ] **Step 2: Arquivos de idioma do backend**

```bash
mkdir -p lang/pt_BR
cp vendor/laravel/framework/src/Illuminate/Translation/lang/en/{auth,passwords,validation,pagination}.php lang/pt_BR/
```

Traduza **os valores** dos quatro arquivos para o português do Brasil, mantendo as chaves, os marcadores (`:attribute`, `:min`, `:max`, `:seconds`, `:other`, `:values` etc.) e a estrutura dos arrays. Estes valores são exigidos pelo teste e valem como referência de estilo:

| Arquivo e chave         | Tradução                                                            |
| ----------------------- | ------------------------------------------------------------------- |
| `auth.failed`           | `Essas credenciais não correspondem aos nossos registros.`          |
| `auth.password`         | `A senha informada está incorreta.`                                 |
| `auth.throttle`         | `Muitas tentativas de login. Tente novamente em :seconds segundos.` |
| `passwords.reset`       | `Sua senha foi redefinida.`                                         |
| `passwords.sent`        | `Enviamos por e-mail o link de redefinição de senha.`               |
| `passwords.throttled`   | `Aguarde antes de tentar novamente.`                                |
| `passwords.token`       | `Este token de redefinição de senha é inválido.`                    |
| `passwords.user`        | `Não encontramos um usuário com esse endereço de e-mail.`           |
| `validation.required`   | `O campo :attribute é obrigatório.`                                 |
| `validation.email`      | `O campo :attribute deve ser um endereço de e-mail válido.`         |
| `validation.confirmed`  | `A confirmação do campo :attribute não confere.`                    |
| `validation.min.string` | `O campo :attribute deve ter pelo menos :min caracteres.`           |
| `validation.max.string` | `O campo :attribute não pode ter mais de :max caracteres.`          |
| `validation.unique`     | `O valor de :attribute já está em uso.`                             |

No `validation.php`, preencha o array `attributes` (hoje vazio) com:

```php
    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'title' => 'título',
        'description' => 'descrição',
        'imagem' => 'imagem',
        'nome' => 'nome',
        'label' => 'rótulo',
        'confirmacao' => 'confirmação',
        'valores' => 'valores',
    ],
```

- [ ] **Step 3: Frases do framework (`lang/pt_BR.json`)**

Crie `lang/pt_BR.json` com as frases que o Laravel usa nos e-mails e nas validações de senha, mais as do app. Confira a lista contra o código com o comando abaixo e acrescente qualquer chave que ele mostrar e que falte:

```bash
grep -rhoE "(Lang::get|trans|__|@lang)\(('[^']*'|\"[^\"]*\")" vendor/laravel/framework/src/Illuminate/Auth/Notifications vendor/laravel/framework/src/Illuminate/Notifications/resources/views vendor/laravel/framework/src/Illuminate/Mail/resources/views vendor/laravel/fortify/src vendor/laravel/passkeys/src app | sort -u
```

```json
{
    "All rights reserved.": "Todos os direitos reservados.",
    "Hello!": "Olá!",
    "Whoops!": "Ops!",
    "Regards,": "Atenciosamente,",
    "Verify Email Address": "Verificar endereço de e-mail",
    "Verify your email address": "Verifique o seu endereço de e-mail",
    "Please click the button below to verify your email address.": "Clique no botão abaixo para verificar o seu endereço de e-mail.",
    "If you did not create an account, no further action is required.": "Se você não criou uma conta, nenhuma ação é necessária.",
    "Reset Password": "Redefinir senha",
    "Reset your password": "Redefina a sua senha",
    "You are receiving this email because we received a password reset request for your account.": "Você está recebendo este e-mail porque recebemos um pedido de redefinição de senha para a sua conta.",
    "This password reset link will expire in :count minutes.": "Este link de redefinição de senha expira em :count minutos.",
    "If you did not request a password reset, no further action is required.": "Se você não pediu a redefinição de senha, nenhuma ação é necessária.",
    "The provided password was incorrect.": "A senha informada está incorreta.",
    "The provided two factor authentication code was invalid.": "O código de autenticação em dois fatores informado é inválido.",
    "The provided two factor recovery code was invalid.": "O código de recuperação em dois fatores informado é inválido.",
    "Invalid credential format.": "Formato de credencial inválido.",
    "Passkey registration session expired. Please try again.": "A sessão de cadastro da chave de acesso expirou. Tente novamente.",
    "Passkey verification session expired. Please try again.": "A sessão de verificação da chave de acesso expirou. Tente novamente.",
    "Profile updated.": "Perfil atualizado.",
    "Password updated.": "Senha atualizada.",
    "(and :count more error)": "(e mais :count erro)",
    "(and :count more errors)": "(e mais :count erros)",
    "The :attribute must be at least :length characters.": "O campo :attribute deve ter pelo menos :length caracteres.",
    "The :attribute must be at least :length characters and contain at least one uppercase character.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos uma letra maiúscula.",
    "The :attribute must be at least :length characters and contain at least one number.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos um número.",
    "The :attribute must be at least :length characters and contain at least one special character.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos um caractere especial.",
    "The :attribute must be at least :length characters and contain at least one uppercase character and one number.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos uma letra maiúscula e um número.",
    "The :attribute must be at least :length characters and contain at least one uppercase character and one special character.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos uma letra maiúscula e um caractere especial.",
    "The :attribute must be at least :length characters and contain at least one special character and one number.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos um caractere especial e um número.",
    "The :attribute must be at least :length characters and contain at least one uppercase character, one number, and one special character.": "O campo :attribute deve ter pelo menos :length caracteres e conter ao menos uma letra maiúscula, um número e um caractere especial."
}
```

O botão-link dos e-mails usa a frase `If you’re having trouble clicking the ":actionText" button, copy and paste the URL below\ninto your web browser:` (com apóstrofo tipográfico `’`). Copie a chave exatamente como está em `vendor/laravel/framework/src/Illuminate/Notifications/resources/views/email.blade.php` e traduza: `Se você tiver problemas para clicar no botão \":actionText\", copie e cole o endereço abaixo\nno seu navegador:`. Se `Reset Password Notification` aparecer no grep, traduza para `Notificação de redefinição de senha`.

Rode `php artisan test --compact tests/Feature/Localizacao` e veja **passar**; depois a suíte inteira (`php artisan test --compact`). Se algum teste do kit afirmava texto em inglês das mensagens, ajuste a asserção para o texto em português.

- [ ] **Step 4: Traduzir as telas (React)**

Traduza todo texto visível ao usuário nos arquivos listados em **Files** para o português do Brasil: títulos, descrições, rótulos, `placeholder`, `aria-label`, `title`, textos de botões, mensagens vazias, textos `sr-only`, o `title` do `<Head>` e o objeto `Pagina.layout = { title, description, breadcrumbs }`. **Não** altere identificadores, nomes de rotas/props, atributos `name`, `data-test`, valores de `autoComplete`, lógica ou classes CSS. Use este glossário para manter o vocabulário consistente:

| Inglês                                           | Português                                                 |
| ------------------------------------------------ | --------------------------------------------------------- |
| Log in / Sign in                                 | Entrar                                                    |
| Log out                                          | Sair                                                      |
| Sign up / Register / Create account              | Criar conta                                               |
| Email address / Email                            | E-mail                                                    |
| Password                                         | Senha                                                     |
| Confirm password                                 | Confirmar senha                                           |
| Current password / New password                  | Senha atual / Nova senha                                  |
| Forgot your password?                            | Esqueceu a senha?                                         |
| Remember me                                      | Lembrar de mim                                            |
| Reset password / Update password                 | Redefinir senha / Atualizar senha                         |
| Settings / Profile / Appearance / Security       | Configurações / Perfil / Aparência / Segurança            |
| Light / Dark / System                            | Claro / Escuro / Sistema                                  |
| Name                                             | Nome                                                      |
| Save / Cancel / Delete / Close / Back / Continue | Salvar / Cancelar / Excluir / Fechar / Voltar / Continuar |
| Delete account                                   | Excluir conta                                             |
| Two-factor authentication                        | Autenticação em dois fatores                              |
| Recovery codes                                   | Códigos de recuperação                                    |
| Passkey / Passkeys                               | Chave de acesso / Chaves de acesso                        |
| Resend verification email                        | Reenviar e-mail de verificação                            |
| Verify email                                     | Verificar e-mail                                          |
| Toggle Sidebar                                   | Alternar barra lateral                                    |
| Search                                           | Buscar                                                    |

Conferência: o comando abaixo lista linhas que ainda têm palavras comuns em inglês nesses arquivos. Cada ocorrência que sobrar deve ser um identificador, um import ou um valor técnico, e não texto da interface:

```bash
grep -rnwE "Log in|Log out|Sign up|Email address|Password|Forgot|Remember me|Confirm|Cancel|Delete|Save|Settings|Profile|Appearance|Security|Two-factor|Passkey|Passkeys|Close|Toggle|Search|Create account|Update|Current|Resend|Verify|Continue|Welcome" resources/js/pages/auth resources/js/pages/settings resources/js/layouts resources/js/components --include='*.tsx' | grep -v "import \|from '"
```

- [ ] **Step 5: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde.

```bash
git add -A
git commit -m "feat: interface e mensagens do Laravel em português do Brasil

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Lista de modelos, diálogos e compartilhamento

**Files:**

- Rewrite: `resources/js/pages/meus-certificados/index.tsx`
- Create: `resources/js/components/modelos/{cartao-de-modelo,dialogo-novo-modelo,dialogo-alterar-modelo,dialogo-opcoes-do-modelo,painel-de-compartilhamento,secao-compartilhados}.tsx`

**Interfaces:**

- Consumes: props `modelos: ModeloResumo[]` e `compartilhados: ModeloResumo[]` (Task 1 e Plano 1); `CabecalhoDaPagina` (Task 3); tipos de `@/types/certificados`; ações do Wayfinder: `store`, `update`, `destroy` de `@/actions/App/Http/Controllers/ModeloCertificadoController` e de `@/actions/App/Http/Controllers/CompartilhamentoController`, e `campos`, `gerar`, `index` de `@/routes/meus-certificados`. Os parâmetros de rota são passados como objeto (`{ modelo: id }`, `{ modelo: id, compartilhamento: id }`).
- Produces: a página `meus-certificados/index` completa (cartões, "Adicionar novo modelo", diálogo de opções com compartilhamento e exclusão, diálogo de alteração, seção "Compartilhados comigo").

Comportamento (portado do app antigo):

- Grade de cartões com a imagem do modelo (4:3), título e descrição. Clicar no cartão abre o diálogo de opções. O último item da grade é o cartão tracejado "Adicionar novo modelo".
- Diálogo de opções: "Gerar certificados" (desabilitado e com aviso quando o modelo não tem campos), "Configurar campos", "Compartilhar" (abre um painel ao lado), "Alterar" e "Excluir" (com confirmação). Cada botão só aparece se a flag do modelo permitir (`pode_editar`, `eh_dono`).
- Painel de compartilhamento: campo de e-mail + botão de adicionar, lista com o e-mail, a chave "Pode editar" e o botão de remover; estado vazio "Nenhum compartilhamento ainda". Os erros do servidor aparecem sob o campo (por exemplo, "Este modelo já está compartilhado com este e-mail.").
- Seção "Compartilhados comigo" (só aparece se houver itens): cartões com as etiquetas "Pode gerar" e, se for o caso, "Pode editar", e os botões "Gerar" e "Campos".

- [ ] **Step 1: Cartão de modelo**

`resources/js/components/modelos/cartao-de-modelo.tsx`:

```tsx
import { Card } from '@/components/ui/card';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo;
    aoClicar: () => void;
};

export default function CartaoDeModelo({ modelo, aoClicar }: Props) {
    return (
        <Card
            role="button"
            tabIndex={0}
            className="cursor-pointer gap-0 overflow-hidden py-0 transition-shadow hover:shadow-lg"
            onClick={aoClicar}
            onKeyDown={(evento) => {
                if (evento.key === 'Enter' || evento.key === ' ') {
                    evento.preventDefault();
                    aoClicar();
                }
            }}
        >
            <div className="aspect-[4/3] w-full overflow-hidden bg-muted">
                <img
                    src={modelo.imagem_url}
                    alt={modelo.title}
                    loading="lazy"
                    className="size-full object-cover"
                />
            </div>
            <div className="flex flex-col items-center gap-1 p-4 text-center">
                <p className="line-clamp-1 text-sm font-semibold">
                    {modelo.title}
                </p>
                {modelo.description && (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {modelo.description}
                    </p>
                )}
            </div>
        </Card>
    );
}
```

- [ ] **Step 2: Diálogo de novo modelo**

`resources/js/components/modelos/dialogo-novo-modelo.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    aberto: boolean;
    aoFechar: () => void;
};

export default function DialogoNovoModelo({ aberto, aoFechar }: Props) {
    const formulario = useForm<{
        title: string;
        description: string;
        imagem: File | null;
    }>({
        title: '',
        description: '',
        imagem: null,
    });

    const fechar = () => {
        formulario.reset();
        formulario.clearErrors();
        aoFechar();
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: fechar,
        });
    };

    return (
        <Dialog open={aberto} onOpenChange={(abrir) => !abrir && fechar()}>
            <DialogContent>
                <form onSubmit={enviar} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Adicionar novo modelo</DialogTitle>
                        <DialogDescription>
                            Envie a imagem do certificado em branco (PNG, JPG ou
                            WEBP, até 50 MB).
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="titulo-do-modelo">Título</Label>
                        <Input
                            id="titulo-do-modelo"
                            placeholder="Nome do modelo"
                            value={formulario.data.title}
                            onChange={(evento) =>
                                formulario.setData('title', evento.target.value)
                            }
                            required
                        />
                        <InputError message={formulario.errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="descricao-do-modelo">Descrição</Label>
                        <Textarea
                            id="descricao-do-modelo"
                            placeholder="Descrição opcional"
                            value={formulario.data.description}
                            onChange={(evento) =>
                                formulario.setData(
                                    'description',
                                    evento.target.value,
                                )
                            }
                        />
                        <InputError message={formulario.errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="imagem-do-modelo">Imagem</Label>
                        <Input
                            id="imagem-do-modelo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            onChange={(evento) =>
                                formulario.setData(
                                    'imagem',
                                    evento.target.files?.[0] ?? null,
                                )
                            }
                            required
                        />
                        <InputError message={formulario.errors.imagem} />
                        {formulario.progress && (
                            <progress
                                value={formulario.progress.percentage}
                                max="100"
                                className="h-2 w-full"
                            />
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={fechar}
                            disabled={formulario.processing}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={formulario.processing}>
                            {formulario.processing && <Spinner />}
                            Salvar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 3: Diálogo de alterar modelo**

`resources/js/components/modelos/dialogo-alterar-modelo.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { update } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo | null;
    aoFechar: () => void;
};

function Formulario({
    modelo,
    aoFechar,
}: {
    modelo: ModeloResumo;
    aoFechar: () => void;
}) {
    const formulario = useForm<{
        title: string;
        description: string;
        imagem: File | null;
    }>({
        title: modelo.title,
        description: modelo.description ?? '',
        imagem: null,
    });

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.put(update.url({ modelo: modelo.id }), {
            forceFormData: formulario.data.imagem !== null,
            preserveScroll: true,
            onSuccess: aoFechar,
        });
    };

    return (
        <form onSubmit={enviar} className="space-y-4">
            <DialogHeader>
                <DialogTitle>Alterar modelo</DialogTitle>
                <DialogDescription>
                    Mude o título, a descrição ou troque a imagem do modelo.
                </DialogDescription>
            </DialogHeader>

            <img
                src={modelo.imagem_url}
                alt={modelo.title}
                className="aspect-[4/3] w-full rounded-md border object-cover"
            />

            <div className="grid gap-2">
                <Label htmlFor="alterar-titulo">Título</Label>
                <Input
                    id="alterar-titulo"
                    value={formulario.data.title}
                    onChange={(evento) =>
                        formulario.setData('title', evento.target.value)
                    }
                    required
                />
                <InputError message={formulario.errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alterar-descricao">Descrição</Label>
                <Textarea
                    id="alterar-descricao"
                    value={formulario.data.description}
                    onChange={(evento) =>
                        formulario.setData('description', evento.target.value)
                    }
                />
                <InputError message={formulario.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="alterar-imagem">Nova imagem (opcional)</Label>
                <Input
                    id="alterar-imagem"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    onChange={(evento) =>
                        formulario.setData(
                            'imagem',
                            evento.target.files?.[0] ?? null,
                        )
                    }
                />
                <InputError message={formulario.errors.imagem} />
                {formulario.progress && (
                    <progress
                        value={formulario.progress.percentage}
                        max="100"
                        className="h-2 w-full"
                    />
                )}
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={aoFechar}
                    disabled={formulario.processing}
                >
                    Cancelar
                </Button>
                <Button type="submit" disabled={formulario.processing}>
                    {formulario.processing && <Spinner />}
                    Salvar
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function DialogoAlterarModelo({ modelo, aoFechar }: Props) {
    return (
        <Dialog
            open={modelo !== null}
            onOpenChange={(abrir) => !abrir && aoFechar()}
        >
            <DialogContent>
                {modelo && (
                    <Formulario
                        key={modelo.id}
                        modelo={modelo}
                        aoFechar={aoFechar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
```

(O `key` reinicia o formulário a cada modelo. O Inertia troca o método para `POST` com `_method=PUT` sozinho quando há arquivo.)

- [ ] **Step 4: Painel de compartilhamento**

`resources/js/components/modelos/painel-de-compartilhamento.tsx`:

```tsx
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CompartilhamentoController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo;
};

export default function PainelDeCompartilhamento({ modelo }: Props) {
    const formulario = useForm({ email: '' });
    const opcoes = { preserveScroll: true, preserveState: true } as const;

    const adicionar = (evento: FormEvent) => {
        evento.preventDefault();
        formulario.post(store.url({ modelo: modelo.id }), {
            ...opcoes,
            onSuccess: () => formulario.reset('email'),
        });
    };

    return (
        <div className="flex min-w-0 flex-1 flex-col gap-3">
            <div>
                <h3 className="text-sm font-semibold">Compartilhar modelo</h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Adicione e-mails para compartilhar este modelo.
                </p>
            </div>

            <form onSubmit={adicionar} className="grid gap-2">
                <div className="flex gap-2">
                    <Input
                        type="email"
                        placeholder="E-mail do destinatário"
                        value={formulario.data.email}
                        onChange={(evento) =>
                            formulario.setData('email', evento.target.value)
                        }
                        className="flex-1"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        aria-label="Adicionar e-mail"
                        disabled={
                            formulario.processing ||
                            formulario.data.email.trim() === ''
                        }
                    >
                        {formulario.processing ? <Spinner /> : <Plus />}
                    </Button>
                </div>
                <InputError message={formulario.errors.email} />
            </form>

            {modelo.compartilhamentos.length === 0 ? (
                <p className="py-4 text-center text-xs text-muted-foreground">
                    Nenhum compartilhamento ainda
                </p>
            ) : (
                <ul className="flex max-h-52 flex-col gap-2 overflow-y-auto">
                    {modelo.compartilhamentos.map((compartilhamento) => (
                        <li
                            key={compartilhamento.id}
                            className="flex items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2"
                        >
                            <span className="flex-1 truncate text-xs">
                                {compartilhamento.email}
                            </span>
                            <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                Pode editar
                                <Switch
                                    checked={compartilhamento.pode_editar}
                                    onCheckedChange={(marcado) =>
                                        router.patch(
                                            update.url({
                                                modelo: modelo.id,
                                                compartilhamento:
                                                    compartilhamento.id,
                                            }),
                                            { pode_editar: marcado },
                                            opcoes,
                                        )
                                    }
                                />
                            </label>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7 text-destructive"
                                aria-label={`Remover ${compartilhamento.email}`}
                                onClick={() =>
                                    router.delete(
                                        destroy.url({
                                            modelo: modelo.id,
                                            compartilhamento:
                                                compartilhamento.id,
                                        }),
                                        opcoes,
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
```

- [ ] **Step 5: Diálogo de opções do modelo**

O estado `compartilhando` fica no componente de fora, porque a largura do diálogo (`sm:max-w-3xl` com o painel aberto, `sm:max-w-sm` sem ele) depende dele.

`resources/js/components/modelos/dialogo-opcoes-do-modelo.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { FileText, Pencil, Settings, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/ModeloCertificadoController';
import PainelDeCompartilhamento from '@/components/modelos/painel-de-compartilhamento';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelo: ModeloResumo | null;
    aoFechar: () => void;
    aoAlterar: (id: string) => void;
};

type PropsDoConteudo = {
    modelo: ModeloResumo;
    compartilhando: boolean;
    aoAlternarCompartilhamento: () => void;
    aoFechar: () => void;
    aoAlterar: (id: string) => void;
};

function Conteudo({
    modelo,
    compartilhando,
    aoAlternarCompartilhamento,
    aoFechar,
    aoAlterar,
}: PropsDoConteudo) {
    const [confirmandoExclusao, setConfirmandoExclusao] = useState(false);
    const semCampos = modelo.campos_count === 0;

    const ir = (url: string) => {
        aoFechar();
        router.visit(url);
    };

    const excluir = () => {
        router.delete(destroy.url({ modelo: modelo.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmandoExclusao(false);
                aoFechar();
            },
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>{modelo.title}</DialogTitle>
            </DialogHeader>

            <div className={cn('flex', compartilhando && 'gap-6')}>
                <div
                    className={cn(
                        'flex flex-col gap-2',
                        compartilhando ? 'w-[260px] shrink-0' : 'w-full',
                    )}
                >
                    {semCampos && (
                        <Alert>
                            <AlertDescription>
                                Este modelo não tem campos configurados.
                                Configure os campos antes de gerar certificados.
                            </AlertDescription>
                        </Alert>
                    )}

                    <Button
                        disabled={semCampos}
                        onClick={() => ir(gerar.url({ modelo: modelo.id }))}
                    >
                        <FileText />
                        Gerar certificados
                    </Button>

                    <Button
                        variant={semCampos ? 'default' : 'secondary'}
                        onClick={() => ir(campos.url({ modelo: modelo.id }))}
                    >
                        <Settings />
                        {modelo.pode_editar
                            ? 'Configurar campos'
                            : 'Ver campos'}
                    </Button>

                    {modelo.eh_dono && (
                        <Button
                            variant={compartilhando ? 'default' : 'secondary'}
                            onClick={aoAlternarCompartilhamento}
                        >
                            <Share2 />
                            Compartilhar
                        </Button>
                    )}

                    <div className="flex gap-2">
                        {modelo.pode_editar && (
                            <Button
                                variant="secondary"
                                className="flex-1"
                                onClick={() => {
                                    aoFechar();
                                    aoAlterar(modelo.id);
                                }}
                            >
                                <Pencil />
                                Alterar
                            </Button>
                        )}
                        {modelo.eh_dono && (
                            <Button
                                variant="destructive"
                                className="flex-1"
                                onClick={() => setConfirmandoExclusao(true)}
                            >
                                <Trash2 />
                                Excluir
                            </Button>
                        )}
                    </div>
                </div>

                {compartilhando && modelo.eh_dono && (
                    <PainelDeCompartilhamento modelo={modelo} />
                )}
            </div>

            <AlertDialog
                open={confirmandoExclusao}
                onOpenChange={setConfirmandoExclusao}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Excluir "{modelo.title}"?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta ação não pode ser desfeita. O modelo, os campos
                            e a imagem serão apagados, e quem recebeu o
                            compartilhamento perde o acesso.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={excluir}>
                            Excluir
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

export default function DialogoOpcoesDoModelo({
    modelo,
    aoFechar,
    aoAlterar,
}: Props) {
    const [compartilhando, setCompartilhando] = useState(false);

    const fechar = () => {
        setCompartilhando(false);
        aoFechar();
    };

    return (
        <Dialog
            open={modelo !== null}
            onOpenChange={(abrir) => !abrir && fechar()}
        >
            <DialogContent
                className={cn(
                    'transition-[max-width] duration-300',
                    compartilhando ? 'sm:max-w-3xl' : 'sm:max-w-sm',
                )}
            >
                {modelo && (
                    <Conteudo
                        key={modelo.id}
                        modelo={modelo}
                        compartilhando={compartilhando}
                        aoAlternarCompartilhamento={() =>
                            setCompartilhando((atual) => !atual)
                        }
                        aoFechar={fechar}
                        aoAlterar={aoAlterar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
```

(`gerar.url(...)` e `campos.url(...)` devolvem o endereço como texto, que é o que `router.visit` espera.)

- [ ] **Step 6: Seção "Compartilhados comigo"**

`resources/js/components/modelos/secao-compartilhados.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { FileText, Settings } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { campos, gerar } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    itens: ModeloResumo[];
};

export default function SecaoCompartilhados({ itens }: Props) {
    if (itens.length === 0) {
        return null;
    }

    return (
        <section className="mt-12">
            <div className="mb-6">
                <h2 className="font-serif text-2xl font-bold">
                    Compartilhados comigo
                </h2>
                <p className="mt-1 text-muted-foreground">
                    Modelos que outros usuários compartilharam com você.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                {itens.map((modelo) => (
                    <Card
                        key={modelo.id}
                        className="gap-0 overflow-hidden py-0"
                    >
                        <div className="aspect-[4/3] w-full overflow-hidden bg-muted">
                            <img
                                src={modelo.imagem_url}
                                alt={modelo.title}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </div>
                        <div className="flex flex-col gap-2 p-4">
                            <div>
                                <p className="line-clamp-1 text-sm font-semibold">
                                    {modelo.title}
                                </p>
                                {modelo.description && (
                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                        {modelo.description}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Compartilhado por {modelo.dono_nome}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-1">
                                <Badge variant="secondary">Pode gerar</Badge>
                                {modelo.pode_editar && (
                                    <Badge>Pode editar</Badge>
                                )}
                            </div>
                            <div className="flex gap-2">
                                {modelo.campos_count === 0 ? (
                                    <Button
                                        size="sm"
                                        className="flex-1"
                                        disabled
                                        title="Este modelo ainda não tem campos"
                                    >
                                        <FileText />
                                        Gerar
                                    </Button>
                                ) : (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="flex-1"
                                    >
                                        <Link
                                            href={gerar({ modelo: modelo.id })}
                                        >
                                            <FileText />
                                            Gerar
                                        </Link>
                                    </Button>
                                )}
                                {modelo.pode_editar && (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="secondary"
                                        className="flex-1"
                                    >
                                        <Link
                                            href={campos({ modelo: modelo.id })}
                                        >
                                            <Settings />
                                            Campos
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </section>
    );
}
```

- [ ] **Step 7: A página**

`resources/js/pages/meus-certificados/index.tsx` (substitua):

```tsx
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CartaoDeModelo from '@/components/modelos/cartao-de-modelo';
import DialogoAlterarModelo from '@/components/modelos/dialogo-alterar-modelo';
import DialogoNovoModelo from '@/components/modelos/dialogo-novo-modelo';
import DialogoOpcoesDoModelo from '@/components/modelos/dialogo-opcoes-do-modelo';
import SecaoCompartilhados from '@/components/modelos/secao-compartilhados';
import { index } from '@/routes/meus-certificados';
import type { ModeloResumo } from '@/types/certificados';

type Props = {
    modelos: ModeloResumo[];
    compartilhados: ModeloResumo[];
};

export default function MeusCertificados({ modelos, compartilhados }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [opcoesDe, setOpcoesDe] = useState<string | null>(null);
    const [alterarDe, setAlterarDe] = useState<string | null>(null);

    return (
        <>
            <Head title="Meus certificados" />
            <div className="p-4">
                <CabecalhoDaPagina
                    titulo="Meus certificados"
                    subtitulo="Gerencie seus modelos de certificado"
                />

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    {modelos.map((modelo) => (
                        <CartaoDeModelo
                            key={modelo.id}
                            modelo={modelo}
                            aoClicar={() => setOpcoesDe(modelo.id)}
                        />
                    ))}

                    <button
                        type="button"
                        onClick={() => setNovoAberto(true)}
                        className="flex min-h-[200px] cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-6 text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        <Plus className="size-8" />
                        <span className="text-sm font-medium">
                            Adicionar novo modelo
                        </span>
                    </button>
                </div>

                <SecaoCompartilhados itens={compartilhados} />
            </div>

            <DialogoNovoModelo
                aberto={novoAberto}
                aoFechar={() => setNovoAberto(false)}
            />
            <DialogoOpcoesDoModelo
                modelo={
                    modelos.find((modelo) => modelo.id === opcoesDe) ?? null
                }
                aoFechar={() => setOpcoesDe(null)}
                aoAlterar={setAlterarDe}
            />
            <DialogoAlterarModelo
                modelo={
                    modelos.find((modelo) => modelo.id === alterarDe) ?? null
                }
                aoFechar={() => setAlterarDe(null)}
            />
        </>
    );
}

MeusCertificados.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
```

(O modelo do diálogo é procurado nas props a cada renderização: depois de compartilhar, alterar ou excluir, o Inertia recarrega a página e o diálogo mostra os dados novos sem estado duplicado.)

- [ ] **Step 8: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js/components/modelos resources/js/pages/meus-certificados/index.tsx
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde. Se o `tsc` reclamar do retorno de `gerar({ modelo })`/`campos({ modelo })` (tipo `RouteDefinition`), use `gerar.url({ modelo: id })` onde a API pedir texto e `gerar({ modelo: id })` onde pedir `href`. Se um import nomeado de `@/actions/...` não existir, use o export `default` do arquivo (`import ModeloCertificadoController from '@/actions/...'` e `ModeloCertificadoController.store.url()`).

```bash
git add -A
git commit -m "feat: lista de modelos, diálogos de alterar e excluir e compartilhamento por e-mail

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Editor de campos

**Files:**

- Rewrite: `resources/js/pages/meus-certificados/campos.tsx`
- Create: `resources/js/components/campos/{seletor-de-fonte,campo-numerico,campo-de-texto,cartao-do-campo,preview-do-modelo,campos-editor}.tsx`

**Interfaces:**

- Consumes: props `modelo: ModeloBasico`, `campos: Campo[]`, `pode_editar: boolean`, `fontes: GruposFontes`, `pesos_das_fontes: PesosDasFontes` (Plano 2 e Task 1); `enviarJson`, `mensagemDoErro` (`@/lib/http`); `escalaDoPreview`, `moverCampo`, `valoresDoNovoCampo` (`@/lib/editor`); `pesoMaisProximo`, `pesosDaFamilia`, `rotuloDoPeso` (`@/lib/fontes`); `useFontesDoCatalogo`; `CabecalhoDaPagina`; ações `store`, `update`, `destroy` de `@/actions/App/Http/Controllers/CampoCertificadoController` (parâmetros `{ modelo: id }` e `{ modelo: id, campo: id }`).
- Produces:
    - `PreviewDoModelo` (`@/components/campos/preview-do-modelo`, export default) com props `{ modelo: ModeloBasico; campos: Campo[]; somenteLeitura: boolean; arrastando: string | null; aoIniciarArraste: (evento: React.PointerEvent, campo: Campo, escala: number) => void }`: reutilizado pela Task 7 como "Ver preview" (somente leitura).
    - A página `meus-certificados/campos` completa.

Comportamento (portado do app antigo, com as mudanças da tabela do topo do plano):

- Painel esquerdo: um cartão por campo, com nome, rótulo, botão de centralizar, excluir; X, Y, tamanho da fonte, espaçamento; cor, tipografia (busca por nome, agrupada) e peso (só os pesos que a fonte tem). Botão tracejado "Novo campo". Cada alteração é salva na hora (`PATCH` JSON); em caso de erro, mostra o toast e volta o valor anterior.
- Painel direito: a imagem do modelo com os textos dos campos por cima (`rótulo || nome`), que se arrastam (um campo centralizado só se move na vertical). Ao soltar, salva a posição. Abaixo, "Salvando…" ou "Alterações salvas automaticamente".
- Sem permissão de edição (leitor): aviso, campos desabilitados, sem arrastar nem criar.
- A tela não tem botão "Salvar" (decisão do topo do plano).

- [ ] **Step 1: Seletor de fonte**

`resources/js/components/campos/seletor-de-fonte.tsx`:

```tsx
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { GruposFontes } from '@/types/certificados';

type Props = {
    valor: string;
    fontes: GruposFontes;
    aoEscolher: (familia: string) => void;
    desabilitado?: boolean;
};

export default function SeletorDeFonte({
    valor,
    fontes,
    aoEscolher,
    desabilitado,
}: Props) {
    const [aberto, setAberto] = useState(false);

    return (
        <Popover open={aberto} onOpenChange={setAberto}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={aberto}
                    disabled={desabilitado}
                    className="h-8 w-full justify-between px-2 text-xs font-normal"
                    style={{ fontFamily: `"${valor}"` }}
                >
                    <span className="truncate">{valor}</span>
                    <ChevronsUpDown className="size-3 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput placeholder="Buscar fonte..." />
                    <CommandList>
                        <CommandEmpty>Nenhuma fonte encontrada.</CommandEmpty>
                        {Object.entries(fontes).map(([grupo, familias]) => (
                            <CommandGroup key={grupo} heading={grupo}>
                                {Object.keys(familias).map((familia) => (
                                    <CommandItem
                                        key={familia}
                                        value={familia}
                                        style={{ fontFamily: `"${familia}"` }}
                                        onSelect={() => {
                                            aoEscolher(familia);
                                            setAberto(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'size-3',
                                                familia === valor
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {familia}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
```

- [ ] **Step 2: Campos de entrada com rascunho**

Os campos mostram o valor das props e só guardam um rascunho enquanto o usuário digita; confirmam ao sair do campo (ou com Enter). Assim o valor muda sozinho quando o campo é arrastado, sem efeitos de sincronização.

`resources/js/components/campos/campo-numerico.tsx`:

```tsx
import { useId, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    rotulo: string;
    valor: number;
    aoConfirmar: (valor: number) => void;
    min?: number;
    max?: number;
    passo?: number;
    desabilitado?: boolean;
};

export default function CampoNumerico({
    rotulo,
    valor,
    aoConfirmar,
    min,
    max,
    passo,
    desabilitado,
}: Props) {
    const [rascunho, setRascunho] = useState<string | null>(null);
    const id = useId();

    const confirmar = () => {
        if (rascunho === null) {
            return;
        }

        const digitado = Number(rascunho);
        setRascunho(null);

        if (rascunho.trim() === '' || !Number.isFinite(digitado)) {
            return;
        }

        const limitado = Math.min(
            max ?? Infinity,
            Math.max(min ?? -Infinity, digitado),
        );

        if (limitado !== valor) {
            aoConfirmar(limitado);
        }
    };

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-[10px] text-muted-foreground">
                {rotulo}
            </Label>
            <Input
                id={id}
                type="number"
                className="h-8 px-2 text-xs"
                value={rascunho ?? String(valor)}
                min={min}
                max={max}
                step={passo}
                disabled={desabilitado}
                onChange={(evento) => setRascunho(evento.target.value)}
                onBlur={confirmar}
                onKeyDown={(evento) =>
                    evento.key === 'Enter' && evento.currentTarget.blur()
                }
            />
        </div>
    );
}
```

`resources/js/components/campos/campo-de-texto.tsx`:

```tsx
import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    valor: string;
    aoConfirmar: (valor: string) => void;
    placeholder?: string;
    rotulo: string;
    obrigatorio?: boolean;
    desabilitado?: boolean;
};

export default function CampoDeTexto({
    valor,
    aoConfirmar,
    placeholder,
    rotulo,
    obrigatorio,
    desabilitado,
}: Props) {
    const [rascunho, setRascunho] = useState<string | null>(null);

    const confirmar = () => {
        if (rascunho === null) {
            return;
        }

        const limpo = rascunho.trim();
        setRascunho(null);

        if ((obrigatorio && limpo === '') || limpo === valor) {
            return;
        }

        aoConfirmar(limpo);
    };

    return (
        <Input
            aria-label={rotulo}
            className="h-8 flex-1 px-2 text-xs"
            placeholder={placeholder}
            value={rascunho ?? valor}
            disabled={desabilitado}
            onChange={(evento) => setRascunho(evento.target.value)}
            onBlur={confirmar}
            onKeyDown={(evento) =>
                evento.key === 'Enter' && evento.currentTarget.blur()
            }
        />
    );
}
```

- [ ] **Step 3: Cartão do campo**

`resources/js/components/campos/cartao-do-campo.tsx`:

```tsx
import { AlignCenter, Trash2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import CampoDeTexto from '@/components/campos/campo-de-texto';
import CampoNumerico from '@/components/campos/campo-numerico';
import SeletorDeFonte from '@/components/campos/seletor-de-fonte';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { pesoMaisProximo, pesosDaFamilia, rotuloDoPeso } from '@/lib/fontes';
import type { Campo, GruposFontes, PesosDasFontes } from '@/types/certificados';

type Props = {
    campo: Campo;
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    somenteLeitura: boolean;
    aoAtualizar: (id: string, mudancas: Partial<Campo>) => void;
    aoExcluir: (id: string) => void;
};

/** O seletor de cor nativo dispara a cada movimento; só salva depois de uma pausa. */
function CampoDeCor({
    valor,
    aoConfirmar,
    desabilitado,
}: {
    valor: string;
    aoConfirmar: (cor: string) => void;
    desabilitado: boolean;
}) {
    const [rascunho, setRascunho] = useState<string | null>(null);
    const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null);
    const id = useId();

    useEffect(
        () => () => {
            if (temporizador.current) {
                clearTimeout(temporizador.current);
            }
        },
        [],
    );

    const mudar = (cor: string) => {
        setRascunho(cor);

        if (temporizador.current) {
            clearTimeout(temporizador.current);
        }

        temporizador.current = setTimeout(() => {
            aoConfirmar(cor);
            setRascunho(null);
        }, 400);
    };

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id} className="text-[10px] text-muted-foreground">
                Cor
            </Label>
            <div className="flex items-center gap-1.5">
                <input
                    id={id}
                    type="color"
                    value={rascunho ?? valor}
                    disabled={desabilitado}
                    onChange={(evento) => mudar(evento.target.value)}
                    className="h-8 w-10 cursor-pointer rounded border bg-background p-0.5 disabled:cursor-not-allowed"
                />
                <span className="font-mono text-[10px] text-muted-foreground">
                    {rascunho ?? valor}
                </span>
            </div>
        </div>
    );
}

export default function CartaoDoCampo({
    campo,
    fontes,
    pesos,
    somenteLeitura,
    aoAtualizar,
    aoExcluir,
}: Props) {
    const pesosDaFonte = pesosDaFamilia(
        fontes,
        pesos,
        campo.fonte_familia,
        campo.peso_fonte,
    );
    const atualizar = (mudancas: Partial<Campo>) =>
        aoAtualizar(campo.id, mudancas);

    const escolherFonte = (familia: string) => {
        const doNovo = pesosDaFamilia(fontes, pesos, familia, campo.peso_fonte);

        atualizar({
            fonte_familia: familia,
            peso_fonte: pesoMaisProximo(doNovo, campo.peso_fonte),
        });
    };

    return (
        <div className="space-y-3 rounded-lg border bg-card p-3">
            <p className="truncate text-xs font-medium text-muted-foreground">
                {campo.nome || 'Sem nome'}
            </p>

            <div className="flex items-center gap-2">
                <CampoDeTexto
                    rotulo="Nome do campo"
                    placeholder="Nome do campo"
                    valor={campo.nome}
                    obrigatorio
                    desabilitado={somenteLeitura}
                    aoConfirmar={(nome) => atualizar({ nome })}
                />
                <CampoDeTexto
                    rotulo="Rótulo"
                    placeholder={campo.nome || 'Rótulo'}
                    valor={campo.label ?? ''}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(label) =>
                        atualizar({ label: label === '' ? null : label })
                    }
                />
                <Toggle
                    size="sm"
                    pressed={campo.centralizado}
                    disabled={somenteLeitura}
                    onPressedChange={(centralizado) =>
                        atualizar({ centralizado })
                    }
                    aria-label="Centralizar horizontalmente"
                    title="Centralizar horizontalmente"
                >
                    <AlignCenter />
                </Toggle>
                {!somenteLeitura && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-destructive"
                        aria-label="Excluir campo"
                        onClick={() => aoExcluir(campo.id)}
                    >
                        <Trash2 />
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-4 gap-2">
                <CampoNumerico
                    rotulo="X"
                    valor={campo.x}
                    min={0}
                    max={10000}
                    desabilitado={somenteLeitura || campo.centralizado}
                    aoConfirmar={(x) => atualizar({ x: Math.round(x) })}
                />
                <CampoNumerico
                    rotulo="Y"
                    valor={campo.y}
                    min={0}
                    max={10000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(y) => atualizar({ y: Math.round(y) })}
                />
                <CampoNumerico
                    rotulo="Fonte (px)"
                    valor={campo.tamanho_fonte}
                    min={4}
                    max={1000}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(tamanho) =>
                        atualizar({ tamanho_fonte: Math.round(tamanho) })
                    }
                />
                <CampoNumerico
                    rotulo="Espaç. (px)"
                    valor={campo.espacamento_letras}
                    min={-50}
                    max={200}
                    passo={0.5}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(espacamento) =>
                        atualizar({ espacamento_letras: espacamento })
                    }
                />
            </div>

            <div className="grid grid-cols-3 gap-2">
                <CampoDeCor
                    valor={campo.cor}
                    desabilitado={somenteLeitura}
                    aoConfirmar={(cor) => atualizar({ cor })}
                />
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] text-muted-foreground">
                        Tipografia
                    </Label>
                    <SeletorDeFonte
                        valor={campo.fonte_familia}
                        fontes={fontes}
                        desabilitado={somenteLeitura}
                        aoEscolher={escolherFonte}
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <Label className="text-[10px] text-muted-foreground">
                        Peso
                    </Label>
                    <Select
                        value={String(
                            pesoMaisProximo(pesosDaFonte, campo.peso_fonte),
                        )}
                        disabled={somenteLeitura}
                        onValueChange={(peso) =>
                            atualizar({ peso_fonte: Number(peso) })
                        }
                    >
                        <SelectTrigger className="h-8 w-full text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {pesosDaFonte.map((peso) => (
                                <SelectItem key={peso} value={String(peso)}>
                                    {rotuloDoPeso(peso)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Preview com arraste**

`resources/js/components/campos/preview-do-modelo.tsx`:

```tsx
import { useEffect, useRef, useState } from 'react';
import { escalaDoPreview } from '@/lib/editor';
import type { Campo, ModeloBasico } from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    somenteLeitura: boolean;
    arrastando: string | null;
    aoIniciarArraste: (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => void;
};

/**
 * A imagem do modelo com o texto de cada campo por cima. O campo centralizado usa `left: 50%` e
 * `translateX(-50%)`: o navegador centraliza a caixa do próprio texto, que inclui o espaçamento depois
 * da última letra, igual ao que o servidor faz ao gerar o PNG.
 */
export default function PreviewDoModelo({
    modelo,
    campos,
    somenteLeitura,
    arrastando,
    aoIniciarArraste,
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const [largura, setLargura] = useState(0);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(
        null,
    );

    useEffect(() => {
        const elemento = container.current;

        if (!elemento) {
            return;
        }

        const observador = new ResizeObserver(([entrada]) =>
            setLargura(entrada.contentRect.width),
        );
        observador.observe(elemento);

        return () => observador.disconnect();
    }, []);

    const escala = escalaDoPreview(largura, natural?.w ?? 0);

    return (
        <div
            ref={container}
            className="relative w-full overflow-hidden rounded-lg border select-none"
            style={
                natural
                    ? { height: largura * (natural.h / natural.w) }
                    : { aspectRatio: '4 / 3' }
            }
        >
            <img
                src={modelo.imagem_url}
                alt={modelo.title}
                draggable={false}
                className="size-full object-contain"
                onLoad={(evento) =>
                    setNatural({
                        w: evento.currentTarget.naturalWidth,
                        h: evento.currentTarget.naturalHeight,
                    })
                }
            />

            {natural &&
                campos.map((campo) => {
                    const emArraste = arrastando === campo.id;

                    return (
                        <span
                            key={campo.id}
                            onPointerDown={
                                somenteLeitura
                                    ? undefined
                                    : (evento) =>
                                          aoIniciarArraste(
                                              evento,
                                              campo,
                                              escala,
                                          )
                            }
                            style={{
                                position: 'absolute',
                                left: campo.centralizado
                                    ? '50%'
                                    : campo.x * escala,
                                transform: campo.centralizado
                                    ? 'translateX(-50%)'
                                    : undefined,
                                top: campo.y * escala,
                                color: campo.cor,
                                fontSize: campo.tamanho_fonte * escala,
                                fontFamily: `"${campo.fonte_familia}"`,
                                fontWeight: campo.peso_fonte,
                                letterSpacing: `${campo.espacamento_letras * escala}px`,
                                lineHeight: 1,
                                whiteSpace: 'nowrap',
                                userSelect: 'none',
                                touchAction: 'none',
                                pointerEvents: somenteLeitura ? 'none' : 'auto',
                                cursor: somenteLeitura
                                    ? 'default'
                                    : campo.centralizado
                                      ? emArraste
                                          ? 'ns-resize'
                                          : 'row-resize'
                                      : emArraste
                                        ? 'grabbing'
                                        : 'grab',
                                outline: emArraste
                                    ? '2px dashed rgba(255,255,255,0.7)'
                                    : 'none',
                                outlineOffset: 4,
                                borderRadius: 2,
                            }}
                        >
                            {campo.label?.trim() || campo.nome}
                        </span>
                    );
                })}
        </div>
    );
}
```

- [ ] **Step 5: O editor**

`resources/js/components/campos/campos-editor.tsx`:

```tsx
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CampoCertificadoController';
import CartaoDoCampo from '@/components/campos/cartao-do-campo';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import { moverCampo, valoresDoNovoCampo } from '@/lib/editor';
import { enviarJson, mensagemDoErro } from '@/lib/http';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    camposIniciais: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
    somenteLeitura: boolean;
};

export default function CamposEditor({
    modelo,
    camposIniciais,
    fontes,
    pesos,
    somenteLeitura,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);

    const [campos, setCampos] = useState<Campo[]>(camposIniciais);
    const [arrastando, setArrastando] = useState<string | null>(null);
    const [salvando, setSalvando] = useState(0);

    /** Aplica a mudança na tela na hora e salva; se o servidor recusar, volta ao valor anterior. */
    const atualizar = async (id: string, mudancas: Partial<Campo>) => {
        const anterior = campos.find((campo) => campo.id === id);

        setCampos((atuais) =>
            atuais.map((campo) =>
                campo.id === id ? { ...campo, ...mudancas } : campo,
            ),
        );
        setSalvando((n) => n + 1);

        try {
            await enviarJson(
                'PATCH',
                update.url({ modelo: modelo.id, campo: id }),
                mudancas,
            );
        } catch (erro) {
            toast.error(mensagemDoErro(erro));

            if (anterior) {
                const reverso = Object.fromEntries(
                    Object.keys(mudancas).map((chave) => [
                        chave,
                        anterior[chave as keyof Campo],
                    ]),
                );

                setCampos((atuais) =>
                    atuais.map((campo) =>
                        campo.id === id ? { ...campo, ...reverso } : campo,
                    ),
                );
            }
        } finally {
            setSalvando((n) => n - 1);
        }
    };

    const criar = async () => {
        try {
            const criado = await enviarJson<Campo>(
                'POST',
                store.url({ modelo: modelo.id }),
                valoresDoNovoCampo(campos.at(-1)),
            );

            if (criado) {
                setCampos((atuais) => [...atuais, criado]);
            }
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
        }
    };

    const excluir = async (id: string) => {
        const antes = campos;

        setCampos((atuais) => atuais.filter((campo) => campo.id !== id));

        try {
            await enviarJson(
                'DELETE',
                destroy.url({ modelo: modelo.id, campo: id }),
            );
        } catch (erro) {
            toast.error(mensagemDoErro(erro));
            setCampos(antes);
        }
    };

    const iniciarArraste = (
        evento: React.PointerEvent,
        campo: Campo,
        escala: number,
    ) => {
        evento.preventDefault();

        const inicio = {
            mouseX: evento.clientX,
            mouseY: evento.clientY,
            x: campo.x,
            y: campo.y,
        };
        const novaPosicao = (movimento: PointerEvent) =>
            moverCampo(
                { x: inicio.x, y: inicio.y },
                {
                    dx: movimento.clientX - inicio.mouseX,
                    dy: movimento.clientY - inicio.mouseY,
                },
                escala,
                campo.centralizado,
            );

        setArrastando(campo.id);

        const aoMover = (movimento: PointerEvent) => {
            const posicao = novaPosicao(movimento);

            setCampos((atuais) =>
                atuais.map((atual) =>
                    atual.id === campo.id ? { ...atual, ...posicao } : atual,
                ),
            );
        };

        const aoSoltar = (movimento: PointerEvent) => {
            window.removeEventListener('pointermove', aoMover);
            window.removeEventListener('pointerup', aoSoltar);
            setArrastando(null);
            void atualizar(campo.id, novaPosicao(movimento));
        };

        window.addEventListener('pointermove', aoMover);
        window.addEventListener('pointerup', aoSoltar);
    };

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div className="flex flex-col gap-4">
                <div>
                    <h2 className="text-lg font-semibold">Campos</h2>
                    <p className="text-sm text-muted-foreground">
                        Configure os campos de texto do certificado: posição,
                        cor e fonte.
                    </p>
                </div>

                {somenteLeitura && (
                    <Alert>
                        <AlertDescription>
                            Você pode ver os campos deste modelo, mas não
                            editá-los.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-3">
                    {campos.map((campo) => (
                        <CartaoDoCampo
                            key={campo.id}
                            campo={campo}
                            fontes={fontes}
                            pesos={pesos}
                            somenteLeitura={somenteLeitura}
                            aoAtualizar={(id, mudancas) =>
                                void atualizar(id, mudancas)
                            }
                            aoExcluir={(id) => void excluir(id)}
                        />
                    ))}
                </div>

                {!somenteLeitura && (
                    <button
                        type="button"
                        onClick={() => void criar()}
                        className="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                    >
                        <Plus className="size-4" />
                        Novo campo
                    </button>
                )}
            </div>

            <div className="flex flex-col gap-3">
                <div>
                    <h2 className="text-lg font-semibold">Preview</h2>
                    <p className="text-sm text-muted-foreground">
                        {somenteLeitura
                            ? 'Veja como os campos ficam sobre a imagem.'
                            : 'Arraste os campos para reposicioná-los sobre a imagem.'}
                    </p>
                </div>

                <PreviewDoModelo
                    modelo={modelo}
                    campos={campos}
                    somenteLeitura={somenteLeitura}
                    arrastando={arrastando}
                    aoIniciarArraste={iniciarArraste}
                />

                {!somenteLeitura && (
                    <p
                        className="text-right text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {salvando > 0
                            ? 'Salvando…'
                            : 'Alterações salvas automaticamente'}
                    </p>
                )}
            </div>
        </div>
    );
}
```

- [ ] **Step 6: A página**

`resources/js/pages/meus-certificados/campos.tsx` (substitua):

```tsx
import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import CamposEditor from '@/components/campos/campos-editor';
import { index } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    pode_editar: boolean;
    fontes: GruposFontes;
    pesos_das_fontes: PesosDasFontes;
};

export default function Campos({
    modelo,
    campos,
    pode_editar,
    fontes,
    pesos_das_fontes,
}: Props) {
    return (
        <>
            <Head title={`Campos: ${modelo.title}`} />
            <div className="p-4">
                <CabecalhoDaPagina
                    modelo={modelo.title}
                    titulo="Campos do certificado"
                    subtitulo="Defina onde cada campo de texto aparece sobre a imagem do modelo."
                />
                <CamposEditor
                    key={modelo.id}
                    modelo={modelo}
                    camposIniciais={campos}
                    fontes={fontes}
                    pesos={pesos_das_fontes}
                    somenteLeitura={!pode_editar}
                />
            </div>
        </>
    );
}

Campos.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
```

- [ ] **Step 7: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js/components/campos resources/js/pages/meus-certificados/campos.tsx
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde. O React Compiler pode apontar leitura de `ref.current` na renderização ou efeitos suspeitos: o código acima só usa refs em efeitos e eventos; se o `check` reclamar de algo diferente, corrija sem mudar o comportamento descrito.

```bash
git add -A
git commit -m "feat: editor de campos com arrastar e soltar e salvamento automático

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Geração de certificados (manual e por Excel)

**Files:**

- Create: `resources/js/pages/meus-certificados/gerar.tsx`, `resources/js/components/gerar/{tabela-de-geracao,dialogo-de-importacao,painel-de-preview,gerar-certificados}.tsx`

**Interfaces:**

- Consumes: props `modelo: ModeloBasico`, `campos: Campo[]`, `fontes`, `pesos_das_fontes` (Task 1); `PreviewDoModelo` (Task 6); `lerPlanilha`, `criarPlanilhaModelo` (`@/lib/planilha`); `LinhaDeGeracao`, `novaLinha`, `editarCelula`, `removerLinha`, `marcarGerando`, `marcarPronto`, `marcarErro`, `substituirLinhas`, `urlsDasLinhas`, `gerarCertificado` (`@/lib/geracao`); `mensagemDoErro`; `useFontesDoCatalogo`; `CabecalhoDaPagina`; ação `store` de `@/actions/App/Http/Controllers/GeracaoCertificadoController` (`store.url({ modelo: id })`); rota `campos` de `@/routes/meus-certificados`.
- Produces: a página `meus-certificados/gerar` completa.

Comportamento (portado do app antigo):

- Tabela com uma linha por certificado e uma coluna por campo (cabeçalho = nome do campo; o campo de entrada mostra o rótulo como dica). Começa com uma linha vazia. "+ Adicionar linha", excluir linha e "Limpar".
- Por linha: "Gerar certificado" (com indicador de progresso enquanto gera); depois de pronto vira "Baixar"; se falhar, "Tentar novamente" e a mensagem de erro do servidor. Ao gerar, o PNG baixa sozinho e abre no painel de preview. Editar uma linha pronta a deixa pronta para gerar de novo.
- "Importar dados": diálogo para arrastar ou escolher uma planilha `.xlsx`/`.xls` (as colunas têm o nome dos campos), com o botão "Baixar planilha modelo". A importação substitui as linhas.
- "Ver preview": painel lateral com a imagem do modelo e o nome/rótulo de cada campo no lugar certo (reutiliza `PreviewDoModelo` só para leitura). `Esc` e o clique fora fecham os painéis.
- Sem campos: aviso com o link "Configure os campos".
- O limite por minuto é tratado pela `gerarCertificado` (espera o `Retry-After` e repete).

- [ ] **Step 1: Tabela**

`resources/js/components/gerar/tabela-de-geracao.tsx`:

```tsx
import { Download, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { LinhaDeGeracao } from '@/lib/geracao';
import type { Campo } from '@/types/certificados';

type Props = {
    campos: Campo[];
    linhas: LinhaDeGeracao[];
    aoEditar: (id: number, nome: string, valor: string) => void;
    aoGerar: (id: number) => void;
    aoBaixar: (id: number) => void;
    aoRemover: (id: number) => void;
};

export default function TabelaDeGeracao({
    campos,
    linhas,
    aoEditar,
    aoGerar,
    aoBaixar,
    aoRemover,
}: Props) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-px whitespace-nowrap">
                        Ação
                    </TableHead>
                    {campos.map((campo) => (
                        <TableHead key={campo.id}>{campo.nome}</TableHead>
                    ))}
                    <TableHead className="w-px">
                        <span className="sr-only">Excluir linha</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {linhas.map((linha) => (
                    <TableRow key={linha.id}>
                        <TableCell className="align-top whitespace-nowrap">
                            {linha.status === 'pronto' ? (
                                <Button
                                    size="sm"
                                    onClick={() => aoBaixar(linha.id)}
                                >
                                    <Download />
                                    Baixar
                                </Button>
                            ) : linha.status === 'erro' ? (
                                <>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => aoGerar(linha.id)}
                                    >
                                        Tentar novamente
                                    </Button>
                                    {linha.erro && (
                                        <p className="mt-1 max-w-48 text-xs whitespace-normal text-destructive">
                                            {linha.erro}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <Button
                                    size="sm"
                                    disabled={linha.status === 'gerando'}
                                    onClick={() => aoGerar(linha.id)}
                                >
                                    {linha.status === 'gerando' && <Spinner />}
                                    Gerar certificado
                                </Button>
                            )}
                        </TableCell>
                        {campos.map((campo) => (
                            <TableCell key={campo.id} className="align-top">
                                <Input
                                    className="h-8 min-w-32"
                                    aria-label={
                                        campo.label?.trim() || campo.nome
                                    }
                                    placeholder={
                                        campo.label?.trim() || campo.nome
                                    }
                                    value={linha.valores[campo.nome] ?? ''}
                                    disabled={linha.status === 'gerando'}
                                    onChange={(evento) =>
                                        aoEditar(
                                            linha.id,
                                            campo.nome,
                                            evento.target.value,
                                        )
                                    }
                                />
                            </TableCell>
                        ))}
                        <TableCell className="align-top">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground hover:text-destructive"
                                aria-label="Excluir linha"
                                disabled={linha.status === 'gerando'}
                                onClick={() => aoRemover(linha.id)}
                            >
                                <Trash2 />
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
```

- [ ] **Step 2: Diálogo de importação e painel de preview**

`resources/js/components/gerar/dialogo-de-importacao.tsx`:

```tsx
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type Props = {
    aberto: boolean;
    aoFechar: () => void;
    aoEscolherArquivo: (arquivo: File) => void;
    aoBaixarModelo: () => void;
};

const EXTENSOES_ACEITAS = ['.xlsx', '.xls'];

export default function DialogoDeImportacao({
    aberto,
    aoFechar,
    aoEscolherArquivo,
    aoBaixarModelo,
}: Props) {
    const [arrastando, setArrastando] = useState(false);

    const aceitar = (arquivo: File | undefined) => {
        if (
            arquivo &&
            EXTENSOES_ACEITAS.some((extensao) =>
                arquivo.name.toLowerCase().endsWith(extensao),
            )
        ) {
            aoEscolherArquivo(arquivo);
        }
    };

    return (
        <Dialog open={aberto} onOpenChange={(abrir) => !abrir && aoFechar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Importar dados</DialogTitle>
                    <DialogDescription>
                        Cada linha da planilha vira um certificado. As colunas
                        precisam ter o nome dos campos do modelo.
                    </DialogDescription>
                </DialogHeader>

                <div
                    className={cn(
                        'flex flex-col items-center gap-4 rounded-lg border-2 border-dashed p-10 transition-colors',
                        arrastando
                            ? 'border-primary bg-primary/10'
                            : 'border-border',
                    )}
                    onDragOver={(evento) => {
                        evento.preventDefault();
                        setArrastando(true);
                    }}
                    onDragLeave={(evento) => {
                        evento.preventDefault();
                        setArrastando(false);
                    }}
                    onDrop={(evento) => {
                        evento.preventDefault();
                        setArrastando(false);
                        aceitar(evento.dataTransfer.files[0]);
                    }}
                >
                    <p className="text-center text-muted-foreground">
                        {arrastando
                            ? 'Solte o arquivo aqui'
                            : 'Arraste um arquivo Excel aqui ou clique para selecionar'}
                    </p>
                    <Button asChild>
                        <label className="cursor-pointer">
                            Selecionar arquivo
                            <input
                                type="file"
                                accept=".xlsx,.xls"
                                className="hidden"
                                onChange={(evento) => {
                                    aceitar(evento.target.files?.[0]);
                                    evento.target.value = '';
                                }}
                            />
                        </label>
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Formatos aceitos: .xlsx, .xls
                    </p>
                </div>

                <DialogFooter className="sm:justify-between">
                    <Button variant="ghost" size="sm" onClick={aoBaixarModelo}>
                        <Download />
                        Baixar planilha modelo
                    </Button>
                    <Button variant="ghost" size="sm" onClick={aoFechar}>
                        Cancelar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

`resources/js/components/gerar/painel-de-preview.tsx`:

```tsx
import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Props = {
    aberto: boolean;
    aoFechar: () => void;
    titulo: string;
    rodape?: ReactNode;
    children: ReactNode;
};

export default function PainelDePreview({
    aberto,
    aoFechar,
    titulo,
    rodape,
    children,
}: Props) {
    return (
        <Sheet open={aberto} onOpenChange={(abrir) => !abrir && aoFechar()}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 sm:max-w-[480px]"
            >
                <SheetHeader className="border-b">
                    <SheetTitle>{titulo}</SheetTitle>
                    <SheetDescription className="sr-only">
                        Pré-visualização do certificado
                    </SheetDescription>
                </SheetHeader>
                <div className="flex flex-1 flex-col items-center justify-center overflow-auto p-4">
                    {children}
                </div>
                {rodape && <div className="border-t p-4">{rodape}</div>}
            </SheetContent>
        </Sheet>
    );
}
```

- [ ] **Step 3: O componente principal**

`resources/js/components/gerar/gerar-certificados.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { Download, Eye, FileUp, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/GeracaoCertificadoController';
import PreviewDoModelo from '@/components/campos/preview-do-modelo';
import DialogoDeImportacao from '@/components/gerar/dialogo-de-importacao';
import PainelDePreview from '@/components/gerar/painel-de-preview';
import TabelaDeGeracao from '@/components/gerar/tabela-de-geracao';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useFontesDoCatalogo } from '@/hooks/use-fontes-do-catalogo';
import {
    editarCelula,
    gerarCertificado,
    marcarErro,
    marcarGerando,
    marcarPronto,
    novaLinha,
    removerLinha,
    substituirLinhas,
    urlsDasLinhas,
    type LinhaDeGeracao,
} from '@/lib/geracao';
import { mensagemDoErro } from '@/lib/http';
import { criarPlanilhaModelo, lerPlanilha } from '@/lib/planilha';
import { campos as rotaDeCampos } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    fontes: GruposFontes;
    pesos: PesosDasFontes;
};

function baixarArquivo(url: string, nome: string) {
    const ancora = document.createElement('a');
    ancora.href = url;
    ancora.download = nome;
    document.body.appendChild(ancora);
    ancora.click();
    ancora.remove();
}

function revogar(linha: LinhaDeGeracao | undefined) {
    if (linha?.arquivo) {
        URL.revokeObjectURL(linha.arquivo.url);
    }
}

export default function GerarCertificados({
    modelo,
    campos,
    fontes,
    pesos,
}: Props) {
    useFontesDoCatalogo(fontes, pesos);

    const [linhas, setLinhas] = useState<LinhaDeGeracao[]>(() => [
        novaLinha(1, campos),
    ]);
    const proximoId = useRef(2);
    const [previewDaLinha, setPreviewDaLinha] = useState<number | null>(null);
    const [previewDoModelo, setPreviewDoModelo] = useState(false);
    const [importando, setImportando] = useState(false);

    const linhasAtuais = useRef(linhas);

    useEffect(() => {
        linhasAtuais.current = linhas;
    });

    useEffect(
        () => () => {
            urlsDasLinhas(linhasAtuais.current).forEach((url) =>
                URL.revokeObjectURL(url),
            );
        },
        [],
    );

    const linhaEmPreview =
        linhas.find((linha) => linha.id === previewDaLinha) ?? null;

    const gerar = async (id: number) => {
        const linha = linhas.find((atual) => atual.id === id);

        if (!linha) {
            return;
        }

        revogar(linha);
        setPreviewDoModelo(false);
        setPreviewDaLinha(id);
        setLinhas((atuais) => marcarGerando(atuais, id));

        try {
            const arquivo = await gerarCertificado(
                store.url({ modelo: modelo.id }),
                linha.valores,
                {
                    nomePadrao: `${modelo.title}.png`,
                },
            );
            const url = URL.createObjectURL(arquivo.blob);

            setLinhas((atuais) =>
                marcarPronto(atuais, id, {
                    url,
                    nome: arquivo.nome,
                    tipo: arquivo.tipo,
                }),
            );
            baixarArquivo(url, arquivo.nome);
        } catch (erro) {
            setLinhas((atuais) => marcarErro(atuais, id, mensagemDoErro(erro)));
        }
    };

    const baixar = (id: number) => {
        const arquivo = linhas.find((linha) => linha.id === id)?.arquivo;

        if (arquivo) {
            baixarArquivo(arquivo.url, arquivo.nome);
        }
    };

    const editar = (id: number, nome: string, valor: string) => {
        revogar(linhas.find((linha) => linha.id === id));
        setLinhas((atuais) => editarCelula(atuais, id, nome, valor));
    };

    const adicionarLinha = () => {
        const id = proximoId.current++;

        setLinhas((atuais) => [...atuais, novaLinha(id, campos)]);
    };

    const remover = (id: number) => {
        revogar(linhas.find((linha) => linha.id === id));
        setPreviewDaLinha((atual) => (atual === id ? null : atual));
        setLinhas((atuais) => removerLinha(atuais, id));
    };

    const limpar = () => {
        urlsDasLinhas(linhas).forEach((url) => URL.revokeObjectURL(url));
        setPreviewDaLinha(null);
        setLinhas([novaLinha(proximoId.current++, campos)]);
    };

    const importar = async (arquivo: File) => {
        try {
            const importadas = lerPlanilha(await arquivo.arrayBuffer(), campos);

            if (importadas.length === 0) {
                toast.error('A planilha não tem linhas de dados.');

                return;
            }

            urlsDasLinhas(linhas).forEach((url) => URL.revokeObjectURL(url));
            const primeiro = proximoId.current;
            proximoId.current += importadas.length;
            setLinhas(substituirLinhas(importadas, primeiro));
            setPreviewDaLinha(null);
            setImportando(false);
            toast.success(
                `${importadas.length} ${importadas.length === 1 ? 'linha importada' : 'linhas importadas'}.`,
            );
        } catch {
            toast.error(
                'Não foi possível ler a planilha. Confira se é um arquivo .xlsx ou .xls válido.',
            );
        }
    };

    const baixarPlanilhaModelo = () => {
        const bytes = criarPlanilhaModelo(campos);
        const url = URL.createObjectURL(
            new Blob([bytes], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            }),
        );

        baixarArquivo(url, `modelo-${modelo.title}.xlsx`);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    const fecharPreview = () => {
        setPreviewDaLinha(null);
        setPreviewDoModelo(false);
    };

    return (
        <div className="flex flex-col gap-4">
            {campos.length === 0 && (
                <Alert>
                    <AlertDescription>
                        Este modelo ainda não tem campos.{' '}
                        <Link
                            href={rotaDeCampos({ modelo: modelo.id })}
                            className="font-medium underline"
                        >
                            Configure os campos
                        </Link>{' '}
                        antes de gerar certificados.
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        setPreviewDoModelo(true);
                        setPreviewDaLinha(null);
                    }}
                >
                    <Eye />
                    Ver preview
                </Button>
                <div className="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => setImportando(true)}
                    >
                        <FileUp />
                        Importar dados
                    </Button>
                    <Button variant="destructive" size="sm" onClick={limpar}>
                        <Trash2 />
                        Limpar
                    </Button>
                </div>
            </div>

            <TabelaDeGeracao
                campos={campos}
                linhas={linhas}
                aoEditar={editar}
                aoGerar={(id) => void gerar(id)}
                aoBaixar={baixar}
                aoRemover={remover}
            />

            <button
                type="button"
                onClick={adicionarLinha}
                className="w-full rounded border border-dashed py-2 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary"
            >
                + Adicionar linha
            </button>

            <DialogoDeImportacao
                aberto={importando}
                aoFechar={() => setImportando(false)}
                aoEscolherArquivo={(arquivo) => void importar(arquivo)}
                aoBaixarModelo={baixarPlanilhaModelo}
            />

            <PainelDePreview
                aberto={previewDoModelo || previewDaLinha !== null}
                aoFechar={fecharPreview}
                titulo={
                    previewDoModelo
                        ? 'Preview do template'
                        : 'Preview do certificado'
                }
                rodape={
                    !previewDoModelo && linhaEmPreview?.status === 'pronto' ? (
                        <Button
                            className="w-full"
                            onClick={() => baixar(linhaEmPreview.id)}
                        >
                            <Download />
                            Baixar
                        </Button>
                    ) : undefined
                }
            >
                {previewDoModelo && (
                    <PreviewDoModelo
                        modelo={modelo}
                        campos={campos}
                        somenteLeitura
                        arrastando={null}
                        aoIniciarArraste={() => undefined}
                    />
                )}
                {!previewDoModelo && linhaEmPreview?.status === 'gerando' && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        Gerando certificado…
                    </p>
                )}
                {!previewDoModelo && linhaEmPreview?.status === 'erro' && (
                    <p className="text-sm text-destructive">
                        {linhaEmPreview.erro ?? 'Erro ao gerar o certificado.'}
                    </p>
                )}
                {!previewDoModelo &&
                    linhaEmPreview?.status === 'pronto' &&
                    linhaEmPreview.arquivo && (
                        <img
                            src={linhaEmPreview.arquivo.url}
                            alt="Preview do certificado"
                            className="max-h-full w-full rounded object-contain"
                        />
                    )}
            </PainelDePreview>
        </div>
    );
}
```

- [ ] **Step 4: A página**

`resources/js/pages/meus-certificados/gerar.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import GerarCertificados from '@/components/gerar/gerar-certificados';
import { index } from '@/routes/meus-certificados';
import type {
    Campo,
    GruposFontes,
    ModeloBasico,
    PesosDasFontes,
} from '@/types/certificados';

type Props = {
    modelo: ModeloBasico;
    campos: Campo[];
    fontes: GruposFontes;
    pesos_das_fontes: PesosDasFontes;
};

export default function Gerar({
    modelo,
    campos,
    fontes,
    pesos_das_fontes,
}: Props) {
    return (
        <>
            <Head title={`Gerar certificados: ${modelo.title}`} />
            <div className="p-4">
                <CabecalhoDaPagina
                    modelo={modelo.title}
                    titulo="Gerar certificados"
                />
                <GerarCertificados
                    key={modelo.id}
                    modelo={modelo}
                    campos={campos}
                    fontes={fontes}
                    pesos={pesos_das_fontes}
                />
            </div>
        </>
    );
}

Gerar.layout = {
    breadcrumbs: [
        {
            title: 'Meus certificados',
            href: index(),
        },
    ],
};
```

- [ ] **Step 5: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js/components/gerar resources/js/pages/meus-certificados/gerar.tsx
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde. Se o `tsc` reclamar do tipo de `href` em `<Link href={rotaDeCampos({ modelo: modelo.id })}>`, use `rotaDeCampos.url({ modelo: modelo.id })`.

```bash
git add -A
git commit -m "feat: geração de certificados com tabela, importação de Excel e preview

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Histórico

**Files:**

- Rewrite: `resources/js/pages/historico/index.tsx`
- Create: `resources/js/components/historico/{tabela-do-historico,dialogo-de-detalhes}.tsx`

**Interfaces:**

- Consumes: prop `geracoes: Geracao[]` (com `ordem_campos`, Task 1); `ordenarValores`, `resumoDosValores`, `formatarData` (`@/lib/historico`); `CabecalhoDaPagina`; rota `index` de `@/routes/historico`.
- Produces: a página `historico/index` completa.

Comportamento: título "Histórico" e subtítulo "Últimas 50 gerações de certificado". Tabela com as colunas Modelo, Campos (até 3 pares `campo: valor` separados por " · ", na ordem do modelo), Data e o botão "Detalhes", que abre um diálogo com o modelo, a data e todos os campos e valores. Sem gerações: "Nenhuma geração encontrada" com a explicação.

- [ ] **Step 1: Tabela e diálogo**

`resources/js/components/historico/tabela-do-historico.tsx`:

```tsx
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    formatarData,
    ordenarValores,
    resumoDosValores,
} from '@/lib/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracoes: Geracao[];
    aoVerDetalhes: (geracao: Geracao) => void;
};

export default function TabelaDoHistorico({ geracoes, aoVerDetalhes }: Props) {
    return (
        <Table>
            <TableHeader>
                <TableRow className="bg-primary hover:bg-primary">
                    <TableHead className="text-primary-foreground">
                        MODELO
                    </TableHead>
                    <TableHead className="text-primary-foreground">
                        CAMPOS
                    </TableHead>
                    <TableHead className="text-primary-foreground">
                        DATA
                    </TableHead>
                    <TableHead className="w-px">
                        <span className="sr-only">Detalhes</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {geracoes.map((geracao) => (
                    <TableRow key={geracao.id}>
                        <TableCell className="font-medium whitespace-nowrap">
                            {geracao.modelo_title}
                        </TableCell>
                        <TableCell>
                            <div className="max-w-sm truncate text-muted-foreground">
                                {resumoDosValores(
                                    ordenarValores(
                                        geracao.campos_valores,
                                        geracao.ordem_campos,
                                    ),
                                )}
                            </div>
                        </TableCell>
                        <TableCell className="whitespace-nowrap text-muted-foreground">
                            {formatarData(geracao.created_at)}
                        </TableCell>
                        <TableCell>
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => aoVerDetalhes(geracao)}
                            >
                                Detalhes
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
```

`resources/js/components/historico/dialogo-de-detalhes.tsx`:

```tsx
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatarData, ordenarValores } from '@/lib/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracao: Geracao | null;
    aoFechar: () => void;
};

export default function DialogoDeDetalhes({ geracao, aoFechar }: Props) {
    return (
        <Dialog
            open={geracao !== null}
            onOpenChange={(abrir) => !abrir && aoFechar()}
        >
            <DialogContent>
                {geracao && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{geracao.modelo_title}</DialogTitle>
                            <DialogDescription>
                                {formatarData(geracao.created_at)}
                            </DialogDescription>
                        </DialogHeader>
                        <dl className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto">
                            {ordenarValores(
                                geracao.campos_valores,
                                geracao.ordem_campos,
                            ).map(([nome, valor]) => (
                                <div
                                    key={nome}
                                    className="flex flex-col gap-0.5"
                                >
                                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        {nome}
                                    </dt>
                                    <dd className="text-sm">{valor}</dd>
                                </div>
                            ))}
                        </dl>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: A página**

`resources/js/pages/historico/index.tsx` (substitua):

```tsx
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import CabecalhoDaPagina from '@/components/cabecalho-da-pagina';
import DialogoDeDetalhes from '@/components/historico/dialogo-de-detalhes';
import TabelaDoHistorico from '@/components/historico/tabela-do-historico';
import { index } from '@/routes/historico';
import type { Geracao } from '@/types/certificados';

type Props = {
    geracoes: Geracao[];
};

export default function Historico({ geracoes }: Props) {
    const [selecionada, setSelecionada] = useState<Geracao | null>(null);

    return (
        <>
            <Head title="Histórico" />
            <div className="p-4">
                <CabecalhoDaPagina
                    titulo="Histórico"
                    subtitulo="Últimas 50 gerações de certificado"
                />

                {geracoes.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                        <p className="text-lg font-medium text-muted-foreground">
                            Nenhuma geração encontrada
                        </p>
                        <p className="text-sm text-muted-foreground">
                            As gerações de certificado aparecerão aqui depois
                            que você gerar o primeiro certificado.
                        </p>
                    </div>
                ) : (
                    <TabelaDoHistorico
                        geracoes={geracoes}
                        aoVerDetalhes={setSelecionada}
                    />
                )}

                <DialogoDeDetalhes
                    geracao={selecionada}
                    aoFechar={() => setSelecionada(null)}
                />
            </div>
        </>
    );
}

Historico.layout = {
    breadcrumbs: [
        {
            title: 'Histórico',
            href: index(),
        },
    ],
};
```

- [ ] **Step 3: Verificação e commit**

```bash
npm run build
npx tsc --noEmit
npx vp fmt resources/js/components/historico resources/js/pages/historico/index.tsx
npm run check
npx vp test run
php artisan test --compact
```

Expected: tudo verde.

```bash
git add -A
git commit -m "feat: histórico de gerações com detalhes

Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Verificação ponta a ponta no navegador (feita pelo controlador)

Os componentes não têm teste automatizado, então esta tarefa é a verificação real da interface. Quem coordena a execução faz isto com o navegador embutido; os consertos vão para um subagente.

- [ ] **Step 1: Preparar o ambiente e os dados**

```bash
docker compose up -d
npm run build
composer run dev       # em segundo plano
php artisan tinker --execute 'App\Models\User::factory()->create(["name" => "Dev E2E", "email" => "e2e@example.com"]); echo "ok";'
```

Gere uma imagem de fundo de teste (azul-escuro, 3500×3500) e crie, por `curl` (login + `POST /modelos` com a imagem + `POST /modelos/{id}/campos` com `{"nome":"Nome","fonte_familia":"Dancing Script","peso_fonte":700,"tamanho_fonte":220,"x":300,"y":1400,"cor":"#ffffff"}` e `{"nome":"Titulo","fonte_familia":"Cinzel","peso_fonte":600,"tamanho_fonte":160,"y":600,"cor":"#ffffff","centralizado":true,"espacamento_letras":6}`), os mesmos passos da verificação do Plano 2. Crie também um segundo modelo sem campos.

- [ ] **Step 2: Percorrer a interface**

Faça login em `http://localhost:8000/login` com o usuário de teste e confira, olhando cada tela (modo claro e escuro):

| Tela                                                              | O que conferir                                                                                                                                                                                                                                           |
| ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/` (visitante)                                                   | Hero, "Entrar" e "Criar conta", seção de recursos, prévia do certificado, tudo em português e com o bordô da marca.                                                                                                                                      |
| `/login`, `/register`                                             | Texto em português, botão "Continuar com o Google".                                                                                                                                                                                                      |
| `/meus-certificados`                                              | Os dois modelos nos cartões; "Adicionar novo modelo". O diálogo de opções do modelo sem campos mostra o aviso e "Gerar certificados" desabilitado.                                                                                                       |
| Compartilhar                                                      | Adicionar um e-mail, alternar "Pode editar", remover; e-mail repetido mostra o erro sob o campo.                                                                                                                                                         |
| Alterar / Excluir                                                 | Renomear; excluir mostra a confirmação.                                                                                                                                                                                                                  |
| `/meus-certificados/{id}/campos`                                  | Textos sobre a imagem, na fonte certa; arrastar um campo (o centralizado só na vertical) e recarregar: a posição ficou salva; trocar a fonte atualiza o seletor de peso; criar e excluir campo; valor inválido (por exemplo X negativo) volta com toast. |
| `/meus-certificados/{id}/gerar`                                   | Preencher uma linha e gerar: baixa o PNG, abre o preview e o botão vira "Baixar"; editar a linha a deixa gerável de novo; "Ver preview"; "Importar dados" com a planilha modelo baixada, preenchida e reimportada; limpar.                               |
| `/historico`                                                      | A geração aparece com os valores na ordem do modelo; "Detalhes" abre o diálogo.                                                                                                                                                                          |
| `/settings/profile`, `/settings/security`, `/settings/appearance` | Tudo em português; excluir conta pede a senha (ou o e-mail, se a conta for só do Google).                                                                                                                                                                |

Compare o certificado gerado com o preview do editor: o texto deve estar na mesma posição (a largura pode variar até ~3% em fontes cursivas, seção 7.4 do spec).

- [ ] **Step 3: Corrigir o que aparecer e limpar**

Cada problema encontrado vira um conserto por subagente, com teste ou verificação que o cubra. Ao terminar: exclua o usuário de teste (a exclusão de conta também apaga as imagens do bucket), pare o `composer run dev` e remova os arquivos temporários.

---

## Verificação final do Plano 3

- [ ] `php artisan test --compact`, `npx vp test run`, `npm run build`, `npx tsc --noEmit` e `npm run check` verdes.
- [ ] `grep -rn "heroui\|framer-motion\|@heroui" package.json resources/js` não encontra nada.
- [ ] A Task 9 percorrida sem pendências.
- [ ] `git status` limpo e a branch integrada em `main`.

## O que o Plano 4 (deploy) herda

- Build do frontend: `npm ci && npm run build` (Vite+); o SSR não é usado em produção (`build:ssr` não faz parte do build).
- Imagem PHP 8.5 com a extensão **imagick compilada contra ImageMagick 7** (as imagens de referência dos testes do Plano 2 valem só para o 7; o ImageMagick do Debian é o 6), `pdo_pgsql` e `docker/php/uploads.ini` (limites de 50 MB) carregado.
- `resources/fonts` e `lang/pt_BR*` vão na imagem (estão no repositório). `APP_LOCALE=pt_BR` no ambiente.
- Rodar a suíte inteira dentro da imagem de produção antes de publicar.
