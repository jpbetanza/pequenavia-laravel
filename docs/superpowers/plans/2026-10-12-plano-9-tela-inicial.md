# PequenaVia — Plano 9: tela inicial (salas, horários e assistente)

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Passos com checkbox (`- [ ]`).

**Objetivo:** o app deixa de abrir em "Modelos". Quem entra cai num **Início** com a agenda do dia, os pedidos pendentes, o que separar de equipamento e o campo do assistente. A página pública (`welcome`) passa a vender salas, horários e o assistente, com certificados como apoio.

**Arquitetura:** um controller invocável `InicioController` monta as props reaproveitando serviços que já existem (`CartaoDeSolicitacao`, e o painel "Hoje: separar e recolher" que sai de `EquipamentoController` para um serviço). O React reaproveita `PainelDeEquipamentosDeHoje`. O assistente ganha `perguntar(texto)` no contexto: abre o painel e manda a pergunta numa conversa nova.

**Tech stack:** Laravel 13, Inertia 3 + React 19 (React Compiler) + Tailwind 4 + shadcn, Wayfinder, Pest, Vitest (`vite-plus/test`), Postgres.

**Design de referência (fonte da verdade visual):**
- `docs/superpowers/design/inicio/Painel.dc.html` — Início depois de entrar (Task 2).
- `docs/superpowers/design/inicio/Inicio.dc.html` — página pública (Task 3).

São HTML com estilos inline. Traduza para Tailwind com os tokens do tema; não copie `style=""`.

## Restrições globais

- Texto visível em pt-BR, exatamente como no design quando o design tiver o texto. Inputs com `label`, botões só-ícone com `aria-label`, alvos ≥ 44 px.
- Site só modo claro: **nenhuma classe `dark:`**.
- Cores pelos tokens do tema (`bg-background`, `bg-card`, `bg-secondary`, `bg-mesa`, `text-muted-foreground`, `bg-primary`, `text-primary`, `bg-foreground`, `text-background`, `text-dourado`, `bg-aviso-fundo`, `text-aviso`, `bg-sucesso-fundo`, `text-sucesso`, `border-border`, `font-serif`…). Hex de espaço **só** via `corDoEspaco()` de `resources/js/lib/agenda/cores.ts` (style inline com o hex devolvido é permitido). O único token novo do plano é `dourado-claro` (Task 3).
- **Nenhuma dependência nova** (PHP ou JS). Nunca `dangerouslySetInnerHTML`.
- Toda rota nova em `auth` + `verified`. Tudo escopado ao usuário logado (`user_id`).
- Horas e datas da agenda no fuso `config('agendamentos.fuso')` (`America/Sao_Paulo`), nunca no UTC do app.
- Backend: `php artisan make:* --no-interaction`, PHPDoc com tipos, Pest com factories, test-first. `vendor/bin/pint --dirty --format agent` antes de cada commit.
- Frontend: rotas via Wayfinder (`@/routes`, `@/actions`); depois de criar rota rode `php artisan wayfinder:generate --with-form`. Lógica pura em `resources/js/lib/inicio/*.ts` com Vitest.
- **Ambiente:** trabalhe só em `/Users/jpbetanza/Projetos/pequenavia-laravel/.worktrees/tela-inicial`. Toda shell precisa de `export PATH="$HOME/.config/herd-lite/bin:$PATH"` e, para testes PHP, `export DB_DATABASE=pequenavia_test_inicio` (banco próprio deste worktree; outro trabalho usa o padrão ao mesmo tempo).
- **Verificação de cada task:** `npm run build`, `npx tsc --noEmit`, `npm run check` (se reclamar de formatação: `npx vp check --fix`), `npx vp test run`, `php artisan test --compact` — todos verdes.
- **Commits** em pt-BR; última linha (após linha em branco) `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`. Sem push.

## Review Focus

1. **Fuso:** "agora", "hoje", a situação de cada reserva e o "mês" dos certificados no fuso de São Paulo. Às 23h30 de São Paulo (02h30 UTC do dia seguinte) o Início ainda mostra o dia certo (teste na Task 1).
2. **Vazamento:** reservas, pedidos, equipamentos e certificados de outro usuário nunca aparecem (teste na Task 1).
3. **Pergunta do Início no StrictMode:** em dev o React monta, desmonta e remonta efeitos; a pergunta tem de ser enviada **uma vez** e não pode ser abortada pelo cleanup (Task 2).
4. **Regressão no painel de equipamentos:** a extração para serviço não muda a resposta de `agendamentos.equipamentos.index` (os testes existentes de `EquipamentoIndexTest` continuam verdes sem alteração).

