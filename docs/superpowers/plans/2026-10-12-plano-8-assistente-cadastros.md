# PequenaVia — Plano 8: assistente cadastra, altera e exclui espaços, grupos e equipamentos

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Passos com checkbox (`- [ ]`).

**Objetivo:** o assistente de IA propõe criar, alterar ou excluir espaços (inclusive horários e bloqueios), grupos e equipamentos; nada é gravado até a secretaria clicar em **Confirmar** no cartão.

**Arquitetura:** reaproveita o mecanismo do plano 7 (`AcaoProposta` + cartão `acao_proposta` + `POST assistente/acoes/{acao}/confirmar|descartar`). As regras de validação e a gravação saem dos controllers para serviços compartilhados entre a tela e o assistente. Três ferramentas novas (`propor_espaco`, `propor_grupo`, `propor_equipamento`) com `operacao: criar|alterar|excluir`. A confirmação despacha pelo `tipo` da ação e recusa se o cadastro mudou desde a proposta.

**Tech stack:** Laravel 13 (Form Requests, Validator, policies), Inertia 3 + React 19 + Tailwind 4 + shadcn, Pest, Vitest (`vite-plus/test`), Postgres.

**Design aprovado (no chat, 2026-10-12):** ferramentas por cadastro com `operacao`; alterar é parcial (só os campos enviados mudam); horários do espaço mesclados dia a dia; bloqueios por adicionar/remover; exclusão bloqueada na proposta quando há reservas (mesmas mensagens da tela); excluir grupo avisa quantas reservas ficam sem grupo; cartão genérico com linhas antes → depois; confirmar recusa com 422 se o cadastro mudou desde a proposta.

## Restrições globais

- Texto visível em pt-BR; sem classes `dark:`; alvos ≥ 44 px; `aria-label` em botão só-ícone; tokens do tema; hex só via `resources/js/lib/agenda/cores.ts`; nunca `dangerouslySetInnerHTML`.
- Nenhuma dependência nova. Nenhum teste chama a rede (`Http::fake` + `Http::preventStrayRequests()` onde o cliente do OpenRouter participar).
- Toda ferramenta usa o **usuário logado**; alvo por nome ou id só entre os cadastros dele; cadastro de outro usuário → "Não encontrei …" (ArgumentoInvalido), nunca vazamento.
- **Nada é gravado sem o clique em Confirmar.** As ferramentas só criam `AcaoProposta`.
- Regras de negócio da tela valem igual no assistente (mesmas regras de validação e mesmas mensagens), sem duplicação de código.
- Backend: PHPDoc com tipos, mensagens pt-BR, Pest com factories, test-first; `vendor/bin/pint --dirty --format agent` antes de cada commit.
- **Verificação de cada task:** `npm run build`, `npx tsc --noEmit`, `npm run check` (`npx vp check --fix` se reclamar de formatação), `npx vp test run`, `php artisan test --compact` — todos verdes.
- Commits em pt-BR, última linha (após linha em branco) `Co-Authored-By: Claude Haiku 5.5 <noreply@anthropic.com>`. Sem push.

## Review Focus

1. **Alteração parcial apagando o que não foi pedido:** "mude a capacidade do salão para 150" não pode zerar horários, bloqueios, recursos ou regras (teste na Task 3 e na Task 2).
2. **Cadastro alterado entre a proposta e o clique** (na tela ou por outra proposta): Confirmar responde 422 "O cadastro mudou desde a proposta. Peça de novo." e nada é gravado (Task 2).
3. **Nome duplicado** ao criar ou renomear (único por dono): vira erro para o modelo na proposta e, se surgir depois, 422 na confirmação — nunca 500 (Tasks 2 e 3).
4. **Exclusão de espaço/equipamento com reservas** (passadas ou futuras): bloqueada já na proposta, com a mensagem da tela; e revalidada na confirmação (Tasks 2 e 3).
5. **Horários mesclados errado:** abrir um dia novo, fechar um dia, mudar só o fechamento de um dia — os outros dias permanecem; fechamento ≤ abertura vira erro (Task 3).

---

## Interfaces compartilhadas (fonte da verdade)

