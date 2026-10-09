# PequenaVia — Migração para Laravel monolítico

- **Data:** 2026-10-06
- **Status:** aprovado em brainstorming, aguardando revisão do spec
- **Origem:** `pequenavia` (Next.js 16 + Supabase), em `certificados.pequenavia.app.br`
- **Destino:** `pequenavia-laravel` (este repositório)

## 1. Objetivo

Reescrever o PequenaVia como um monolito Laravel que roda na VPS própria, sem Supabase:

- PostgreSQL próprio no lugar do banco do Supabase.
- RustFS (compatível com S3) no lugar do Supabase Storage.
- Login com Google mantido, ao lado do login com email e senha.

O produto continua o mesmo. A paróquia sobe a imagem de um modelo de certidão (Batismo, Crisma…), posiciona campos de texto arrastando no editor, preenche os dados à mão ou importa de Excel, e baixa o certificado gerado como PNG. O modelo pode ser compartilhado com outros usuários por email.

### Decisões tomadas

| Tema                     | Decisão                                                                                                                                                                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Criação do projeto       | Starter kit oficial React via `laravel new --react --pest --database=pgsql --npm --boost` (Laravel Boost instalado).                                                                                                                            |
| Interface                | Inertia 3 + React 19 + TypeScript + Tailwind 4 + **shadcn/ui** (padrão do kit). Componentes do PequenaVia portados de HeroUI para shadcn.                                                                                                       |
| Ambiente local           | PHP 8.5 + Composer + instalador via `php.new`; Postgres e RustFS num `docker-compose` local; testes do Imagick em container com a imagem de produção.                                                                                           |
| Object storage           | RustFS na VPS, com bucket privado, acessado pelo disco `s3` do Laravel. O MinIO foi descartado porque não tem mais imagem Docker pública. O RustFS 1.x é um projeto jovem: antes da produção, validar upload de 50 MB e fazer backup do volume. |
| Dados existentes         | **Não migrar.** O sistema novo começa vazio.                                                                                                                                                                                                    |
| Login                    | Google (Socialite) + email/senha (Fortify do kit), com verificação de email, reset de senha por SMTP e 2FA (ativo por padrão no kit).                                                                                                           |
| Geração da imagem        | Imagick no PHP, dentro do próprio monolito.                                                                                                                                                                                                     |
| Tamanho máximo do modelo | 50 MB por imagem.                                                                                                                                                                                                                               |

### Fora do escopo

- Importar dados do Supabase.
- A página `/certificados`, que é código morto: chama o gerador sem `modelo_id`, sempre recebe 404 e não tem links apontando para ela.
- Fila assíncrona e Redis.
- Saída em PDF.
- Download do lote em ZIP.
- Backup automatizado, que fica para a fase 2 (seção 8).

## 2. Arquitetura e stack

### Aplicação

- Laravel 13, PHP 8.5, extensão `imagick`.
- Projeto criado pelo starter kit oficial React: Inertia 3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui, Vite e **Wayfinder** (rotas tipadas geradas no build).
- O layout, as telas de auth e as telas de configurações do kit são mantidos e recebem a identidade visual do PequenaVia.
- Os componentes do projeto Next vão para `resources/js/` e são **portados de HeroUI para shadcn/ui**. HeroUI não entra no projeto. Além da troca de componentes, muda a camada de dados: as chamadas `supabase.from(...)`, `supabase.storage` e `fetch('/api/...')` são substituídas por:
    - props do Inertia, entregues pelos controllers;
    - `router.post/put/patch/delete` ou `useForm` para as mutações;
    - `fetch` apenas no endpoint de geração, que devolve binário.
- Sessão, cache e fila usam o driver `database`.

### Estrutura de código (backend)

```
app/
  Http/Controllers/
    Auth/GoogleController.php           # redirect + callback do Socialite
    ModeloCertificadoController.php     # listar, criar, renomear, trocar imagem, excluir
    CampoCertificadoController.php      # criar, atualizar, excluir campos
    CompartilhamentoController.php      # convidar, alternar pode_editar, remover
    GeracaoCertificadoController.php    # POST gerar → PNG
    HistoricoController.php             # últimas 50 gerações
    ConfiguracoesController.php         # perfil, excluir conta
  Models/ User, ModeloCertificado, CampoCertificado, GeracaoCertificado, CompartilhamentoModelo
  Policies/ModeloCertificadoPolicy.php
  Services/
    GeradorCertificado.php              # Imagick; não conhece HTTP
    CatalogoFontes.php                  # resolve família + peso → arquivo .ttf
  Console/Commands/BaixarFontes.php     # php artisan fontes:baixar
config/fontes.php
resources/fonts/                        # TTFs versionados
```