---

## Task 1: Backend do Início

**Files:**
- Create: `app/Services/Agenda/PainelDeEquipamentosDeHoje.php`
- Modify: `app/Http/Controllers/Agendamentos/EquipamentoController.php` (usa o serviço; remove `painelDeHoje`, `movimento` e a constante)
- Create: `app/Http/Controllers/InicioController.php`
- Modify: `routes/web.php` (rota `inicio`; `dashboard` redireciona para ela)
- Modify: `tests/Feature/DashboardTest.php` (o redirecionamento agora vai para `inicio`)
- Create: `tests/Feature/InicioTest.php`

**Interfaces:**
- Produces: rota `GET /inicio` nome `inicio`, página Inertia `inicio` com as props abaixo (a Task 2 usa exatamente esses nomes):

```ts
type PropsDoInicio = {
    hoje: { data: string /* Y-m-d */; rotulo: string /* "sábado, 17 de outubro" */; agora: string /* HH:MM */; periodo: 'manha' | 'tarde' | 'noite' };
    reservas: {
        id: string; titulo: string; inicio: string /* HH:MM */; fim: string /* HH:MM */;
        espaco: { nome: string; cor: CorDoEspaco };
        grupo_nome: string | null;
        equipamentos: { nome: string; quantidade: number }[];
        situacao: 'encerrada' | 'em_andamento' | 'proxima';
    }[];
    solicitacoes: { total: number; primeiras: (CartaoDeSolicitacao & { espaco_nome: string | null; espaco_cor: CorDoEspaco | null })[] };
    equipamentos_hoje: PainelDeHoje; // mesmo formato de `hoje` em agendamentos/equipamentos
    certificados: { emitidos_no_mes: number; ultimo: { modelo: string; gerado_em: string /* ISO 8601 no fuso */ } | null };
};
```

Regras:
- `periodo`: hora local < 12 → `manha`; < 18 → `tarde`; senão `noite`.
- `situacao`: `fim <= agora` → `encerrada`; `inicio <= agora < fim` → `em_andamento`; senão `proxima`. Compare strings `HH:MM`.
- `reservas`: só as do dono na data de hoje (fuso), ordenadas por `inicio` e `titulo`. `equipamentos` por nome.
- `solicitacoes.total`: pendentes do dono. `primeiras`: até 3 pendentes, ordem `data`, `inicio`, cada uma `CartaoDeSolicitacao::montar()` + `espaco_nome`/`espaco_cor` (null sem espaço).
- `certificados.emitidos_no_mes`: `GeracaoCertificado` com `user_id` do dono e `created_at` entre o início e o fim do mês corrente **no fuso** (converta os limites para UTC antes de consultar). `ultimo`: a mais recente do dono (qualquer mês), `modelo` = `modelo_title`, `gerado_em` no fuso, ISO 8601.

- [ ] **Step 1: Extrair o painel de hoje para um serviço (refatoração protegida)**

Rode antes `php artisan test --compact tests/Feature/Agendamentos/EquipamentoIndexTest.php` e anote: verde.

Crie `app/Services/Agenda/PainelDeEquipamentosDeHoje.php` movendo **sem mudar comportamento** o método `painelDeHoje`, o método `movimento` e a constante `ANTECEDENCIA_DA_SEPARACAO_MIN` de `EquipamentoController`:

```php
<?php

namespace App\Services\Agenda;

use App\Models\Equipamento;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Lista de levar e recolher equipamentos do dia: cada reserva com equipamento vira dois movimentos.
 */
class PainelDeEquipamentosDeHoje
{
    /** Minutos antes do início em que o equipamento deve ser separado. */
    public const ANTECEDENCIA_DA_SEPARACAO_MIN = 15;

    /**
     * @return array{rotulo: string, movimentos: list<array<string, mixed>>}
     */
    public function montar(User $user, CarbonInterface $agora): array
    {
        // corpo idêntico ao antigo EquipamentoController::painelDeHoje, chamando $this->movimento(...)
    }

    /**
     * @return array<string, mixed>
     */
    private function movimento(Reserva $reserva, string $acao, string $horario): array
    {
        // corpo idêntico ao antigo EquipamentoController::movimento
    }
}
```

Em `EquipamentoController`, injete o serviço (`public function __construct(private readonly PainelDeEquipamentosDeHoje $painelDeHoje) {}` — se já houver construtor, acrescente o parâmetro) e troque `$this->painelDeHoje($request->user(), $agora)` por `$this->painelDeHoje->montar($request->user(), $agora)`. Remova imports que ficarem sem uso.