```php
// app/Services/Agenda/Cadastros/RegrasDeEspaco.php (e RegrasDeGrupo, RegrasDeEquipamento — mesma forma)
final class RegrasDeEspaco {
    /** Regras do Validator; o nome é único entre os cadastros do dono, ignorando $atual. @return array<string, mixed> */
    public static function regras(User $dono, ?Espaco $atual = null): array;
    /** @return array<string, string> mensagens pt-BR (as que hoje estão em StoreEspacoRequest::messages) */
    public static function mensagens(): array;
    /** Só espaço: regras que cruzam campos (fecha > abre; fim do bloqueio ≥ início). @param array<string, mixed> $dados  @return array<string, string> campo => mensagem */
    public static function errosCruzados(array $dados): array;
}

// app/Services/Agenda/Cadastros/GravadorDeEspaco.php
final class GravadorDeEspaco {
    /** @param array<string, mixed> $dados já validados (com horarios e bloqueios) */
    public function criar(User $dono, array $dados): Espaco;            // transação; cria horários e bloqueios
    /** @param array<string, mixed> $dados já validados e COMPLETOS (estado final) */
    public function atualizar(Espaco $espaco, array $dados): Espaco;    // transação; substitui horários e bloqueios
    /** @throws ValidationException com as mensagens atuais da tela (reservas futuras / reservas) */
    public function excluir(Espaco $espaco): void;
    /** Mensagem que impede excluir, ou null. Mesmo texto do excluir. */
    public function motivoParaNaoExcluir(Espaco $espaco): ?string;
}
// GravadorDeGrupo: criar(User, array): Grupo; atualizar(Grupo, array): Grupo; excluir(Grupo): void; reservasAfetadas(Grupo): int
// GravadorDeEquipamento: criar(User, array): Equipamento; atualizar(Equipamento, array): Equipamento; excluir(Equipamento): void; motivoParaNaoExcluir(Equipamento): ?string

// Tipos de AcaoProposta (coluna `tipo`, string(30)):
//   criar_reserva (já existe) | criar_espaco | alterar_espaco | excluir_espaco | criar_grupo | alterar_grupo | excluir_grupo
//   | criar_equipamento | alterar_equipamento | excluir_equipamento
// `dados` das ações de cadastro:
//   ['entidade' => 'espaco'|'grupo'|'equipamento', 'operacao' => 'criar'|'alterar'|'excluir',
//    'alvo_id' => ?string, 'alvo_atualizado_em' => ?string (ISO, updated_at do alvo na proposta),
//    'final' => array (estado final COMPLETO e validado; vazio na exclusão),
//    'linhas' => list<array{rotulo: string, antes: ?string, depois: ?string}>,
//    'avisos' => list<string>, 'titulo' => string]

// app/Services/Assistente/Cadastros/ExecutorDeCadastro.php
final class ExecutorDeCadastro {
    /** Revalida e grava a ação de cadastro; devolve o id do cadastro criado/alterado (null na exclusão).
     *  @throws \Symfony\Component\HttpKernel\Exception\HttpResponseException 422 com {message} quando mudou ou é inválida */
    public function executar(User $usuario, AcaoProposta $acao): ?string;
}
```

**Cartão `acao_proposta` (dados) — ganha `tipo`:**
- `tipo: 'criar_reserva'` → formato atual (`id, status, resumo{…}, resultado{reserva_id, data}|null`) + `tipo`.
- demais `tipo` → `{id, status, tipo, cadastro: {entidade, operacao, titulo, linhas: [{rotulo, antes, depois}], avisos: [string]}, resultado: {cadastro_id: string|null, entidade}|null}`.

---

### Task 1: Regras e gravação compartilhadas (refatoração protegida)

**Arquivos:** criar `app/Services/Agenda/Cadastros/{RegrasDeEspaco,RegrasDeGrupo,RegrasDeEquipamento,GravadorDeEspaco,GravadorDeGrupo,GravadorDeEquipamento}.php`; modificar `app/Http/Requests/Agendamentos/{Store,Update}{Espaco,Grupo,Equipamento}Request.php` e `app/Http/Controllers/Agendamentos/{Espaco,Grupo,Equipamento}Controller.php`; teste `tests/Feature/Agendamentos/GravadoresDeCadastroTest.php`.

