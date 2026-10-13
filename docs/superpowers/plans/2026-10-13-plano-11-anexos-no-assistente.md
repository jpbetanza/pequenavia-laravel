# PequenaVia — Plano 11: anexos no assistente

**Objetivo:** a secretaria anexa arquivos à mensagem do assistente, pelo botão de clipe ou arrastando para o painel. O caso principal é importar cadastros de uma planilha (espaços com capacidade e horários, equipamentos, grupos, reservas): o assistente lê o arquivo, resume o que entendeu e propõe um cadastro por linha, que a secretaria confirma um a um ou todos de uma vez. Imagens e PDFs (ex.: foto de um quadro de horários, ofício da diocese) também entram.

**Arquitetura:** o modelo (`anthropic/claude-haiku-5.5` pelo OpenRouter) aceita texto, imagem e PDF, mas não planilha. Por isso:

- **Planilhas** (`.xlsx`, `.xls`, `.ods`, `.csv`) são lidas **no navegador** com o SheetJS que o app já usa em `lib/planilha.ts`: cada aba vira um bloco CSV com o nome da aba. O servidor recebe só texto. Nenhuma dependência PHP nova.
- **Texto** (`.txt`, `.md`) vai como texto.
- **Imagem** (`.png`, `.jpg`, `.webp`, `.gif`) e **PDF** sobem como arquivo, ficam no bucket `s3` em `assistente/{conversa}/{anexo}.{ext}` e vão ao modelo como data URL (`image_url` / `file`) toda vez que a mensagem entra no contexto.
- O texto extraído e os metadados ficam numa coluna nova `mensagens.anexos` (jsonb). O modelo recebe a mensagem do usuário como lista de partes: o texto digitado, um bloco `<anexo nome="…" tipo="…">…</anexo>` por anexo de texto e as partes de imagem/PDF.
- O envio da mensagem passa de JSON para `multipart/form-data`.

**Limites** (`config/assistente.php`): até 5 anexos por mensagem; imagem/PDF até 5 MB; texto até 60 000 caracteres por anexo (a planilha maior é cortada no navegador, com `truncado: true`, e o modelo é avisado). A mensagem pode ir só com anexos, sem texto.

**Propostas em lote:** hoje uma proposta nova expira as pendentes com a mesma chave, e toda reserva tem a chave `reserva`; importar 10 reservas deixaria só a última. Passa a valer: **uma proposta expira só as pendentes da mesma chave criadas antes do pedido atual do usuário**. Propostas da mesma resposta convivem; uma correção na mensagem seguinte continua substituindo a anterior. O painel faz a mesma conta. A resposta com duas ou mais propostas pendentes ganha o botão **Confirmar todas (N)**, que confirma uma por uma pelas rotas que já existem e para no primeiro conflito.

**Tech stack:** Laravel 13, Pest, Inertia 3 + React 19 + Tailwind 4 + shadcn, Wayfinder, SheetJS, Vitest.

**Design de referência:** canvas "PequenaVia — telas reimaginadas", pasta `assistente/` (`PainelLateral`, `AssistenteCelular` atualizados com o clipe e os anexos; `PainelArrastando` novo, com a área de soltar e uma importação em andamento).

## Restrições

- Texto em pt-BR. Botões só-ícone com `aria-label`; alvos ≥ 44 px; foco visível. Cores só por tokens do tema.
- Conteúdo de anexo é dado, nunca instrução: as instruções do sistema dizem isso, como já dizem para o resultado das ferramentas.
- Nada é gravado sem confirmação: os anexos só alimentam propostas.
- Excluir a conversa apaga os arquivos dela no bucket (job na fila, como `RemoverImagemModelo`).

---

## Task 1: Backend — receber, guardar e mandar os anexos ao modelo

**Files:**
- Create: migration `add_anexos_to_mensagens_table` (`jsonb anexos` nullable)
- Modify: `app/Models/Mensagem.php` (fillable, cast `array`)
- Modify: `config/assistente.php` (`max_anexos`, `max_bytes_arquivo`, `max_caracteres_anexo`)
- Modify: `app/Http/Requests/Assistente/EnviarMensagemRequest.php` (texto opcional com anexos; `anexos.*.nome|tipo|conteudo|truncado|arquivo`)
- Create: `app/Services/Assistente/Anexos.php` (grava os anexos de uma mensagem; monta as partes para o modelo; apaga a pasta da conversa)
- Modify: `app/Services/Assistente/Assistente.php` (`responder` recebe os anexos; título "Arquivo: nome" quando não há texto)
- Modify: `app/Services/Assistente/MontadorDeContexto.php` (mensagem do usuário com anexos vira lista de partes)
- Modify: `app/Http/Controllers/Assistente/MensagemController.php`, `ConversaController.php` (`show` devolve `anexos` sem o conteúdo; `destroy` agenda a limpeza)
- Create: `app/Jobs/RemoverAnexosDaConversa.php`
- Modify: `resources/views/assistente/instrucoes.blade.php` (regras de anexos e importação)
- Tests: `tests/Feature/Assistente/AnexosTest.php`

## Task 2: Propostas em lote

**Files:**
- Modify: `app/Services/Assistente/Cadastros/ExpiracaoDePropostas.php` (só expira as anteriores ao último pedido do usuário)
- Modify: `resources/js/lib/assistente/eventos.ts` (`expirarPropostasAnteriores` ignora a resposta atual)
- Create: `resources/js/components/assistente/cartoes/confirmar-todas.tsx`
- Tests: expiração no mesmo turno e em turnos diferentes (Pest e Vitest)

## Task 3: Frontend — clipe, arrastar e soltar, anexos nas mensagens

**Files:**
- Create: `resources/js/lib/assistente/anexos.ts` (+ `anexos.test.ts`): tipo pelo nome/MIME, leitura da planilha em CSV por aba com corte, validação de limites
- Modify: `resources/js/lib/assistente/enviar.ts` (FormData)
- Modify: `resources/js/components/assistente/campo-de-mensagem.tsx` (clipe, input de arquivo, lista de anexos removíveis)
- Create: `resources/js/components/assistente/anexo.tsx` (chip: ícone do tipo, nome, tamanho, remover)
- Modify: `resources/js/components/assistente/conversa-do-assistente.tsx` (estado dos anexos, área de soltar com sobreposição, anexos na bolha do usuário, tentar de novo reenvia os anexos)
- Modify: `resources/js/types/assistente.ts`, `resources/js/lib/assistente/eventos.ts` (`iniciarTroca` com anexos)

## Task 4: Design

Atualizar o canvas de design (pasta `assistente/`) com a cara nova: clipe no campo, anexos prontos para envio, anexo na bolha do usuário, sobreposição de arrastar e soltar, resposta de importação com "Confirmar todas".

## Verificação

`npm run check`, `npm run types:check`, `npm test`, `composer types:check` (PHPStan), `php artisan test --compact`, todos verdes no CI.