Rode de novo `EquipamentoIndexTest`: continua verde **sem mudar o teste**.

- [ ] **Step 2: Escrever os testes do Início (falhando)**

Crie `tests/Feature/InicioTest.php` (Pest, `RefreshDatabase` já vem do `Pest.php` — confira em `tests/Pest.php` como os outros testes de `tests/Feature/Agendamentos` fazem e siga igual). Use as factories existentes; leia `database/factories/ReservaFactory.php`, `EspacoFactory.php`, `SolicitacaoFactory.php`, `GeracaoCertificadoFactory.php`, `EquipamentoFactory.php` para saber os campos e estados. Casos obrigatórios:

```php
<?php

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\GeracaoCertificado;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // 17/10/2026 15h30 em São Paulo = 18h30 UTC
    $this->travelTo(CarbonImmutable::parse('2026-10-17 15:30', 'America/Sao_Paulo'));
});

it('exige login', function () {
    $this->get(route('inicio'))->assertRedirect(route('login'));
});

it('mostra o dia no fuso de São Paulo com período e agora', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inicio')
            ->where('hoje.data', '2026-10-17')
            ->where('hoje.rotulo', 'sábado, 17 de outubro')
            ->where('hoje.agora', '15:30')
            ->where('hoje.periodo', 'tarde'));
});

it('às 23h30 de São Paulo ainda é o mesmo dia e é noite', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-17 23:30', 'America/Sao_Paulo'));

    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hoje.data', '2026-10-17')
            ->where('hoje.periodo', 'noite'));
});

it('lista só as reservas de hoje do dono, em ordem, com situação', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Salão', 'cor' => 'bordo']);
    $projetor = Equipamento::factory()->for($user)->create(['nome' => 'Projetor', 'quantidade' => 2]);

    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Coral', 'data' => '2026-10-17', 'inicio' => '19:30', 'fim' => '21:00']);
    $agora = Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Crisma', 'data' => '2026-10-17', 'inicio' => '15:00', 'fim' => '17:00']);
    $agora->equipamentos()->attach($projetor->id, ['quantidade' => 1]);
    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Criança', 'data' => '2026-10-17', 'inicio' => '14:00', 'fim' => '15:30']);
    Reserva::factory()->for($user)->for($espaco)->create(['titulo' => 'Ontem', 'data' => '2026-10-16', 'inicio' => '15:00', 'fim' => '16:00']);

    $outro = User::factory()->create();
    Reserva::factory()->for($outro)->for(Espaco::factory()->for($outro))->create(['titulo' => 'Alheia', 'data' => '2026-10-17']);

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservas', 3)
            ->where('reservas.0.titulo', 'Criança')
            ->where('reservas.0.situacao', 'encerrada')
            ->where('reservas.1.titulo', 'Crisma')
            ->where('reservas.1.situacao', 'em_andamento')
            ->where('reservas.1.inicio', '15:00')
            ->where('reservas.1.espaco.nome', 'Salão')
            ->where('reservas.1.espaco.cor', 'bordo')
            ->where('reservas.1.equipamentos.0.nome', 'Projetor')
            ->where('reservas.1.equipamentos.0.quantidade', 1)
            ->where('reservas.2.titulo', 'Coral')
            ->where('reservas.2.situacao', 'proxima'));
});

it('traz o total de pedidos pendentes e só os 3 primeiros cartões', function () {
    $user = User::factory()->create();
    $espaco = Espaco::factory()->for($user)->create(['nome' => 'Capela', 'cor' => 'verde']);

    foreach (['2026-10-20', '2026-10-18', '2026-10-22', '2026-10-19'] as $data) {
        Solicitacao::factory()->for($user)->create(['espaco_id' => $espaco->id, 'data' => $data, 'inicio' => '19:00', 'fim' => '20:00']);
    }
    Solicitacao::factory()->for($user)->create(['status' => 'recusada']);
    Solicitacao::factory()->for(User::factory())->create();

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('solicitacoes.total', 4)
            ->has('solicitacoes.primeiras', 3)
            ->where('solicitacoes.primeiras.0.data', '2026-10-18')
            ->where('solicitacoes.primeiras.0.espaco_nome', 'Capela')
            ->where('solicitacoes.primeiras.0.espaco_cor', 'verde')
            ->has('solicitacoes.primeiras.0.estado'));
});

it('conta os certificados do mês no fuso e traz o último', function () {
    $user = User::factory()->create();

    // 30/09 22h em SP = 01/10 01h UTC: é setembro em São Paulo, não entra
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Setembro', 'created_at' => CarbonImmutable::parse('2026-09-30 22:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Batismo', 'created_at' => CarbonImmutable::parse('2026-10-16 10:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for($user)->create(['modelo_title' => 'Crisma', 'created_at' => CarbonImmutable::parse('2026-10-02 10:00', 'America/Sao_Paulo')]);
    GeracaoCertificado::factory()->for(User::factory())->create(['created_at' => CarbonImmutable::parse('2026-10-10 10:00', 'America/Sao_Paulo')]);

    $this->actingAs($user)
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificados.emitidos_no_mes', 2)
            ->where('certificados.ultimo.modelo', 'Batismo'));
});

it('sem certificado o último é nulo, e o painel de equipamentos vem no formato da tela de equipamentos', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('inicio'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificados.ultimo', null)
            ->where('equipamentos_hoje.rotulo', 'sábado, 17 de outubro')
            ->has('equipamentos_hoje.movimentos', 0));
});
```