- [ ] **Passo 1:** rode `php artisan test --compact tests/Feature/Agendamentos` e anote o resultado (verde). Essa é a rede de proteção.
- [ ] **Passo 2:** mova as regras de `Store*Request::regras()` e `messages()` para `Regras*::regras(User $dono, ?Model $atual)` / `mensagens()` (o `Rule::unique(...)->where('user_id', $dono->id)->ignore($atual?->id)` vai junto). Mova a checagem cruzada de `StoreEspacoRequest::withValidator` para `RegrasDeEspaco::errosCruzados(array $dados): array` (mesmas mensagens e chaves `horarios.{i}.fecha`, `bloqueios.{i}.fim`); o `withValidator` passa a fazer `foreach (RegrasDeEspaco::errosCruzados($this->all()) as $campo => $msg) $validador->errors()->add($campo, $msg)` — mantendo o "pular se já tem erro de formato" (passe os erros atuais ou faça `errosCruzados` ignorar valores fora do padrão HH:MM / Y-m-d). Os Requests delegam: `rules()` → `RegrasDe*::regras($this->user(), $this->route('…'))`.
- [ ] **Passo 3:** mova a gravação: `EspacoController::store/update/destroy` → `GravadorDeEspaco::criar/atualizar/excluir` (inclusive `atributos()` e `gravarHorariosEBloqueios()` e as duas mensagens de exclusão, com `motivoParaNaoExcluir()` usado pelo `excluir()`); `GrupoController` → `GravadorDeGrupo` (`reservasAfetadas(Grupo): int` = reservas com `grupo_id` do grupo, data ≥ hoje no fuso da agenda); `EquipamentoController` → `GravadorDeEquipamento`. Os controllers continuam com Gate, toast e redirect, e chamam os gravadores.
- [ ] **Passo 4:** rode `php artisan test --compact tests/Feature/Agendamentos` → mesmo resultado verde (sem mudar testes existentes).
- [ ] **Passo 5 (TDD):** `GravadoresDeCadastroTest.php` cobre direto os serviços: criar espaço com 2 horários e 1 bloqueio; atualizar substitui horários; `motivoParaNaoExcluir` para reserva futura, reserva passada e sem reservas (null); `RegrasDeEspaco::errosCruzados` com fecha ≤ abre e bloqueio fim < início; `GravadorDeGrupo::reservasAfetadas` conta só futuras do grupo; `GravadorDeEquipamento::motivoParaNaoExcluir` com reserva futura. Veja falhar (classes ainda não testadas diretamente → os testes devem passar só depois do Passo 3; escreva-os antes do Passo 3 se preferir RED real).
- [ ] **Passo 6:** verificação completa. Commit `refactor: regras e gravação de cadastros em serviços compartilhados`.

---

### Task 2: Ações de cadastro de grupo e equipamento + confirmação genérica

**Arquivos:** criar `app/Services/Assistente/Ferramentas/{ProporGrupo,ProporEquipamento}.php`, `app/Services/Assistente/Cadastros/{ExecutorDeCadastro,PropostaDeCadastro}.php`; modificar `app/Services/Assistente/ResumoDaAcao.php`, `app/Http/Controllers/Assistente/AcaoPropostaController.php`, `app/Providers/AppServiceProvider.php` (registrar as 2 ferramentas na `CaixaDeFerramentas`); testes `tests/Feature/Assistente/{ProporGrupoTest,ProporEquipamentoTest,ConfirmarCadastroTest}.php`.

**Interfaces — consome:** Task 1 (`Regras*`, `Gravador*`), `ResolvedorDeCadastros` (`grupo`, `equipamento` por id ou nome entre os do usuário; lança `ArgumentoInvalido`), `ValidaArgumentos`, `ArgumentoInvalido`, `ResultadoDeFerramenta`. **Produz:** `PropostaDeCadastro::criar(User, Conversa, string $entidade, string $operacao, ?Model $alvo, array $final, array $linhas, array $avisos, string $titulo): AcaoProposta` (expira as propostas pendentes da conversa — mesma regra de `ProporReserva` —, grava `tipo = "{$operacao}_{$entidade}"` e devolve a ação); `ExecutorDeCadastro::executar()`; `ResumoDaAcao::paraCartao()` com `tipo`.