Cada unidade tem uma responsabilidade:

- `GeradorCertificado` recebe um modelo com seus campos, os valores e o binário da imagem de fundo, e devolve os bytes do PNG.
- `CatalogoFontes` é o único lugar que sabe onde ficam os arquivos de fonte.
- Toda decisão de acesso fica na Policy.

## 3. Modelo de dados

IDs são `uuid` (`HasUuids`), para as URLs continuarem no formato `/meus-certificados/{uuid}/...`.

### `users`

| Coluna                       | Tipo                     | Observação                                     |
| ---------------------------- | ------------------------ | ---------------------------------------------- |
| `id`                         | uuid PK                  |                                                |
| `name`                       | string                   |                                                |
| `email`                      | string, unique           |                                                |
| `email_verified_at`          | timestamp, nullable      | Contas vindas do Google já entram verificadas. |
| `password`                   | string, **nullable**     | Nulo para quem só usa Google.                  |
| `google_id`                  | string, unique, nullable |                                                |
| `avatar_url`                 | string, nullable         |                                                |
| `remember_token`, timestamps |                          |                                                |

### `modelos_certificado`

| Coluna        | Tipo                | Observação                                                              |
| ------------- | ------------------- | ----------------------------------------------------------------------- |
| `id`          | uuid PK             |                                                                         |
| `user_id`     | FK → users, cascade | Dono do modelo.                                                         |
| `title`       | string              |                                                                         |
| `description` | text, nullable      |                                                                         |
| `image_path`  | string              | Chave do objeto no RustFS, no formato `modelos/{user_id}/{uuid}.{ext}`. |
| timestamps    |                     |                                                                         |

Não existe `image_url`. O navegador carrega a imagem pela rota `GET /modelos/{modelo}/imagem` (seção 6).

### `campos_certificado`

| Coluna               | Tipo                              | Default                                               |
| -------------------- | --------------------------------- | ----------------------------------------------------- |
| `id`                 | uuid PK                           |                                                       |
| `modelo_id`          | FK → modelos_certificado, cascade |                                                       |
| `nome`               | string                            | Chave usada nos valores e no cabeçalho do Excel.      |
| `label`              | string, nullable                  | Quando vazio, a interface mostra o `nome`.            |
| `x`, `y`             | integer                           | Pixels na resolução original da imagem.               |
| `cor`                | string                            | Hex, por exemplo `#1a1a1a`.                           |
| `tamanho_fonte`      | integer                           |                                                       |
| `fonte_familia`      | string                            | Precisa existir em `config/fontes.php` (regra `in:`). |
| `peso_fonte`         | smallint                          | 600                                                   |
| `espacamento_letras` | decimal(5,2)                      | 0                                                     |
| `centralizado`       | boolean                           | false                                                 |
| timestamps           |                                   |                                                       |

Unique: (`modelo_id`, `nome`). Um campo novo recebe do servidor o primeiro nome livre na sequência `Campo 1`, `Campo 2`…, e renomear valida a unicidade dentro do modelo. Hoje todo campo novo nasce como "Novo campo", e nomes duplicados fariam dois campos receberem o mesmo valor na geração.

### `geracoes_certificado`

| Coluna           | Tipo                                       | Observação                                  |
| ---------------- | ------------------------------------------ | ------------------------------------------- |
| `id`             | uuid PK                                    |                                             |
| `user_id`        | FK → users, cascade                        |                                             |
| `modelo_id`      | FK → modelos_certificado, **nullOnDelete** | O histórico sobrevive à exclusão do modelo. |
| `modelo_title`   | string                                     | Snapshot do título no momento da geração.   |
| `campos_valores` | jsonb                                      |                                             |
| `created_at`     | timestamp                                  | Sem `updated_at`.                           |

### `compartilhamentos_modelo`

| Coluna        | Tipo                              | Observação                                                         |
| ------------- | --------------------------------- | ------------------------------------------------------------------ |
| `id`          | uuid PK                           |                                                                    |
| `modelo_id`   | FK → modelos_certificado, cascade |                                                                    |
| `email`       | string                            | Gravado em minúsculas. Pode ser de alguém que ainda não tem conta. |
| `pode_editar` | boolean, default false            |                                                                    |
| timestamps    |                                   |                                                                    |

Unique: (`modelo_id`, `email`).

Também entram as tabelas padrão do Laravel: `sessions`, `cache`, `jobs`, `password_reset_tokens`.