Ajuste nomes de colunas/estados **só** se a factory real for diferente (ex.: se `status` for enum, `StatusSolicitacao::Recusada`; se `GeracaoCertificado` precisar de `modelo_id`, deixe a factory criar). Não enfraqueça as asserções.

Também mude `tests/Feature/DashboardTest.php`: o teste `test_authenticated_users_are_sent_to_their_certificates` vira `test_authenticated_users_are_sent_to_the_home` e espera `assertRedirect(route('inicio'))`.

Rode `php artisan test --compact tests/Feature/InicioTest.php tests/Feature/DashboardTest.php` → falham (rota `inicio` não existe).

- [ ] **Step 3: Controller e rota**

`php artisan make:controller InicioController --invokable --no-interaction`. Implementação:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusSolicitacao;
use App\Models\Equipamento;
use App\Models\GeracaoCertificado;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Agenda\CartaoDeSolicitacao;
use App\Services\Agenda\PainelDeEquipamentosDeHoje;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Início da secretaria: o dia nas salas, os pedidos pendentes, os equipamentos a separar e os certificados do mês.
 */
class InicioController extends Controller
{
    private const PEDIDOS_NO_INICIO = 3;

    public function __construct(
        private readonly CartaoDeSolicitacao $cartoes,
        private readonly PainelDeEquipamentosDeHoje $painelDeHoje,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $fuso = config('agendamentos.fuso');
        $agora = CarbonImmutable::now($fuso);
        $horaAtual = $agora->format('H:i');

        return Inertia::render('inicio', [
            'hoje' => [
                'data' => $agora->toDateString(),
                'rotulo' => $agora->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'),
                'agora' => $horaAtual,
                'periodo' => match (true) {
                    $agora->hour < 12 => 'manha',
                    $agora->hour < 18 => 'tarde',
                    default => 'noite',
                },
            ],
            'reservas' => $this->reservasDeHoje($user, $agora->toDateString(), $horaAtual),
            'solicitacoes' => $this->solicitacoes($user),
            'equipamentos_hoje' => $this->painelDeHoje->montar($user, $agora),
            'certificados' => $this->certificados($user, $agora),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reservasDeHoje(User $user, string $data, string $horaAtual): array
    {
        return Reserva::query()
            ->where('user_id', $user->id)
            ->where('data', $data)
            ->with(['espaco:id,nome,cor', 'grupo:id,nome', 'equipamentos' => fn ($consulta) => $consulta->orderBy('equipamentos.nome')])
            ->orderBy('inicio')
            ->orderBy('titulo')
            ->get()
            ->map(function (Reserva $reserva) use ($horaAtual) {
                $inicio = substr($reserva->inicio, 0, 5);
                $fim = substr($reserva->fim, 0, 5);

                return [
                    'id' => $reserva->id,
                    'titulo' => $reserva->titulo,
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'espaco' => ['nome' => $reserva->espaco->nome, 'cor' => $reserva->espaco->cor->value],
                    'grupo_nome' => $reserva->grupo?->nome,
                    'equipamentos' => $reserva->equipamentos
                        ->map(fn (Equipamento $equipamento) => ['nome' => $equipamento->nome, 'quantidade' => (int) $equipamento->pivot->quantidade])
                        ->values()
                        ->all(),
                    'situacao' => match (true) {
                        $fim <= $horaAtual => 'encerrada',
                        $inicio <= $horaAtual => 'em_andamento',
                        default => 'proxima',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, primeiras: list<array<string, mixed>>}
     */
    private function solicitacoes(User $user): array
    {
        $pendentes = Solicitacao::query()
            ->where('user_id', $user->id)
            ->where('status', StatusSolicitacao::Pendente->value);

        return [
            'total' => (clone $pendentes)->count(),
            'primeiras' => $pendentes
                ->with(['grupo:id,nome', 'espaco', 'equipamentos'])
                ->orderBy('data')
                ->orderBy('inicio')
                ->limit(self::PEDIDOS_NO_INICIO)
                ->get()
                ->map(fn (Solicitacao $solicitacao) => $this->cartoes->montar($solicitacao) + [
                    'espaco_nome' => $solicitacao->espaco?->nome,
                    'espaco_cor' => $solicitacao->espaco?->cor->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{emitidos_no_mes: int, ultimo: array{modelo: string, gerado_em: string}|null}
     */
    private function certificados(User $user, CarbonImmutable $agora): array
    {
        $ultimo = GeracaoCertificado::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return [
            'emitidos_no_mes' => GeracaoCertificado::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [
                    $agora->startOfMonth()->utc(),
                    $agora->endOfMonth()->utc(),
                ])
                ->count(),
            'ultimo' => $ultimo === null ? null : [
                'modelo' => $ultimo->modelo_title,
                'gerado_em' => $ultimo->created_at->setTimezone(config('agendamentos.fuso'))->toIso8601String(),
            ],
        ];
    }
}
```

Em `routes/web.php`, dentro do grupo `auth`+`verified`: troque a linha do `dashboard` por

```php
    Route::get('dashboard', fn () => to_route('inicio'))->name('dashboard');
    Route::get('inicio', InicioController::class)->name('inicio');
```

e adicione `use App\Http\Controllers\InicioController;` em ordem alfabética. Não mude `config/fortify.php` (`home` continua `/dashboard`, que redireciona).

Crie um placeholder mínimo `resources/js/pages/inicio.tsx` para o build não quebrar (a Task 2 substitui):

```tsx
export default function Inicio() {
    return null;
}
```

Rode `php artisan wayfinder:generate --with-form`.

- [ ] **Step 4: Verificar e commitar**

Testes da task verdes; depois a verificação completa das restrições globais. Commit: `feat: início da secretaria com o dia nas salas, pedidos, equipamentos e certificados`.

---

## Task 2: Página Início, menu e "perguntar ao assistente"

**Files:**
- Create: `resources/js/lib/inicio/resumo.ts`, `resources/js/lib/inicio/resumo.test.ts`
- Create: `resources/js/types/inicio.ts`
- Replace: `resources/js/pages/inicio.tsx`
- Create: `resources/js/components/inicio/saudacao-do-inicio.tsx`, `hoje-nas-salas.tsx`, `pedidos-do-inicio.tsx`, `atalho-de-certificados.tsx`
- Modify: `resources/js/hooks/use-assistente.tsx` (pergunta pendente)
- Modify: `resources/js/components/assistente/painel-do-assistente.tsx`, `resources/js/components/assistente/conversa-do-assistente.tsx` (consumir a pergunta)
- Modify: `resources/js/components/app-sidebar.tsx` (item "Início", ordem das seções, link do logo)

**Interfaces:**
- Consumes: props `PropsDoInicio` da Task 1 (copie o tipo para `resources/js/types/inicio.ts`, importando `CartaoDeSolicitacao`, `PainelDeHoje` de `@/types/agendamentos` e `CorDoEspaco` de `@/lib/agenda/cores`); rota `inicio` de `@/routes`.
- Produces: `useAssistente().perguntar(texto: string): void`.

- [ ] **Step 1: Funções puras com Vitest (test-first)**

`resources/js/lib/inicio/resumo.test.ts`:

```ts
import { describe, expect, it } from 'vite-plus/test';
import { primeiroNome, resumoDoDia, saudacao } from './resumo';

describe('saudacao', () => {
    it('usa o período e o primeiro nome', () => {
        expect(saudacao('manha', 'Ana Paula Souza')).toBe('Bom dia, Ana.');
        expect(saudacao('tarde', 'Ana')).toBe('Boa tarde, Ana.');
        expect(saudacao('noite', '  José  ')).toBe('Boa noite, José.');
    });

    it('sem nome, só a saudação', () => {
        expect(saudacao('tarde', '')).toBe('Boa tarde.');
    });
});

describe('primeiroNome', () => {
    it('pega a primeira palavra', () => {
        expect(primeiroNome('Maria Clara')).toBe('Maria');
    });
});

describe('resumoDoDia', () => {
    it('junta as partes com vírgula e "e", no plural certo', () => {
        expect(resumoDoDia({ reservas: 6, pedidos: 3, separar: 2 })).toBe(
            '6 reservas hoje, 3 pedidos esperando resposta e 2 equipamentos para separar.',
        );
    });

    it('usa o singular', () => {
        expect(resumoDoDia({ reservas: 1, pedidos: 1, separar: 1 })).toBe(
            '1 reserva hoje, 1 pedido esperando resposta e 1 equipamento para separar.',
        );
    });

    it('omite o que é zero', () => {
        expect(resumoDoDia({ reservas: 2, pedidos: 0, separar: 0 })).toBe('2 reservas hoje.');
        expect(resumoDoDia({ reservas: 0, pedidos: 3, separar: 0 })).toBe('3 pedidos esperando resposta.');
        expect(resumoDoDia({ reservas: 2, pedidos: 0, separar: 1 })).toBe(
            '2 reservas hoje e 1 equipamento para separar.',
        );
    });

    it('dia vazio', () => {
        expect(resumoDoDia({ reservas: 0, pedidos: 0, separar: 0 })).toBe(
            'Nenhuma reserva hoje e nenhum pedido esperando resposta.',
        );
    });
});
```

Implemente `resources/js/lib/inicio/resumo.ts` exportando:
- `type Periodo = 'manha' | 'tarde' | 'noite'`
- `primeiroNome(nome: string): string` (trim, primeira palavra, '' se vazio)
- `saudacao(periodo: Periodo, nome: string): string`
- `resumoDoDia(contagem: { reservas: number; pedidos: number; separar: number }): string`
- `equipamentosParaSeparar(painel: PainelDeHoje): number` — soma das `quantidade` dos itens **não marcados** dos movimentos com `acao === 'levar'`. Escreva também um teste para ela (um movimento `levar` com itens 1 não marcado + 2 não marcado + 1 marcado e um `recolher` com 5 → `3`).

`npx vp test run resources/js/lib/inicio` → verde.

- [ ] **Step 2: Pergunta pendente no contexto do assistente**

Em `use-assistente.tsx`, acrescente ao valor do contexto:

```ts
    /** Pergunta enviada de fora do painel (ex.: Início), ainda não entregue à conversa. */
    perguntaPendente: string | null;
    /** Abre o painel e manda a pergunta numa conversa nova. */
    perguntar: (texto: string) => void;
    /** A conversa avisa que pegou a pergunta. */
    consumirPergunta: () => void;
```

`perguntar` faz `trim`, ignora vazio, guarda em estado e chama `definir(true)`. `consumirPergunta` zera o estado.

Em `painel-do-assistente.tsx` (`ConteudoDoAssistente`): leia `perguntaPendente` e `consumirPergunta` do hook e adicione o estado `perguntaInicial: string | null`. Um `useEffect` com dependências `[perguntaPendente, enviando]`: se `perguntaPendente !== null && !enviando`, faça `setPerguntaInicial(perguntaPendente)`, `consumirPergunta()` e `abrirConversa(null)` (que já remonta a conversa nova pela `sessao`). Enquanto uma resposta estiver chegando (`enviando`), a pergunta espera. Passe `perguntaInicial={perguntaInicial}` e `aoPerguntaEnviada={() => setPerguntaInicial(null)}` para `ConversaDoAssistente`.

Em `conversa-do-assistente.tsx`: duas props novas, opcionais:

```ts
    /** Pergunta a enviar assim que esta conversa (nova) monta. */
    perguntaInicial?: string | null;
    aoPerguntaEnviada?: () => void;
```

Envio no mount **à prova de StrictMode** (o React em dev monta, desmonta e remonta; o cleanup de desmontagem chama `controleRef.current?.abort()`, então enviar direto no efeito seria abortado):

```ts
    useEffect(() => {
        if (!perguntaInicial) {
            return;
        }

        // Adia para depois do ciclo de montagem: no StrictMode o primeiro agendamento é cancelado
        // pelo cleanup e só o segundo envia, uma vez só e sem ser abortado.
        const agendado = window.setTimeout(() => {
            aoPerguntaEnviada?.();
            void enviar(perguntaInicial);
        }, 0);

        return () => window.clearTimeout(agendado);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- só ao montar a conversa nova
    }, []);
```

(Se o lint do projeto não usar essa regra, remova o comentário de disable.)

- [ ] **Step 3: Página e componentes**

Traduza `docs/superpowers/design/inicio/Painel.dc.html` (só a `<main>`; a barra lateral é a do app) para `pages/inicio.tsx` + os componentes listados. `<Head title="Início" />`. Layout: o `AppLayout` já entra automaticamente (veja `resources/js/app.tsx`).

- **`SaudacaoDoInicio`** (cartão escuro `bg-foreground text-background rounded-[22px]`): rótulo da data com a primeira letra maiúscula (`hoje.rotulo`), título `saudacao(hoje.periodo, auth.user.name)` em `font-serif`, e `resumoDoDia({ reservas: reservas.length, pedidos: solicitacoes.total, separar: equipamentosParaSeparar(equipamentos_hoje) })`. À direita, **só se `usePage().props.assistente?.disponivel`**: formulário com `label` "Pergunte ao assistente" (ícone `Sparkles`), input com placeholder `Ex.: tem sala livre quinta à noite para 20 pessoas?`, botão de enviar só-ícone `aria-label="Enviar pergunta"` (ícone `ArrowUp`), e três sugestões (`O que tem amanhã?`, `Solicitações pendentes`, `Certificados emitidos este mês`). Enviar o form ou clicar numa sugestão chama `perguntar(texto)` e limpa o campo. Botão desabilitado com campo vazio.
- **`HojeNasSalas`**: título "Hoje nas salas" + link "Ver ocupação →" para `calendario({ query: { visao: 'dia' } })` **se** a rota do calendário aceitar essa query (confira em `ReservaController::calendario` / `CalendarioRequest`); senão, `calendario()`. Faixa "Agora" com a reserva `em_andamento` (se houver mais de uma, a primeira; se nenhuma, não mostra a faixa). Lista ordenada: horário `inicio–fim` (formato do design: `15h–17h`, `19h30–21h` — escreva `horaCurta('15:00') === '15h'`, `horaCurta('19:30') === '19h30'` em `lib/inicio/resumo.ts` com teste), barra de cor do espaço (`corDoEspaco(cor).forte` via `style`), título, espaço, e à direita: `Em andamento` (selo `corDoEspaco(cor).fundo`/`.texto`), `Encerrada` (texto `text-muted-foreground`, linha inteira esmaecida) ou o primeiro equipamento `Nome ×qtd`. Sem reservas: texto "Nenhuma reserva hoje." Rodapé: botões "Nova reserva" (primário; link para o calendário — confira se `calendario` aceita algum parâmetro para abrir o painel de nova reserva; se não, só o link) e "Abrir a semana" (outline, `calendario()`).
- **`PedidosDoInicio`**: título "Pedidos esperando você" + link `Ver todos (N) →` para `solicitacoes()` (`@/routes/agendamentos/solicitacoes` `index`). Linhas: quadrado de cor do espaço (cinza `bg-border` se sem espaço), `titulo` em negrito · `grupo_nome`, abaixo `espaco_nome ?? 'Sem espaço'` · data curta (`ter 20`) e horário (`19h–20h30`) — data curta via `Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: 'numeric', timeZone: 'UTC' })` sobre `${data}T00:00:00Z`, removendo o ponto. Selo à direita pelo `estado`: `conflito` → `N conflitos`/`1 conflito` (`bg-aviso-fundo text-aviso`); `sem_conflito` → `Sem conflito` (`bg-sucesso-fundo text-sucesso`); `escolha_um_espaco` → `Escolher espaço` (`bg-secondary`). Leia `CartaoDeSolicitacao` em `@/types/agendamentos` para o nome do campo da lista de conflitos. Zero pedidos: "Nenhum pedido esperando resposta." e o link vira "Ver solicitações →".
- **Separar e recolher**: reutilize `PainelDeEquipamentosDeHoje` de `@/components/agendamentos/painel-de-equipamentos-de-hoje` com `painel={equipamentos_hoje}`, dentro de um cartão com título "Separar e recolher" e link "Equipamentos →" (`@/routes/agendamentos/equipamentos` `index`). Não reescreva a lista.
- **`AtalhoDeCertificados`**: cartão com borda, título "Certificados", linha `N emitidos em <mês por extenso> · último: <modelo>, <hoje|ontem|dd/mm>` (só a primeira parte se `ultimo` for null; "Nenhum emitido em outubro" com zero), e botão outline "Gerar certificado" → `meusCertificados()` (`@/routes/meus-certificados` `index`).

Grade: saudação em largura total; abaixo duas colunas (`grid gap-6 lg:grid-cols-2 items-start`): esquerda `HojeNasSalas`; direita pedidos, separar e recolher, certificados empilhados. No celular, uma coluna.

- [ ] **Step 4: Menu**

Em `app-sidebar.tsx`:
- Antes do `NavMain`, um `SidebarMenu` (dentro de `SidebarGroup` com o mesmo espaçamento que o `NavMain` usa — confira `nav-main.tsx`) com um item "Início" (ícone `House`), `isActive` quando a URL atual for a de `inicio()` (use `useCurrentUrl`), `tooltip`, classe `h-11 gap-3 px-3 text-[15px] font-medium`, `Link` com `prefetch`.
- Em `secoesDoMenu`, **Agendamentos antes de Certificados**.
- O link do logo vai para `inicio()`.
- Teste PHP de menu (`tests/Feature/Agendamentos/MenuTest.php`) não deve precisar mudar; se algum teste Vitest checar a ordem das seções, atualize-o.

- [ ] **Step 5: Verificar e commitar**

Verificação completa. Commit: `feat: tela de início com agenda do dia, pedidos e pergunta ao assistente`.

---

## Task 3: Página pública nova

**Files:**
- Modify: `resources/css/app.css` (token `dourado-claro`)
- Replace: `resources/js/pages/welcome.tsx`
- Create: `resources/js/components/welcome/exemplo-do-dia.tsx`, `exemplo-de-pedido.tsx`, `exemplo-do-assistente.tsx`, `exemplo-de-certificado.tsx`

**Interfaces:**
- Consumes: `inicio()` de `@/routes` (Task 1), `corDoEspaco` de `@/lib/agenda/cores`.

- [ ] **Step 1: Token**

Em `resources/css/app.css`, ao lado de `--color-dourado: var(--dourado);` acrescente `--color-dourado-claro: var(--dourado-claro);` e, ao lado de `--dourado: #9a7428;`, `--dourado-claro: #d9b97a;` (dourado legível sobre fundo escuro).

- [ ] **Step 2: Página**

Traduza `docs/superpowers/design/inicio/Inicio.dc.html` inteiro para `welcome.tsx`, mantendo a estrutura do `welcome.tsx` atual (cabeçalho com logo em `bg-medalha`, `Head` com a fonte Pinyon Script, botões `Button` do shadcn com `asChild`, `auth.user` decide os destinos). Mudanças de comportamento:
- "Entrar" → `login()`; "Começar agora", "Organizar minha agenda" e "Criar conta gratuita" → `inicio()` se logado, senão `register()`. Logado, o cabeçalho mostra só um botão "Abrir o app" → `inicio()` (como o atual faz com o painel).
- `<Head title="Salas, horários e certificados da paróquia">`.
- Âncoras `#agenda`, `#assistente`, `#certificados` no menu.

As quatro ilustrações (agenda do sábado com a linha de agora, balão do assistente com cartão de reserva, cartão de pedido com conflitos, painel do assistente com lista de pedidos e cartão de novo espaço, certificado) viram componentes estáticos em `components/welcome/` — são figuras: `figure` com `aria-label` como no design, sem botões reais (use `span` com o visual de botão, como o design faz). Cores das salas por `corDoEspaco('bordo' | 'azul' | 'verde' | 'roxo')`. A seção do assistente usa `bg-foreground text-background`, rótulo e ícones em `text-dourado-claro`, textos secundários `text-background/75`. Rótulos uppercase pequenos fora da seção escura em `text-dourado`.

Layout responsivo: as grades do design usam `auto-fit/minmax`; em Tailwind use `grid gap-14 lg:grid-cols-2` (e `md:grid-cols-3` para os três passos). A figura do topo tem altura fixa no desktop; no celular (< `md`) o balão do assistente deixa de ser absoluto e fica abaixo da agenda, sem sobrepor. Nada pode causar rolagem horizontal em 375 px.

- [ ] **Step 3: Verificar e commitar**

`tests/Feature/TemaClaroTest.php` e `tests/Feature/ExampleTest.php` continuam verdes. Verificação completa. Commit: `feat: página inicial pública com salas, horários e assistente`.

---

## Task 4: Verificação no navegador (controlador)

- [ ] Servidor do worktree, login com usuário de teste, Início: saudação, resumo, lista do dia com "Agora", pedidos com selos, marcar entregue no painel de equipamentos, atalho de certificados.
- [ ] Perguntar pelo Início abre o painel numa conversa nova e envia **uma** mensagem (conferir na rede: um POST de conversa e um de mensagem).
- [ ] Menu: Início ativo, Agendamentos antes de Certificados, logo leva ao Início; `/dashboard` cai no Início.
- [ ] Página pública em 1440 e 375 px, deslogado e logado; sem rolagem horizontal; sem classes `dark:` (grep).