- [ ] **Passo 1 (testes RED)** — `ProporGrupoTest.php` (com `config(['assistente.chave' => 'chave-de-teste'])` onde precisar):
  - criar: `{operacao: 'criar', nome: 'Pastoral da Juventude', coordenacao: 'Ana', ativo: true}` → 1 `AcaoProposta` `tipo = criar_grupo`, `Grupo::count() === 0`, cartão `tipo` `criar_grupo` com `cadastro.titulo` "Novo grupo: Pastoral da Juventude" e linha `{rotulo: 'Nome', antes: null, depois: 'Pastoral da Juventude'}`.
  - criar com nome que já existe para o usuário → `ArgumentoInvalido` com a mensagem de nome repetido das regras.
  - alterar parcial: grupo existente `{nome: 'Catequese', coordenacao: 'Ana', contato: '11 9999', ativo: true}`; `{operacao: 'alterar', alvo: 'catequese', coordenacao: 'Bia'}` → `final` mantém nome, contato e ativo; linhas só de "Coordenação" `Ana → Bia`.
  - alterar sem nenhum campo diferente → `ArgumentoInvalido('Nada para alterar: os valores informados já são os atuais.')`.
  - excluir grupo com 2 reservas futuras → proposta criada, `avisos` contém "2 reservas futuras ficarão sem grupo."
  - alvo de outro usuário → `ArgumentoInvalido` "Não encontrei o grupo …".
- `ProporEquipamentoTest.php`: criar (quantidade obrigatória; `quantidade: 0` → erro de validação); alterar `manutencao_ate` (linha "Manutenção até" `— → 20/10/2026`); excluir equipamento com reserva futura → `ArgumentoInvalido` com "Há reservas futuras usando este equipamento. Desative em vez de excluir." e nenhuma ação criada.
- `ConfirmarCadastroTest.php` (rotas `assistente.acoes.confirmar|descartar`):
  - confirmar `criar_grupo` cria o grupo do usuário e o cartão volta com `status: confirmada` e `resultado.cadastro_id`.
  - confirmar `alterar_grupo` aplica só a mudança.
  - **mudou desde a proposta:** proposta de alterar; depois `travel(1)->seconds()` e `$grupo->update(['contato' => 'outro'])`; confirmar → 422 `message` "O cadastro mudou desde a proposta. Peça de novo." e o grupo continua com `contato` 'outro'.
  - **nome passou a existir:** proposta de criar "Coral"; outro grupo "Coral" criado depois; confirmar → 422 (mensagem de nome repetido), nada criado.
  - confirmar `excluir_equipamento` sem reservas → excluído; com reserva criada depois da proposta → 422 com a mensagem da tela.
  - confirmar duas vezes → 422 "Esta ação não está mais pendente." (comportamento existente).
  - ação de outro usuário → 404 (existente; um caso de cadastro basta).
  - `criar_reserva` continua funcionando (rode `tests/Feature/Assistente/AcaoPropostaControllerTest.php`).