## 4. Permissões

`ModeloCertificadoPolicy` substitui todo o RLS do Supabase e a função `is_owner_of_modelo()`.

| Ação (método da Policy)                            | Dono | Compartilhado, `pode_editar = true` | Compartilhado, só leitura | Outros |
| -------------------------------------------------- | ---- | ----------------------------------- | ------------------------- | ------ |
| `view`, `gerar`                                    | ✅   | ✅                                  | ✅                        | ❌     |
| `editarCampos`, `update` (renomear, trocar imagem) | ✅   | ✅                                  | ❌                        | ❌     |
| `gerenciarCompartilhamentos`                       | ✅   | ❌                                  | ❌                        | ❌     |
| `delete`                                           | ✅   | ❌                                  | ❌                        | ❌     |

Regras:

- O compartilhamento é casado por `lower(email)` do usuário logado. **Só vale se o email do usuário estiver verificado.** Isso impede que alguém crie uma conta com o email de outra pessoa para ver os modelos compartilhados com ela.
- Quem não pode ver o modelo recebe **404** em qualquer ação (`Response::denyAsNotFound()` na Policy). Quem pode ver mas não tem a permissão da ação recebe **403**.
- As listagens usam o scope `ModeloCertificado::acessiveisPor(User $user)`, que retorna os modelos próprios e os compartilhados. Nenhum controller consulta `ModeloCertificado::query()` sem esse scope ou sem `authorize()`.
- Correção em relação ao sistema atual: hoje `pode_editar` aparece na interface, mas nenhuma regra de RLS dá permissão de escrita a quem recebeu o compartilhamento. No sistema novo, a edição por compartilhado passa a funcionar como a interface promete.

## 5. Autenticação

- **Fortify**, já configurado pelo starter kit com as telas React, cuida de login, registro, verificação de email, "esqueci minha senha", reset e 2FA. O `User` passa a implementar `MustVerifyEmail`. As telas de login e registro ganham o botão "Entrar com Google".
- O login pelo Google não passa pelo desafio de 2FA do Fortify. O próprio Google é o segundo fator nesse caminho.
- Se alguma feature do Fortify for desligada, as referências a ela no frontend precisam ser removidas, porque as rotas do Wayfinder são geradas no build.
- **Socialite / Google**:
    - `GET /auth/google` redireciona para o Google.
    - `GET /auth/google/callback` trata a volta:
        1. Se existe um usuário com aquele `google_id`, faz o login.
        2. Senão, se existe um usuário com o mesmo email, vincula o `google_id` a ele e marca o email como verificado. O Google já verificou esse email.
        3. Senão, cria o usuário com `password = null` e o email verificado.
    - Se o usuário cancelar ou o `state` for inválido, volta para `/login` com uma mensagem flash.
- Rotas autenticadas exigem `auth` + `verified`.
- A exclusão de conta pede confirmação. Usuários com senha digitam a senha; usuários só-Google digitam o próprio email.
- SMTP configurado por `MAIL_*` no ambiente.

## 6. Armazenamento de imagens (RustFS)

- Disco `s3` com `AWS_ENDPOINT` apontando para o RustFS interno, `AWS_USE_PATH_STYLE_ENDPOINT=true`, bucket `modelos-certificado`, **privado**.
- Upload de modelo:
    - aceita `image/png`, `image/jpeg` e `image/webp`;
    - **até 50 MB**;
    - dimensões máximas de 10000 × 10000 px;
    - validação no servidor.
- Os limites de upload acompanham os 50 MB em todas as camadas:
    - PHP: `upload_max_filesize=55M` e `post_max_size=60M`;
    - nginx: `client_max_body_size 60m`;
    - Traefik: sem middleware de buffering limitando o corpo.
- Para trocar a imagem, o arquivo novo é enviado primeiro, e o antigo só é removido depois que o registro foi atualizado.
- Ao excluir um modelo, o evento `deleted` do model despacha um job que remove o objeto do RustFS. Na exclusão de conta, o mesmo acontece para cada modelo do usuário.
- O navegador acessa a imagem só pela rota `GET /modelos/{modelo}/imagem`. Ela passa pela Policy (`view`) e faz streaming do RustFS com `Cache-Control: private, max-age=3600`. O RustFS **não fica exposto publicamente**: uma URL S3 assinada para o host interno (`http://rustfs:9000`) não abriria no navegador, e o host faz parte da assinatura. Mesma origem também elimina CORS no canvas do editor.

## 7. Geração do certificado

### 7.1 Catálogo de fontes