- [ ] **Passo 2:** rode → FALHA.
- [ ] **Passo 3: Implementação.**
  - **Ferramentas** (nomes `propor_grupo`, `propor_equipamento`; status "Preparando o cadastro…"; descrição diz que **não grava** e que a secretaria precisa confirmar; parâmetros: `operacao` enum, `alvo` (obrigatório em alterar/excluir), e os campos do cadastro — grupo: `nome, coordenacao, contato, observacao, ativo`; equipamento: `nome, descricao, quantidade, local_guarda, manutencao_ate (Y-m-d ou null)`).
  - **Algoritmo comum** (escreva-o uma vez num trait/classe base, ex.: `PropoeCadastro`, usado por todas as ferramentas de cadastro):
    1. `criar`: `final` = campos enviados; valida com `Validator::make($final, RegrasDeX::regras($usuario), RegrasDeX::mensagens())`; erro → `ArgumentoInvalido($validador->errors()->first())`.
    2. `alterar`: resolve o alvo; `atual` = atributos atuais do alvo nas chaves das regras; `final = array_replace($atual, $enviados)`; valida `final` com `regras($usuario, $alvo)`; `linhas` = só chaves em que `final[k] != atual[k]` (formatadas para leitura: booleanos "Sim/Não", datas `d/m/Y`, null "—"); sem linhas → `ArgumentoInvalido('Nada para alterar: os valores informados já são os atuais.')`.
    3. `excluir`: resolve o alvo; `motivoParaNaoExcluir()` não-null → `ArgumentoInvalido($motivo)`; grupo → `avisos` com `reservasAfetadas` (`"{n} reservas futuras ficarão sem grupo."`, só quando n > 0; singular "1 reserva futura ficará sem grupo.").
    4. `PropostaDeCadastro::criar(...)` com `alvo_atualizado_em = $alvo?->updated_at?->toIso8601String()`; título: "Novo grupo: X" / "Alterar grupo: X" / "Excluir grupo: X" (idem "espaço", "equipamento").
    5. `ResultadoDeFerramenta(['criada' => true, 'acao_id' => …, 'mensagem' => 'Proposta pronta. A secretaria precisa clicar em Confirmar.'], ['tipo' => 'acao_proposta', 'dados' => $resumo->paraCartao($acao)])`.
  - **`ExecutorDeCadastro::executar`**: carrega o alvo entre os do usuário (sumiu → 422 "O cadastro não existe mais."); compara `updated_at` com `alvo_atualizado_em` (diferente → 422 "O cadastro mudou desde a proposta. Peça de novo."); revalida `final` com as regras (erro → 422 com a primeira mensagem); para espaço revalida também `errosCruzados`; executa o `Gravador*` (exclusão: `excluir()` lança `ValidationException` → converta em 422 com a mensagem). Use `abort(response()->json(['message' => …], 422))` como o controller já faz.
  - **`AcaoPropostaController::confirmar`**: se `tipo === 'criar_reserva'`, fluxo atual; senão `ExecutorDeCadastro::executar()` dentro da mesma transação com `pendenteTravada`, gravando `resultado_id` (id do cadastro ou null) e `status = confirmada`.
  - **`ResumoDaAcao::paraCartao`**: acrescenta `tipo` em todos; para tipos de cadastro devolve `cadastro` (de `dados`: entidade, operacao, titulo, linhas, avisos) e `resultado` = `{cadastro_id: resultado_id, entidade}` quando confirmada.
  - Registrar `ProporGrupo` e `ProporEquipamento` na `CaixaDeFerramentas` (AppServiceProvider).
- [ ] **Passo 4:** testes → PASSAM (inclusive todos de `tests/Feature/Assistente`). Verificação completa. Commit `feat: assistente propõe cadastrar, alterar e excluir grupos e equipamentos`.

---

### Task 3: Ações de espaço, com horários e bloqueios

**Arquivos:** criar `app/Services/Assistente/Ferramentas/ProporEspaco.php`, `app/Services/Assistente/Cadastros/MescladorDeHorarios.php`; registrar na `CaixaDeFerramentas`; testes `tests/Feature/Assistente/ProporEspacoTest.php`, `tests/Unit/MescladorDeHorariosTest.php`, e casos em `ConfirmarCadastroTest.php`.

**Interfaces — consome:** Task 2 (algoritmo comum, `PropostaDeCadastro`, `ExecutorDeCadastro`), Task 1 (`RegrasDeEspaco`, `GravadorDeEspaco`).

- [ ] **Passo 1: `MescladorDeHorarios` (puro, sem banco) com teste unitário RED.**

```php
final class MescladorDeHorarios
{
    /** Dias em pt-BR (sem acento e minúsculos aceitos) → 0=domingo … 6=sábado. */
    public const DIAS = ['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6];

    /**
     * @param  list<array{dia_semana: int, abre: string, fecha: string}>  $atuais
     * @param  list<array{dia: string, abre?: string, fecha?: string, fechado?: bool}>  $mudancas
     * @return list<array{dia_semana: int, abre: string, fecha: string}>  ordenado por dia_semana
     * @throws ArgumentoInvalido dia desconhecido; abre/fecha ausentes sem `fechado`
     */
    public static function mesclar(array $atuais, array $mudancas): array;

    /** Linhas antes → depois por dia que mudou, ex.: ['rotulo' => 'Sábado', 'antes' => 'Fechado', 'depois' => '08:00–18:00']. */
    public static function linhas(array $atuais, array $finais): array;
}
```

Casos: abrir sábado mantém seg–sex; `fechado: true` remove o dia; mudar só `fecha` de um dia existente mantém `abre` (se `abre` ausente e o dia existe, usa o atual); dia novo sem `abre`/`fecha` → `ArgumentoInvalido('Informe abertura e fechamento de sábado.')`; aceita "Sábado", "sábado", "sabado", "SÁBADO"; `linhas` só dos dias alterados, rótulos com acento ("Terça", "Sábado").

- [ ] **Passo 2: testes RED de `ProporEspacoTest.php`:**
  - criar "Sala 3" capacidade 25, cor `azul`, horários seg–sex 08:00–22:00 (a ferramenta aceita `horarios: [{dia, abre, fecha}]`), sem gravar espaço; cartão com linhas Nome/Capacidade/Cor/Horários.
  - criar sem `cor` → usa `bordo` como padrão (o modelo não precisa perguntar cor).
  - alterar só capacidade de espaço com 5 horários, 1 bloqueio e recursos → `final` mantém horários, bloqueios, recursos e regras; linha só "Capacidade".
  - alterar `horarios: [{dia: 'sábado', abre: '08:00', fecha: '18:00'}]` → `final.horarios` = os 5 atuais + sábado; linha "Sábado: Fechado → 08:00–18:00".
  - `horarios: [{dia: 'domingo', abre: '10:00', fecha: '09:00'}]` → `ArgumentoInvalido` com "O fechamento precisa ser depois da abertura."
  - `adicionar_bloqueios: [{inicio: '2026-12-20', fim: '2026-12-22', motivo: 'Reforma'}]` → mantém o bloqueio atual + novo; linha "Bloqueios" com "+ 20/12/2026 a 22/12/2026 (Reforma)".
  - `remover_bloqueios: [{motivo: 'reforma'}]` (casa por motivo sem caixa/acento, ou por `inicio`) remove só o bloqueio correspondente; nenhum corresponde → `ArgumentoInvalido('Não encontrei esse bloqueio no espaço.')`.
  - renomear para nome de outro espaço do usuário → `ArgumentoInvalido` (nome repetido).
  - excluir espaço com reserva passada → `ArgumentoInvalido` "Há reservas usando este espaço. Desative em vez de excluir."; sem reservas → proposta criada.
- [ ] **Passo 3:** implementação de `ProporEspaco` (nome `propor_espaco`; parâmetros: `operacao`, `alvo`, `nome`, `capacidade`, `localizacao`, `cor` (enum das 5), `recursos_fixos` (lista), `aceita_solicitacoes`, `antecedencia_min_horas`, `duracao_max_min`, `intervalo_min`, `ativo`, `horarios` (mudanças por dia), `adicionar_bloqueios`, `remover_bloqueios`) usando o algoritmo comum da Task 2, com `final.horarios = MescladorDeHorarios::mesclar(atuais, mudanças)` e bloqueios = atuais − removidos + adicionados; validar `final` com `RegrasDeEspaco::regras` + `errosCruzados`. Linhas: campos simples como na Task 2; horários via `MescladorDeHorarios::linhas`; bloqueios "+ …" e "− …".
- [ ] **Passo 4:** acrescentar em `ConfirmarCadastroTest.php`: confirmar `alterar_espaco` (só sábado) grava e mantém os outros dias e bloqueios; confirmar `criar_espaco` grava horários; confirmar exclusão de espaço que ganhou reserva depois da proposta → 422.
- [ ] **Passo 5:** testes → PASSAM. Verificação completa. Commit `feat: assistente propõe cadastrar, alterar e excluir espaços com horários e bloqueios`.

---

### Task 4: Instruções do modelo e ciclo completo

**Arquivos:** modificar `resources/views/assistente/instrucoes.blade.php`; teste em `tests/Feature/Assistente/AssistenteTest.php` e `MontadorDeContextoTest.php`.

- [ ] **Passo 1 (RED):** `MontadorDeContextoTest` — o prompt de sistema contém "propor_espaco", "propor_grupo", "propor_equipamento" e a regra de pedir confirmação. `AssistenteTest` — rodada 1 o modelo chama `propor_grupo` com `{operacao: 'criar', nome: 'Coral'}`; rodada 2 responde texto; eventos `['status','cartao','texto','fim']`, o `cartao` tem `tipo` `acao_proposta` e `dados.tipo` `criar_grupo`; nenhum `Grupo` criado.
- [ ] **Passo 2:** acrescente às instruções (texto puro, pt-BR):
  - "Você também pode propor cadastrar, alterar ou excluir espaços, grupos e equipamentos com propor_espaco, propor_grupo e propor_equipamento. Elas só preparam a mudança; a secretaria confirma no cartão. Nunca diga que algo foi cadastrado, alterado ou excluído antes disso."
  - "Para alterar, envie só os campos que mudam. Para horários, envie só os dias que mudam (use fechado: true para fechar um dia)."
  - "Se o nome do cadastro for ambíguo ou não existir, use listar_cadastros e pergunte antes de propor."