`config/fontes.php` é a fonte única de verdade. Ele define os grupos, cada família e o arquivo `.ttf` de cada peso disponível. O React recebe o catálogo como prop compartilhada do Inertia (`HandleInertiaRequests::share`), e a constante `FONTE_GRUPOS` sai do componente.

| Grupo      | Família exibida                                                                          | Arquivo usado    |
| ---------- | ---------------------------------------------------------------------------------------- | ---------------- |
| Sistema    | Georgia                                                                                  | Gelasio          |
| Sistema    | Arial                                                                                    | Liberation Sans  |
| Sistema    | Times New Roman                                                                          | Liberation Serif |
| Sistema    | Courier New                                                                              | Liberation Mono  |
| Serif      | Cormorant Garamond, EB Garamond, Lora, Merriweather, Playfair Display, Libre Baskerville | Google Fonts     |
| Sem serifa | Roboto, Montserrat, Open Sans, Lato, Raleway, Quicksand                                  | Google Fonts     |
| Display    | Cinzel, Crimson Text, Dancing Script                                                     | Google Fonts     |

Regras do catálogo:

- As fontes de "Sistema" usam substitutas livres com as mesmas medidas. As originais da Microsoft não existem no container Linux, e hoje o servidor desenha com uma fonte substituta qualquer.
- `php artisan fontes:baixar` busca no Google Fonts os TTFs estáticos de cada peso de 100 a 900 que a família oferece e grava em `resources/fonts/{familia}/{peso}.ttf`. O resultado é versionado no repositório. A geração **nunca** acessa a rede.
- O editor carrega as mesmas fontes por `@font-face` a partir de `/fonts/...`, servidas pelo próprio app, em vez do Google Fonts. Assim o preview e o PNG usam exatamente os mesmos arquivos.
- Peso indisponível: `CatalogoFontes` usa o peso mais próximo da mesma família (empate vai para o mais pesado) e registra um aviso no log.

### 7.2 Endpoint

`POST /modelos/{modelo}/certificados`, com corpo `{ "valores": { "<nome_campo>": "texto", ... } }`.

1. `authorize('gerar', $modelo)`.
2. Validação:
    - `valores` é um array;
    - as chaves precisam pertencer a `nome` dos campos do modelo; chaves extras são descartadas;
    - cada valor é uma string de até 500 caracteres.
3. `GeradorCertificado::gerar($modelo, $valores, $imagemFundo)` gera os bytes do PNG.
4. Grava uma linha em `geracoes_certificado`.
5. Responde 200 com `Content-Type: image/png` e `Content-Disposition: attachment; filename="{title} - {primeiro valor}.png"`.

Limite de taxa: 60 gerações por minuto por usuário (`throttle`). O lote do Excel continua sendo um loop no navegador, uma request por linha, com o status por linha que já existe hoje.

### 7.3 Algoritmo (`GeradorCertificado`)

1. Antes de tudo, define os limites de recurso do Imagick (`setResourceLimit`): memória de 512 MB e área compatível com imagens de 10000 × 10000.
2. `new Imagick()` + `readImageBlob($imagemFundo)`. O tamanho da tela é o da imagem original.
3. Para cada campo, na ordem de `created_at`, com valor não vazio após `trim`:
    1. `ImagickDraw`: `setFont(CatalogoFontes::arquivo(familia, peso))`, `setFontSize(tamanho_fonte)`, `setFillColor(cor)`, `setTextKerning(espacamento_letras)`, `setTextAntialias(true)`.
    2. `$m = $imagick->queryFontMetrics($draw, $texto)`.
    3. `x = centralizado ? (largura - $m['textWidth']) / 2 : campo.x`.
    4. `y = campo.y + $m['ascender']`. O Imagick posiciona pela linha de base; somar o ascender reproduz o `textBaseline = "top"` do editor.
    5. `$imagick->annotateImage($draw, x, y, 0, $texto)`.
4. `setImageFormat('png')`, devolve `getImageBlob()` e libera os recursos (`clear()`).

## 8. Infraestrutura e deploy

Stack Swarm `pequenavia`:

| Serviço        | Imagem                                                                                 | Redes                                                                            | Volumes         |
| -------------- | -------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- | --------------- |
| `app`          | `jpbetanza/pequenavia-laravel:latest` (php-fpm + nginx, multi-stage com build do Vite) | `interna`, `network_swarm_public`                                                | —               |
| `postgres`     | `postgres:17`                                                                          | `interna`                                                                        | `pequenavia_pg` |
| `rustfs`       | `rustfs/rustfs:1.0.1`                                                                  | `interna` (+ `network_swarm_public` só se o console for exposto; a API S3 nunca) | `pequenavia_s3` |
| `rustfs-perms` | `alpine:3` (roda uma vez; ajusta o dono do volume para o UID 10001 do RustFS)          | —                                                                                | `pequenavia_s3` |
| `s3-init`      | `amazon/aws-cli` (roda uma vez)                                                        | `interna`                                                                        | —               |