- [ ] **Passo 3:** testes → PASSAM. Verificação completa. Commit `feat: assistente sabe propor mudanças nos cadastros`.

---

### Task 5: Cartão genérico de cadastro (frontend)

**Arquivos:** modificar `resources/js/types/assistente.ts`, `resources/js/components/assistente/cartoes/cartao-de-acao-proposta.tsx` (ou criar `cartao-de-cadastro-proposto.tsx` e escolher pelo `tipo`), `resources/js/lib/assistente/eventos.ts` (se a expiração de propostas precisar considerar o novo formato); criar `resources/js/lib/assistente/cadastro.ts` + `cadastro.test.ts`.

**Interfaces — consome:** formato do cartão (topo deste plano). **Produz:** `AcaoProposta` vira união discriminada por `tipo`: `AcaoPropostaDeReserva` (`tipo: 'criar_reserva'`, formato atual) | `AcaoPropostaDeCadastro` (`tipo` dos 9 de cadastro, `cadastro: {entidade, operacao, titulo, linhas, avisos}`, `resultado: {cadastro_id: string|null, entidade} | null`). Cartões antigos sem `tipo` → tratar como `criar_reserva`.

- [ ] **Passo 1 (Vitest RED)** em `cadastro.test.ts` para funções puras: `rotuloDoSelo(status, operacao)` ("Aguardando sua confirmação" / "Cadastrado" / "Alterado" / "Excluído" / "Descartada" / "Expirada"); `linkDoCadastro(entidade, cadastroId)` → rota da página do cadastro (`espaco` → `agendamentos.espacos.edit(id)` url quando houver id; `grupo` → `agendamentos.grupos.index`; `equipamento` → `agendamentos.equipamentos.index`; exclusão → página de índice); `normalizarAcao(dados)` → `tipo` padrão `criar_reserva` quando ausente.
- [ ] **Passo 2:** o cartão escolhe o layout pelo `tipo`: reserva = atual; cadastro = título em destaque, `<dl>` de linhas com "antes → depois" (antes riscado em `text-muted-foreground line-through` quando existe, depois em `font-medium`; `—` para null), avisos em `bg-aviso-fundo text-aviso`, operação "excluir" com o botão principal **Confirmar exclusão** em estilo destrutivo (`variant="destructive"`); demais **Confirmar**. Descartar, 422 com `acao` (substitui o cartão), duplo clique e `router.reload()` após confirmar continuam como hoje. Confirmado: selo do `rotuloDoSelo` + link "Ver cadastro" (`linkDoCadastro`).
- [ ] **Passo 3:** verificação completa (`php artisan wayfinder:generate --with-form` se precisar das rotas). Commit `feat: cartão de proposta de cadastro no assistente`.

---

### Task 6: Verificação no navegador (controlador)

- [ ] Limpar a VM do Docker não é necessário (sem build). Banco descartável `pequenavia_verif` + seed (script do plano 7), servidor `cd public && OBJC_DISABLE_INITIALIZE_FORK_SAFETY=YES PHP_CLI_SERVER_WORKERS=4 DB_DATABASE=pequenavia_verif APP_URL=http://127.0.0.1:8765 php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`; chave só no `.env` local.
- [ ] Pedir: "Cadastre a Sala 3 para 25 pessoas, aberta de segunda a sexta das 8h às 22h" → cartão → Confirmar → aparece em Espaços. "Abra o salão aos sábados das 8h às 18h" → só sábado muda. "Bloqueie a Sala 3 de 20 a 22/12 para reforma" → Confirmar → bloqueio aparece. "Cadastre o grupo Coral" / "Exclua o grupo Coral" (com aviso de reservas). "O projetor está em manutenção até sexta". "Exclua o salão" (com reserva) → recusa com a mensagem. Alterar na tela entre proposta e Confirmar → 422 visível no cartão.
- [ ] Limpeza: parar servidor, `DROP DATABASE pequenavia_verif`.