- `app` em `certificados.pequenavia.app.br` pelo Traefik (`websecure`, `letsencryptresolver`), com o mesmo esquema de labels da stack atual.
- Healthcheck em `GET /up`.
- Deploy `start-first` com rollback e Watchtower, como hoje. Consequência: **toda migration precisa ser compatível com a versão anterior do código** (adicionar antes, remover depois).
- O entrypoint do `app` roda `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache` e depois sobe o servidor.
- `s3-init` cria o bucket `modelos-certificado` como privado, se ainda não existir.
- Segredos (`APP_KEY`, `DB_PASSWORD`, `AWS_ACCESS_KEY_ID/SECRET`, `GOOGLE_CLIENT_ID/SECRET`, `MAIL_PASSWORD`) entram como variáveis da stack no Portainer ou Docker secrets, **nunca** no YAML versionado.
- O Google Console precisa da URI de redirecionamento `https://certificados.pequenavia.app.br/auth/google/callback`.
- **Fase 2 (fora deste escopo):** `pg_dump` diário e `mc mirror` do bucket para um destino fora dos volumes principais.

## 9. Tratamento de erros

| Situação                                                                                    | Resposta                                                                             |
| ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Upload inválido (tipo, mais de 50 MB, dimensões)                                            | Erro de validação no formulário Inertia.                                             |
| Usuário que não pode ver o modelo (estranho, ou convidado sem email verificado)             | 404, em qualquer ação, para não revelar que o modelo existe.                         |
| Usuário que vê o modelo mas não tem a permissão da ação (leitor editando, editor excluindo) | 403. Inertia mostra a página de erro.                                                |
| Imagem de fundo ausente ou ilegível no RustFS                                               | 422 com "Não foi possível ler a imagem do modelo".                                   |
| Fonte ou peso ausente                                                                       | Fallback para o peso mais próximo, com aviso no log.                                 |
| Exceção inesperada na geração                                                               | 500 com mensagem genérica, exceção no log. `error.message` nunca vai para o cliente. |
| Falha no OAuth do Google                                                                    | Redireciona para `/login` com mensagem flash (`toast-listener` lê a flash session).  |
| Limite de taxa excedido                                                                     | 429. O gerador mostra "aguarde alguns segundos" na linha.                            |

## 10. Testes

Pest, com Postgres real num container de teste. `jsonb`, as FKs com cascade/nullOnDelete e os índices unique precisam ser testados no mesmo motor de produção. RustFS via `Storage::fake('s3')`.

- **Permissões (feature):** cada rota de modelo, campo, compartilhamento e geração exercitada como dono, compartilhado-editor, compartilhado-leitor, compartilhado com email não verificado e estranho, conferindo a matriz da seção 4. Inclui o scope `acessiveisPor`.
- **Auth (feature):** registro, verificação, login, reset de senha e os três ramos do callback do Google (Socialite mockado). Inclui a exclusão de conta removendo os objetos do storage.
- **Upload:** aceita 50 MB, rejeita acima disso, rejeita tipo inválido e dimensões acima do limite. A troca de imagem remove o objeto antigo.
- **Geração (visual):** `GeradorCertificado` com uma imagem de fundo fixa, comparado com PNGs de referência em `tests/fixtures/esperado/` por `Imagick::compareImages` (métrica RMSE com tolerância baixa). Casos:
    - campo alinhado à esquerda;
    - campo centralizado;
    - espaçamento entre letras;
    - pesos 400 e 700;
    - uma fonte de cada grupo;
    - fallback de peso.
- **Catálogo:** todo arquivo referenciado em `config/fontes.php` existe em `resources/fonts/`.
- **Frontend:** `npm run build` e `tsc --noEmit` sem erros no CI.

## 11. Critérios de pronto

1. Fluxo completo funcionando em produção na VPS: cadastro (email ou Google), upload de modelo de até 50 MB, posicionamento de campos, geração manual e por Excel, compartilhamento com edição e leitura, histórico e exclusão de conta.
2. Nenhuma dependência do Supabase no código ou na stack.
3. O PNG gerado coincide com o preview do editor para todas as fontes do catálogo (verificação visual manual + testes de referência).
4. Suíte Pest verde.
