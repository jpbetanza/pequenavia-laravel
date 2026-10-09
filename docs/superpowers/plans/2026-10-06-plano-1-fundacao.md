# PequenaVia Laravel — Plano 1: Fundação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o projeto Laravel pelo starter kit oficial e entregar todo o backend do PequenaVia, exceto a geração de imagem: schema, permissões, login com Google, modelos com imagem no RustFS, campos, compartilhamentos, histórico e exclusão de conta. Tudo coberto por testes Pest em Postgres.

**Architecture:** Monolito Laravel 13 + Inertia 3 + React. Controllers finos entregam props ao Inertia. Toda regra de acesso fica em `ModeloCertificadoPolicy` e no scope `acessiveisPor`. Imagens ficam num bucket privado do RustFS e chegam ao navegador por uma rota autenticada. As páginas React deste plano são **placeholders mínimos**. A interface real é o Plano 3.

**Tech Stack:** PHP 8.5 (php.new), Laravel 13, Fortify (do starter kit), Socialite, Pest, PostgreSQL 17, RustFS, `league/flysystem-aws-s3-v3`, Inertia 3, React 19, TypeScript, Wayfinder, Laravel Boost.

**Spec:** `docs/superpowers/specs/2026-10-06-migracao-laravel-design.md`

## Roteiro dos planos

| Plano | Entrega | Depende de |
|---|---|---|
| **1 — Fundação** (este) | Scaffold, ambiente local, schema, Policy, Google, modelos/campos/compartilhamentos/histórico/conta | — |
| 2 — Geração | `config/fontes.php` completo, `fontes:baixar`, `CatalogoFontes::arquivo()`, `GeradorCertificado` (Imagick), endpoint de geração, testes visuais | 1 |
| 3 — Frontend | Porte do editor, gerador (manual/Excel), modais, histórico e configurações de HeroUI para shadcn/ui, landing e navegação | 1, 2 |
| 4 — Deploy | Dockerfile multi-stage (php-fpm + nginx + imagick), stack Swarm com Postgres e RustFS, entrypoint, limites de 50 MB | 1–3 |

## Global Constraints

- PHP 8.5, Laravel 13, criado com `laravel new --react --pest --database=pgsql --npm --boost --no-interaction`.
- UI: shadcn/ui do kit. **HeroUI não entra no projeto.**
- IDs `uuid` (`HasUuids`) em `users`, `modelos_certificado`, `campos_certificado`, `geracoes_certificado` e `compartilhamentos_modelo`.
- Testes em PostgreSQL real (banco `pequenavia_test`), nunca SQLite.
- Bucket `modelos-certificado` privado. O navegador acessa imagens só por `GET /modelos/{modelo}/imagem`.
- Upload de modelo: `png`, `jpg`, `jpeg` ou `webp`, até 50 MB (`max:51200` KB), até 10000 × 10000 px.
- Compartilhamento casado por email em minúsculas e **só para usuários com email verificado**.
- Mensagens ao usuário em português. `error.message` de exceções nunca vai para o cliente.
- Toda migration nova é compatível com a versão anterior do código (adicionar antes, remover depois).
- Diretrizes do Laravel Boost (`CLAUDE.md`/`AGENTS.md` gerados) valem para todas as tarefas: leia-as no início de cada tarefa.
- Commits terminam com `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Mapa de arquivos

```
compose.yaml                                         # Postgres + RustFS locais (Task 1)
docker/postgres/init.sql                             # cria pequenavia_test (Task 1)
phpunit.xml                                          # testes em pgsql (Task 1)
database/migrations/0001_01_01_000000_create_users_table.php   # users uuid + google (Task 2)
database/migrations/2026_10_06_000001_create_modelos_certificado_table.php
database/migrations/2026_10_06_000002_create_campos_certificado_table.php
database/migrations/2026_10_06_000003_create_compartilhamentos_modelo_table.php
database/migrations/2026_10_06_000004_create_geracoes_certificado_table.php
app/Models/{User,ModeloCertificado,CampoCertificado,CompartilhamentoModelo,GeracaoCertificado}.php
database/factories/{User,ModeloCertificado,CampoCertificado,CompartilhamentoModelo,GeracaoCertificado}Factory.php
app/Policies/ModeloCertificadoPolicy.php             # Task 3
app/Http/Controllers/Auth/GoogleController.php       # Task 4
app/Http/Controllers/ModeloCertificadoController.php # Task 5
app/Http/Requests/{Store,Update}ModeloCertificadoRequest.php
app/Jobs/RemoverImagemModelo.php
app/Services/CatalogoFontes.php                      # Task 6 (Plano 2 estende)
config/fontes.php                                    # Task 6 (Plano 2 estende)
app/Http/Controllers/CampoCertificadoController.php  # Task 6
app/Http/Controllers/CompartilhamentoController.php  # Task 7
app/Http/Controllers/HistoricoController.php         # Task 8
app/Http/Controllers/Settings/ProfileController.php  # Task 8 (arquivo do kit, método destroy)
resources/js/pages/meus-certificados/{index,campos}.tsx   # placeholders
resources/js/pages/historico/index.tsx                     # placeholder
tests/Feature/{Modelos,Campos,Compartilhamentos,Historico,Conta,Auth/Google,Policies}/*.php
```

---

### Task 1: Toolchain, scaffold e ambiente local

> **Nota de execução (2026-10-08):** o MinIO deixou de ter imagem pública (Docker Hub inexistente, Quay com 401), então o object storage local e de produção passa a ser o **RustFS** (`rustfs/rustfs:1.0.1`), escolhido pelo usuário. O código não muda, pois o disco `s3` fala S3. O bucket é criado pelo AWS CLI (`s3-init`). Também: o kit traz uma migration de passkeys (conferir na Task 2) e `Route::redirect` foi trocado por `Route::get` em `routes/settings.php`, porque o Wayfinder gera o método `query` que ele mesmo não tipa.

**Files:**
- Create: projeto inteiro via `laravel new`, `compose.yaml`, `docker/postgres/init.sql`
- Modify: `.env`, `.env.example`, `phpunit.xml`

**Interfaces:**
- Produces: projeto Laravel na raiz do repo; Postgres local em `127.0.0.1:54320` (bancos `pequenavia` e `pequenavia_test`, usuário `pequenavia`/`secret`); RustFS em `127.0.0.1:9010` (console `9011`, usuário `pequenavia`/`pequenavia-secret`, bucket privado `modelos-certificado`); `php artisan test` rodando em Postgres.

- [ ] **Step 1: Criar a branch de trabalho**

```bash
cd /Users/jpbetanza/Projetos/pequenavia-laravel
git checkout -b feat/fundacao
```

- [ ] **Step 2: Instalar PHP, Composer e o instalador via php.new (pedir confirmação ao usuário antes)**

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
export PATH="$HOME/.config/herd-lite/bin:$PATH"
php -v && composer -V && laravel --version
php -m | grep -E -i '^(pdo_pgsql|gd|imagick)$'
```

Esperado: PHP 8.5.x, Composer 2.x e o instalador listados. `pdo_pgsql` e `gd` presentes. Se `imagick` não aparecer, está tudo bem: só o Plano 2 precisa dele, e lá os testes do Imagick rodam em container. Se `pdo_pgsql` faltar, pare e avise o usuário.

Nos passos seguintes, todo shell novo precisa do `export PATH=...` acima, até o usuário reiniciar o terminal.

- [ ] **Step 3: Gerar o projeto no scratchpad e mover para o repositório**

O repo já tem `.git`, `README.md` e `docs/`, e o `laravel new` exige uma pasta inexistente. Por isso o projeto é gerado fora e movido:

```bash
SCRATCH=/private/tmp/claude-501/-Users-jpbetanza-Projetos/94b67f05-0341-4063-980e-b0dadb79bb3d/scratchpad
cd "$SCRATCH" && rm -rf pequenavia-laravel
laravel new pequenavia-laravel --react --pest --database=pgsql --npm --boost --no-interaction
rsync -a --exclude .git --exclude docs "$SCRATCH/pequenavia-laravel/" /Users/jpbetanza/Projetos/pequenavia-laravel/
cd /Users/jpbetanza/Projetos/pequenavia-laravel && ls
```

Esperado: `app/ bootstrap/ config/ database/ resources/ routes/ tests/ composer.json package.json vendor/ node_modules/ docs/` e os arquivos do Boost (`CLAUDE.md` e/ou `AGENTS.md`, `.mcp.json`). Se o instalador falhar ao rodar migrations por não ter banco, ignore: elas rodam no Step 7.

- [ ] **Step 4: Ler as diretrizes do Boost**

Leia o `CLAUDE.md` (ou `AGENTS.md`) gerado. As convenções dele valem a partir daqui, por exemplo usar `php artisan make:*` com `--no-interaction`.

- [ ] **Step 5: Criar os serviços locais**

`docker/postgres/init.sql`:

```sql
CREATE DATABASE pequenavia_test OWNER pequenavia;
```

`compose.yaml`:

```yaml
services:
  postgres:
    image: postgres:17
    environment:
      POSTGRES_DB: pequenavia
      POSTGRES_USER: pequenavia
      POSTGRES_PASSWORD: secret
    ports:
      - "54320:5432"
    volumes:
      - pequenavia_pg:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U pequenavia"]
      interval: 5s
      retries: 10

  rustfs-perms:
    image: alpine:3
    user: root
    command: chown -R 10001:10001 /data
    volumes:
      - pequenavia_s3:/data

  rustfs:
    image: rustfs/rustfs:1.0.1
    depends_on:
      rustfs-perms:
        condition: service_completed_successfully
    environment:
      RUSTFS_ACCESS_KEY: pequenavia
      RUSTFS_SECRET_KEY: pequenavia-secret
      RUSTFS_ADDRESS: ":9000"
      RUSTFS_CONSOLE_ADDRESS: ":9001"
      RUSTFS_CONSOLE_ENABLE: "true"
    ports:
      - "9010:9000"
      - "9011:9001"
    volumes:
      - pequenavia_s3:/data

  s3-init:
    image: amazon/aws-cli:latest
    depends_on:
      - rustfs
    environment:
      AWS_ACCESS_KEY_ID: pequenavia
      AWS_SECRET_ACCESS_KEY: pequenavia-secret
      AWS_DEFAULT_REGION: us-east-1
    entrypoint: >
      /bin/sh -c "
      until aws --endpoint-url http://rustfs:9000 s3api list-buckets; do sleep 2; done;
      aws --endpoint-url http://rustfs:9000 s3api head-bucket --bucket modelos-certificado
      || aws --endpoint-url http://rustfs:9000 s3api create-bucket --bucket modelos-certificado;
      "

volumes:
  pequenavia_pg:
  pequenavia_s3:
```

Run: `docker compose up -d && docker compose ps`
Esperado: `postgres` healthy, `rustfs` running, `rustfs-perms` e `s3-init` encerrados com código 0.

- [ ] **Step 6: Configurar `.env` e `.env.example`**

No `.env`, defina (substituindo as linhas `DB_*` e `AWS_*` existentes):

```ini
APP_NAME=PequenaVia
APP_LOCALE=pt_BR
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=54320
DB_DATABASE=pequenavia
DB_USERNAME=pequenavia
DB_PASSWORD=secret

AWS_ACCESS_KEY_ID=pequenavia
AWS_SECRET_ACCESS_KEY=pequenavia-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=modelos-certificado
AWS_ENDPOINT=http://127.0.0.1:9010
AWS_USE_PATH_STYLE_ENDPOINT=true

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=/auth/google/callback
```

Copie as mesmas chaves para `.env.example`, com `DB_PASSWORD=`, `AWS_SECRET_ACCESS_KEY=` e `GOOGLE_CLIENT_SECRET=` vazios.

- [ ] **Step 7: Apontar os testes para o Postgres**

Em `phpunit.xml`, dentro de `<php>`, substitua as linhas `DB_CONNECTION` e `DB_DATABASE` por:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="pequenavia_test"/>
```

Mantenha `QUEUE_CONNECTION=sync`. Se não existir, adicione `<env name="QUEUE_CONNECTION" value="sync"/>`.

- [ ] **Step 8: Migrar e rodar a suíte do kit**

```bash
php artisan migrate
php artisan test
```

Esperado: as migrations rodam no banco `pequenavia` e todos os testes do kit passam (auth, settings, 2FA, dashboard).

- [ ] **Step 9: Conferir o build do frontend**

```bash
npm run build && npx tsc --noEmit
```

Esperado: os dois terminam sem erros.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "chore: cria projeto pelo starter kit React e ambiente local

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Schema, models e factories

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`
- Create: as 4 migrations `2026_10_06_00000{1..4}_*.php`, os models `ModeloCertificado`, `CampoCertificado`, `CompartilhamentoModelo`, `GeracaoCertificado` e suas factories
- Test: `tests/Feature/Models/SchemaTest.php`

**Interfaces:**
- Produces:
  - `User`: `uuid id`, `email` sempre em minúsculas, `tem_senha` (bool, appended), `modelos(): HasMany<ModeloCertificado>`, `geracoes(): HasMany<GeracaoCertificado>`, implementa `MustVerifyEmail`; `UserFactory::google()`.
  - `ModeloCertificado` (tabela `modelos_certificado`): `user()`, `campos()` (ordenados por `created_at`, `id`), `compartilhamentos()`, `geracoes()`; `$fillable = ['title','description','image_path']`.
  - `CampoCertificado` (tabela `campos_certificado`): `modelo()`; casts `x`, `y`, `tamanho_fonte`, `peso_fonte` → int; `espacamento_letras` → float; `centralizado` → bool.
  - `CompartilhamentoModelo` (tabela `compartilhamentos_modelo`): `modelo()`; `email` em minúsculas; `pode_editar` → bool; `CompartilhamentoModeloFactory::editor()`.
  - `GeracaoCertificado` (tabela `geracoes_certificado`): `UPDATED_AT = null`; `campos_valores` → array; `user()`, `modelo()`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Models/SchemaTest.php`:

```php
<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('usuário usa uuid, guarda email em minúsculas e pode não ter senha', function () {
    $user = User::factory()->google()->create(['email' => 'Ana.Souza@Example.COM']);

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->email)->toBe('ana.souza@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->tem_senha)->toBeFalse()
        ->and(User::factory()->create()->tem_senha)->toBeTrue();
});

test('excluir o usuário apaga modelos, campos e compartilhamentos em cascata', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create();
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $modelo->user->delete();

    expect(ModeloCertificado::count())->toBe(0)
        ->and(CampoCertificado::count())->toBe(0)
        ->and(CompartilhamentoModelo::count())->toBe(0);
});

test('histórico sobrevive à exclusão do modelo', function () {
    $geracao = GeracaoCertificado::factory()->create();

    $geracao->modelo->delete();

    expect($geracao->fresh())->not->toBeNull()
        ->and($geracao->fresh()->modelo_id)->toBeNull()
        ->and($geracao->fresh()->modelo_title)->toBe('Batismo');
});

test('campos_valores é jsonb e volta como array', function () {
    $geracao = GeracaoCertificado::factory()->create(['campos_valores' => ['nome' => 'Maria']]);

    expect($geracao->fresh()->campos_valores)->toBe(['nome' => 'Maria']);
});

test('nome do campo é único dentro do modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);

    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);
})->throws(UniqueConstraintViolationException::class);

test('mesmo email não é compartilhado duas vezes no mesmo modelo', function () {
    $modelo = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'Joao@Example.com']);

    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'joao@example.com']);
})->throws(UniqueConstraintViolationException::class);

test('campos vêm na ordem de criação', function () {
    $modelo = ModeloCertificado::factory()->create();
    $primeiro = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'B']);
    $segundo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'A']);

    expect($modelo->campos->pluck('id')->all())->toBe([$primeiro->id, $segundo->id]);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=SchemaTest`
Esperado: FAIL com `Class "App\Models\ModeloCertificado" not found`.

- [ ] **Step 3: Trocar a migration de `users` do kit**

Substitua o conteúdo de `database/migrations/0001_01_01_000000_create_users_table.php` por:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_url')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

Confira se alguma outra migration do kit referencia `users.id` com `foreignId(...)` (`grep -rn "foreignId('user_id')" database/migrations`). Se houver, troque por `foreignUuid('user_id')`. A migration de colunas de 2FA do kit só adiciona colunas e não muda.

- [ ] **Step 4: Criar as migrations novas**

`database/migrations/2026_10_06_000001_create_modelos_certificado_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos_certificado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_certificado');
    }
};
```

`database/migrations/2026_10_06_000002_create_campos_certificado_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_certificado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('modelo_id')->constrained('modelos_certificado')->cascadeOnDelete();
            $table->string('nome');
            $table->string('label')->nullable();
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->string('cor', 7)->default('#000000');
            $table->unsignedSmallInteger('tamanho_fonte')->default(32);
            $table->string('fonte_familia')->default('Georgia');
            $table->unsignedSmallInteger('peso_fonte')->default(600);
            $table->decimal('espacamento_letras', 5, 2)->default(0);
            $table->boolean('centralizado')->default(false);
            $table->timestamps();

            $table->unique(['modelo_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_certificado');
    }
};
```

`database/migrations/2026_10_06_000003_create_compartilhamentos_modelo_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compartilhamentos_modelo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('modelo_id')->constrained('modelos_certificado')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('pode_editar')->default(false);
            $table->timestamps();

            $table->unique(['modelo_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compartilhamentos_modelo');
    }
};
```

`database/migrations/2026_10_06_000004_create_geracoes_certificado_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geracoes_certificado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('modelo_id')->nullable()->constrained('modelos_certificado')->nullOnDelete();
            $table->string('modelo_title');
            $table->jsonb('campos_valores');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geracoes_certificado');
    }
};
```

- [ ] **Step 5: Reescrever `app/Models/User.php`**

Antes de substituir, abra o arquivo gerado e veja se o kit usa algo além do código abaixo (traits ou casts extras). Se usar, preserve.

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = ['name', 'email', 'password', 'google_id', 'avatar_url'];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    protected $appends = ['tem_senha'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => Str::lower(trim($value)));
    }

    protected function temSenha(): Attribute
    {
        return Attribute::get(fn () => $this->password !== null);
    }

    /** @return HasMany<ModeloCertificado, $this> */
    public function modelos(): HasMany
    {
        return $this->hasMany(ModeloCertificado::class);
    }

    /** @return HasMany<GeracaoCertificado, $this> */
    public function geracoes(): HasMany
    {
        return $this->hasMany(GeracaoCertificado::class);
    }
}
```

- [ ] **Step 6: Adicionar o estado `google()` em `database/factories/UserFactory.php`**

Adicione o método dentro da classe, mantendo o resto:

```php
    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => (string) fake()->unique()->numerify('#####################'),
            'avatar_url' => 'https://lh3.googleusercontent.com/a/exemplo',
        ]);
    }
```

- [ ] **Step 7: Criar os models**

`app/Models/ModeloCertificado.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeloCertificado extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'modelos_certificado';

    protected $fillable = ['title', 'description', 'image_path'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CampoCertificado, $this> */
    public function campos(): HasMany
    {
        return $this->hasMany(CampoCertificado::class, 'modelo_id')->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<CompartilhamentoModelo, $this> */
    public function compartilhamentos(): HasMany
    {
        return $this->hasMany(CompartilhamentoModelo::class, 'modelo_id');
    }

    /** @return HasMany<GeracaoCertificado, $this> */
    public function geracoes(): HasMany
    {
        return $this->hasMany(GeracaoCertificado::class, 'modelo_id');
    }
}
```

`app/Models/CampoCertificado.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampoCertificado extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'campos_certificado';

    protected $fillable = [
        'nome', 'label', 'x', 'y', 'cor', 'tamanho_fonte', 'fonte_familia',
        'peso_fonte', 'espacamento_letras', 'centralizado',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'tamanho_fonte' => 'integer',
            'peso_fonte' => 'integer',
            'espacamento_letras' => 'float',
            'centralizado' => 'boolean',
        ];
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
```

`app/Models/CompartilhamentoModelo.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompartilhamentoModelo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'compartilhamentos_modelo';

    protected $fillable = ['email', 'pode_editar'];

    protected function casts(): array
    {
        return ['pode_editar' => 'boolean'];
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => Str::lower(trim($value)));
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
```

`app/Models/GeracaoCertificado.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeracaoCertificado extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'geracoes_certificado';

    protected $fillable = ['user_id', 'modelo_id', 'modelo_title', 'campos_valores'];

    protected function casts(): array
    {
        return ['campos_valores' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ModeloCertificado, $this> */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloCertificado::class, 'modelo_id');
    }
}
```

- [ ] **Step 8: Criar as factories**

`database/factories/ModeloCertificadoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModeloCertificado> */
class ModeloCertificadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Batismo',
            'description' => null,
            'image_path' => 'modelos/'.Str::uuid().'.png',
        ];
    }
}
```

`database/factories/CampoCertificadoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampoCertificado> */
class CampoCertificadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modelo_id' => ModeloCertificado::factory(),
            'nome' => 'Campo '.fake()->unique()->numberBetween(1, 1_000_000),
            'label' => null,
            'x' => 100,
            'y' => 100,
            'cor' => '#000000',
            'tamanho_fonte' => 32,
            'fonte_familia' => 'Georgia',
            'peso_fonte' => 600,
            'espacamento_letras' => 0,
            'centralizado' => false,
        ];
    }
}
```

`database/factories/CompartilhamentoModeloFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompartilhamentoModelo> */
class CompartilhamentoModeloFactory extends Factory
{
    public function definition(): array
    {
        return [
            'modelo_id' => ModeloCertificado::factory(),
            'email' => fake()->unique()->safeEmail(),
            'pode_editar' => false,
        ];
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes) => ['pode_editar' => true]);
    }
}
```

`database/factories/GeracaoCertificadoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GeracaoCertificado> */
class GeracaoCertificadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'modelo_id' => ModeloCertificado::factory(),
            'modelo_title' => 'Batismo',
            'campos_valores' => ['Campo 1' => 'Maria'],
        ];
    }
}
```

- [ ] **Step 9: Rodar os testes**

```bash
php artisan migrate:fresh
php artisan test
```

Esperado: `SchemaTest` com 7 testes passando e o restante da suíte do kit verde. Se algum teste do kit quebrar por `id` inteiro (por exemplo, `assertDatabaseHas('users', ['id' => 1])`), troque o valor por `$user->id`.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: schema do PequenaVia com uuids, models e factories

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Permissões (Policy + scope)

**Files:**
- Create: `app/Policies/ModeloCertificadoPolicy.php`
- Modify: `app/Models/ModeloCertificado.php` (scope `acessiveisPor`)
- Test: `tests/Feature/Policies/ModeloCertificadoPolicyTest.php`

**Interfaces:**
- Consumes: models da Task 2.
- Produces:
  - Policy (auto-descoberta) com `view`, `gerar`, `update`, `editarCampos`, `gerenciarCompartilhamentos` e `delete`, todos `(User $user, ModeloCertificado $modelo): bool`.
  - Scope `ModeloCertificado::query()->acessiveisPor(User $user)`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Policies/ModeloCertificadoPolicyTest.php`:

```php
<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

function cenarioPermissao(string $papel): array
{
    $modelo = ModeloCertificado::factory()->create();

    $user = match ($papel) {
        'dono' => $modelo->user,
        'editor', 'leitor', 'nao_verificado' => User::factory()
            ->state($papel === 'nao_verificado' ? ['email_verified_at' => null] : [])
            ->create(['email' => "{$papel}@example.com"]),
        'estranho' => User::factory()->create(),
    };

    if (in_array($papel, ['editor', 'leitor', 'nao_verificado'], true)) {
        CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create([
            // maiúsculas de propósito: o casamento é case-insensitive
            'email' => strtoupper("{$papel}@example.com"),
            'pode_editar' => $papel !== 'leitor',
        ]);
    }

    return [$user, $modelo];
}

dataset('matriz', [
    //                 papel             view   gerar  update editarCampos gerenciar delete
    'dono'           => ['dono',           true,  true,  true,  true,  true,  true],
    'editor'         => ['editor',         true,  true,  true,  true,  false, false],
    'leitor'         => ['leitor',         true,  true,  false, false, false, false],
    'não verificado' => ['nao_verificado', false, false, false, false, false, false],
    'estranho'       => ['estranho',       false, false, false, false, false, false],
]);

test('matriz de permissões do modelo', function (
    string $papel, bool $view, bool $gerar, bool $update, bool $editarCampos, bool $gerenciar, bool $delete,
) {
    [$user, $modelo] = cenarioPermissao($papel);

    expect($user->can('view', $modelo))->toBe($view)
        ->and($user->can('gerar', $modelo))->toBe($gerar)
        ->and($user->can('update', $modelo))->toBe($update)
        ->and($user->can('editarCampos', $modelo))->toBe($editarCampos)
        ->and($user->can('gerenciarCompartilhamentos', $modelo))->toBe($gerenciar)
        ->and($user->can('delete', $modelo))->toBe($delete);
})->with('matriz');

test('acessiveisPor traz modelos próprios e compartilhados, nunca os de outros', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $proprio = ModeloCertificado::factory()->for($user)->create();
    $compartilhado = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->create(['email' => 'ANA@example.com']);
    ModeloCertificado::factory()->create();

    $ids = ModeloCertificado::query()->acessiveisPor($user)->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$proprio->id, $compartilhado->id])->sort()->values()->all());
});

test('acessiveisPor ignora compartilhamentos de quem não verificou o email', function () {
    $user = User::factory()->unverified()->create(['email' => 'ana@example.com']);
    $compartilhado = ModeloCertificado::factory()->create();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->create(['email' => 'ana@example.com']);

    expect(ModeloCertificado::query()->acessiveisPor($user)->count())->toBe(0);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=ModeloCertificadoPolicyTest`
Esperado: FAIL. Sem Policy, `can()` retorna `false` até para o dono, e `acessiveisPor` não existe.

- [ ] **Step 3: Criar a Policy**

`app/Policies/ModeloCertificadoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

class ModeloCertificadoPolicy
{
    public function view(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo) || $this->compartilhamento($user, $modelo) !== null;
    }

    public function gerar(User $user, ModeloCertificado $modelo): bool
    {
        return $this->view($user, $modelo);
    }

    public function update(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo)
            || $this->compartilhamento($user, $modelo)?->pode_editar === true;
    }

    public function editarCampos(User $user, ModeloCertificado $modelo): bool
    {
        return $this->update($user, $modelo);
    }

    public function gerenciarCompartilhamentos(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo);
    }

    public function delete(User $user, ModeloCertificado $modelo): bool
    {
        return $this->ehDono($user, $modelo);
    }

    private function ehDono(User $user, ModeloCertificado $modelo): bool
    {
        return $modelo->user_id === $user->id;
    }

    private function compartilhamento(User $user, ModeloCertificado $modelo): ?CompartilhamentoModelo
    {
        // Sem email verificado, qualquer um poderia criar uma conta com o email de outra pessoa.
        if (! $user->hasVerifiedEmail()) {
            return null;
        }

        return $modelo->compartilhamentos()->where('email', $user->email)->first();
    }
}
```

`$user->email` já está em minúsculas (mutator da Task 2), e o `email` do compartilhamento também. Por isso a comparação direta é case-insensitive na prática.

- [ ] **Step 4: Adicionar o scope em `app/Models/ModeloCertificado.php`**

Adicione os imports `use Illuminate\Database\Eloquent\Builder;` e o método:

```php
    /** @param Builder<ModeloCertificado> $query */
    public function scopeAcessiveisPor(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->when($user->hasVerifiedEmail(), fn (Builder $query) => $query->orWhereHas(
                    'compartilhamentos',
                    fn (Builder $query) => $query->where('email', $user->email),
                ));
        });
    }
```

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --filter=ModeloCertificadoPolicyTest`
Esperado: PASS, com 5 casos da matriz e 2 testes de scope.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: policy e scope de acesso aos modelos

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Login com Google, verificação de email e mensagens flash

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleController.php`
- Modify: `config/services.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/pages/auth/login.tsx`, `resources/js/pages/auth/register.tsx`
- Test: `tests/Feature/Auth/GoogleLoginTest.php`

**Interfaces:**
- Consumes: `User` (Task 2) com `google_id`, `avatar_url` e `MustVerifyEmail`.
- Produces: rotas `auth.google` (`GET /auth/google`) e `auth.google.callback` (`GET /auth/google/callback`); prop compartilhada `flash: { success?: string, error?: string }` em todas as páginas Inertia.

- [ ] **Step 1: Instalar o Socialite**

```bash
composer require laravel/socialite
```

- [ ] **Step 2: Escrever o teste que falha**

`tests/Feature/Auth/GoogleLoginTest.php`:

```php
<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

function googleRetorna(array $dados = []): void
{
    $googleUser = (new SocialiteUser)->map(array_merge([
        'id' => 'google-123',
        'name' => 'Ana Souza',
        'email' => 'Ana@Example.com',
        'avatar' => 'https://lh3.googleusercontent.com/a/ana',
    ], $dados));

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('redireciona para o Google', function () {
    config(['services.google.client_id' => 'id-teste', 'services.google.client_secret' => 'segredo']);

    $this->get(route('auth.google'))->assertRedirectContains('accounts.google.com');
});

test('cria usuário novo já verificado e sem senha', function () {
    googleRetorna();

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();
    expect($user->email)->toBe('ana@example.com')
        ->and($user->google_id)->toBe('google-123')
        ->and($user->password)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('vincula ao usuário de email e senha já existente e verifica o email', function () {
    $existente = User::factory()->unverified()->create(['email' => 'ana@example.com']);
    googleRetorna();

    $this->get(route('auth.google.callback'));

    $existente->refresh();
    expect(User::count())->toBe(1)
        ->and($existente->google_id)->toBe('google-123')
        ->and($existente->hasVerifiedEmail())->toBeTrue()
        ->and($existente->tem_senha)->toBeTrue();
    $this->assertAuthenticatedAs($existente);
});

test('entra pelo google_id mesmo se o email do Google mudou', function () {
    $existente = User::factory()->google()->create(['google_id' => 'google-123', 'email' => 'antigo@example.com']);
    googleRetorna(['email' => 'novo@example.com']);

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($existente);
    expect($existente->fresh()->email)->toBe('antigo@example.com');
});

test('cancelamento ou state inválido volta ao login com mensagem', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Não foi possível entrar com o Google. Tente novamente.');
    $this->assertGuest();
});

test('mensagens flash chegam às páginas Inertia', function () {
    $this->withSession(['error' => 'Algo deu errado'])
        ->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('flash.error', 'Algo deu errado'));
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `php artisan test --filter=GoogleLoginTest`
Esperado: FAIL com `Route [auth.google] not defined`.

- [ ] **Step 4: Configurar o provider em `config/services.php`**

Adicione ao array retornado:

```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],
```

- [ ] **Step 5: Criar o controller**

`app/Http/Controllers/Auth/GoogleController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->with('error', 'Não foi possível entrar com o Google. Tente novamente.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', Str::lower($google->getEmail()))->first();

        if ($user === null) {
            $user = User::create([
                'name' => $google->getName() ?: $google->getEmail(),
                'email' => $google->getEmail(),
                'google_id' => $google->getId(),
                'avatar_url' => $google->getAvatar(),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $google->getId(),
                'avatar_url' => $google->getAvatar(),
            ]);
        }

        // O Google já verificou o email; o login por ele não passa pelo 2FA do Fortify (decisão do spec).
        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()]);
        }
        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
```

O redirect vai para `dashboard`, que é o `home` do Fortify no kit. O Plano 3 troca o destino ao montar a navegação.

- [ ] **Step 6: Registrar as rotas em `routes/web.php`**

Adicione o import `use App\Http\Controllers\Auth\GoogleController;` e:

```php
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});
```

- [ ] **Step 7: Compartilhar o flash em `app/Http/Middleware/HandleInertiaRequests.php`**

No array retornado por `share()`, adicione a chave (mantendo as que o kit já tem):

```php
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
```

- [ ] **Step 8: Rodar os testes**

Run: `php artisan test --filter=GoogleLoginTest`
Esperado: PASS (6 testes).

- [ ] **Step 9: Botão do Google e erro nas telas de login e registro**

Em `resources/js/pages/auth/login.tsx` e `resources/js/pages/auth/register.tsx`, importe (se ainda não estiverem importados):

```tsx
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
```

E logo após o `</form>` (ou o componente de formulário) de cada página, insira:

```tsx
<div className="flex flex-col gap-3">
    <div className="relative text-center text-xs text-muted-foreground">
        <span className="bg-background px-2">ou</span>
    </div>
    {/* <a> e não <Link>: o OAuth sai do app, precisa de navegação completa */}
    <Button asChild variant="outline" className="w-full">
        <a href="/auth/google">Entrar com Google</a>
    </Button>
    <ErroFlash />
</div>
```

E, no fim de cada arquivo:

```tsx
function ErroFlash() {
    const { flash } = usePage<{ flash?: { error?: string | null } }>().props;

    if (!flash?.error) return null;

    return <p className="text-center text-sm text-destructive">{flash.error}</p>;
}
```

- [ ] **Step 10: Conferir build e suíte**

```bash
npm run build && npx tsc --noEmit && php artisan test
```

Esperado: tudo verde.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: login com Google via Socialite e flash compartilhado

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Modelos de certificado com imagem no RustFS

**Files:**
- Create: `app/Http/Controllers/ModeloCertificadoController.php`, `app/Http/Requests/StoreModeloCertificadoRequest.php`, `app/Http/Requests/UpdateModeloCertificadoRequest.php`, `app/Jobs/RemoverImagemModelo.php`, `resources/js/pages/meus-certificados/index.tsx`
- Modify: `app/Models/ModeloCertificado.php` (evento `deleted`), `routes/web.php`
- Test: `tests/Feature/Modelos/ModeloCertificadoTest.php`

**Interfaces:**
- Consumes: Policy e scope da Task 3.
- Produces:
  - Rotas (grupo `auth` + `verified`): `meus-certificados.index` (`GET /meus-certificados`), `modelos.store` (`POST /modelos`), `modelos.update` (`PUT /modelos/{modelo}`, multipart via `_method`), `modelos.destroy` (`DELETE /modelos/{modelo}`) e `modelos.imagem` (`GET /modelos/{modelo}/imagem`).
  - Job `RemoverImagemModelo(string $path)`.
  - Formato de cada item das props `modelos`/`compartilhados`: `{ id, title, description, imagem_url, dono_nome, pode_editar: bool, eh_dono: bool, compartilhamentos: {id,email,pode_editar}[] }` (lista vazia quando não é dono).

- [ ] **Step 1: Instalar o driver S3 e conferir o RustFS**

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
php artisan tinker --execute="Storage::disk('s3')->put('ping.txt', 'ok'); echo Storage::disk('s3')->get('ping.txt'); Storage::disk('s3')->delete('ping.txt');"
```

Esperado: imprime `ok`. O disco `s3` do kit já lê `AWS_ENDPOINT` e `AWS_USE_PATH_STYLE_ENDPOINT`. Se não ler, adicione `'endpoint' => env('AWS_ENDPOINT')` e `'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false)` ao disco `s3` em `config/filesystems.php`.

- [ ] **Step 2: Escrever o teste que falha**

`tests/Feature/Modelos/ModeloCertificadoTest.php`:

```php
<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('s3'));

function modeloComImagem(?User $dono = null): ModeloCertificado
{
    $modelo = ModeloCertificado::factory()->for($dono ?? User::factory()->create())->create();
    Storage::disk('s3')->put($modelo->image_path, 'png-bytes');

    return $modelo;
}

test('lista modelos próprios e compartilhados separadamente', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $proprio = modeloComImagem($user);
    $compartilhado = modeloComImagem();
    CompartilhamentoModelo::factory()->for($compartilhado, 'modelo')->editor()->create(['email' => 'ana@example.com']);
    CompartilhamentoModelo::factory()->for($proprio, 'modelo')->create(['email' => 'joao@example.com']);
    modeloComImagem();

    $this->actingAs($user)->get(route('meus-certificados.index'))
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/index')
            ->has('modelos', 1)
            ->where('modelos.0.id', $proprio->id)
            ->where('modelos.0.eh_dono', true)
            ->where('modelos.0.imagem_url', route('modelos.imagem', $proprio))
            ->where('modelos.0.compartilhamentos.0.email', 'joao@example.com')
            ->has('compartilhados', 1)
            ->where('compartilhados.0.id', $compartilhado->id)
            ->where('compartilhados.0.pode_editar', true)
            ->where('compartilhados.0.compartilhamentos', []));
});

test('cria modelo enviando imagem de até 50 MB para o bucket', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('modelos.store'), [
        'title' => 'Batismo',
        'description' => 'Paróquia São José',
        'imagem' => UploadedFile::fake()->image('batismo.png', 2000, 1400)->size(51200),
    ])->assertRedirect(route('meus-certificados.index'))->assertSessionHas('success');

    $modelo = ModeloCertificado::sole();
    expect($modelo->user_id)->toBe($user->id)
        ->and($modelo->image_path)->toStartWith("modelos/{$user->id}/")
        ->and($modelo->image_path)->toEndWith('.png');
    Storage::disk('s3')->assertExists($modelo->image_path);
});

test('rejeita imagem acima de 50 MB, tipo inválido ou dimensões acima de 10000 px', function (UploadedFile $arquivo) {
    $this->actingAs(User::factory()->create())
        ->post(route('modelos.store'), ['title' => 'X', 'imagem' => $arquivo])
        ->assertSessionHasErrors('imagem');

    expect(ModeloCertificado::count())->toBe(0);
})->with([
    'grande demais' => fn () => UploadedFile::fake()->image('a.png', 100, 100)->size(51201),
    'pdf' => fn () => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
    'largura excessiva' => fn () => UploadedFile::fake()->image('a.png', 10001, 10),
]);

test('editor compartilhado renomeia e troca a imagem; a antiga é removida', function () {
    $modelo = modeloComImagem();
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);
    $antiga = $modelo->image_path;

    $this->actingAs($editor)->put(route('modelos.update', $modelo), [
        'title' => 'Crisma',
        'imagem' => UploadedFile::fake()->image('crisma.jpg', 800, 600),
    ])->assertRedirect()->assertSessionHas('success');

    $modelo->refresh();
    expect($modelo->title)->toBe('Crisma')
        ->and($modelo->image_path)->toStartWith("modelos/{$modelo->user_id}/");
    Storage::disk('s3')->assertMissing($antiga);
    Storage::disk('s3')->assertExists($modelo->image_path);
});

test('leitor compartilhado não pode alterar o modelo', function () {
    $modelo = modeloComImagem();
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)->put(route('modelos.update', $modelo), ['title' => 'Hack'])->assertForbidden();
});

test('dono exclui o modelo e a imagem sai do bucket', function () {
    $modelo = modeloComImagem();

    $this->actingAs($modelo->user)->delete(route('modelos.destroy', $modelo))
        ->assertRedirect(route('meus-certificados.index'));

    expect(ModeloCertificado::count())->toBe(0);
    Storage::disk('s3')->assertMissing($modelo->image_path);
});

test('editor compartilhado não pode excluir', function () {
    $modelo = modeloComImagem();
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);

    $this->actingAs($editor)->delete(route('modelos.destroy', $modelo))->assertForbidden();
});

test('imagem é servida só para quem pode ver o modelo', function () {
    $modelo = modeloComImagem();

    $this->actingAs($modelo->user)->get(route('modelos.imagem', $modelo))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, private');

    $this->actingAs(User::factory()->create())->get(route('modelos.imagem', $modelo))->assertForbidden();
});

test('imagem ausente no bucket responde 404', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)->get(route('modelos.imagem', $modelo))->assertNotFound();
});

test('visitante é mandado para o login', function () {
    $this->get(route('meus-certificados.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `php artisan test --filter=ModeloCertificadoTest`
Esperado: FAIL com `Route [meus-certificados.index] not defined`.

- [ ] **Step 4: Criar o job de remoção**

`app/Jobs/RemoverImagemModelo.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class RemoverImagemModelo implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $path) {}

    public function handle(): void
    {
        Storage::disk('s3')->delete($this->path);
    }
}
```

Em `app/Models/ModeloCertificado.php`, adicione o import `use App\Jobs\RemoverImagemModelo;` e:

```php
    protected static function booted(): void
    {
        static::deleted(fn (ModeloCertificado $modelo) => RemoverImagemModelo::dispatch($modelo->image_path));
    }
```

- [ ] **Step 5: Criar os Form Requests**

`app/Http/Requests/StoreModeloCertificadoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModeloCertificadoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'imagem' => ['required', ...self::regrasImagem()],
        ];
    }

    /** @return list<string> */
    public static function regrasImagem(): array
    {
        return ['file', 'mimes:png,jpg,jpeg,webp', 'max:51200', 'dimensions:max_width=10000,max_height=10000'];
    }

    public function messages(): array
    {
        return [
            'imagem.max' => 'A imagem pode ter no máximo 50 MB.',
            'imagem.mimes' => 'Envie uma imagem PNG, JPG ou WEBP.',
            'imagem.dimensions' => 'A imagem pode ter no máximo 10000 × 10000 pixels.',
        ];
    }
}
```

`app/Http/Requests/UpdateModeloCertificadoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModeloCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('modelo'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'imagem' => ['nullable', ...StoreModeloCertificadoRequest::regrasImagem()],
        ];
    }

    public function messages(): array
    {
        return (new StoreModeloCertificadoRequest)->messages();
    }
}
```

- [ ] **Step 6: Criar o controller**

`app/Http/Controllers/ModeloCertificadoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModeloCertificadoRequest;
use App\Http\Requests\UpdateModeloCertificadoRequest;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModeloCertificadoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $modelos = ModeloCertificado::query()
            ->acessiveisPor($user)
            ->with(['user:id,name', 'compartilhamentos'])
            ->latest()
            ->get()
            ->map(fn (ModeloCertificado $modelo) => $this->resumo($modelo, $user));

        return Inertia::render('meus-certificados/index', [
            'modelos' => $modelos->where('eh_dono', true)->values(),
            'compartilhados' => $modelos->where('eh_dono', false)->values(),
        ]);
    }

    public function store(StoreModeloCertificadoRequest $request): RedirectResponse
    {
        $user = $request->user();

        $modelo = new ModeloCertificado($request->safe()->only(['title', 'description']));
        $modelo->user()->associate($user);
        $modelo->image_path = $this->salvarImagem($request->file('imagem'), $user->id);
        $modelo->save();

        return redirect()->route('meus-certificados.index')->with('success', 'Modelo criado.');
    }

    public function update(UpdateModeloCertificadoRequest $request, ModeloCertificado $modelo): RedirectResponse
    {
        $imagemAntiga = null;

        if ($request->hasFile('imagem')) {
            $imagemAntiga = $modelo->image_path;
            // A imagem fica sempre na pasta do dono, mesmo quando um editor compartilhado troca.
            $modelo->image_path = $this->salvarImagem($request->file('imagem'), $modelo->user_id);
        }

        $modelo->fill($request->safe()->only(['title', 'description']))->save();

        if ($imagemAntiga !== null) {
            Storage::disk('s3')->delete($imagemAntiga);
        }

        return back()->with('success', 'Modelo atualizado.');
    }

    public function destroy(ModeloCertificado $modelo): RedirectResponse
    {
        Gate::authorize('delete', $modelo);

        $modelo->delete();

        return redirect()->route('meus-certificados.index')->with('success', 'Modelo excluído.');
    }

    public function imagem(ModeloCertificado $modelo): StreamedResponse
    {
        Gate::authorize('view', $modelo);

        abort_unless(Storage::disk('s3')->exists($modelo->image_path), 404);

        return Storage::disk('s3')->response($modelo->image_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function salvarImagem(UploadedFile $arquivo, string $donoId): string
    {
        return $arquivo->storeAs("modelos/{$donoId}", Str::uuid().'.'.$arquivo->extension(), 's3');
    }

    /** @return array<string, mixed> */
    private function resumo(ModeloCertificado $modelo, User $user): array
    {
        $ehDono = $modelo->user_id === $user->id;

        return [
            'id' => $modelo->id,
            'title' => $modelo->title,
            'description' => $modelo->description,
            'imagem_url' => route('modelos.imagem', $modelo),
            'dono_nome' => $modelo->user->name,
            'eh_dono' => $ehDono,
            'pode_editar' => $user->can('update', $modelo),
            'compartilhamentos' => $ehDono
                ? $modelo->compartilhamentos->map(fn (CompartilhamentoModelo $c) => [
                    'id' => $c->id,
                    'email' => $c->email,
                    'pode_editar' => $c->pode_editar,
                ])->values()
                : [],
        ];
    }
}
```

O teste de `Cache-Control` espera `max-age=3600, private` porque o Symfony normaliza a ordem das diretivas. Se o valor real vier em outra ordem, ajuste o teste para o valor normalizado observado.

- [ ] **Step 7: Registrar as rotas em `routes/web.php`**

Adicione o import `use App\Http\Controllers\ModeloCertificadoController;` e, dentro do grupo `auth` + `verified` existente (onde está o `dashboard`), as rotas:

```php
    Route::get('meus-certificados', [ModeloCertificadoController::class, 'index'])->name('meus-certificados.index');
    Route::post('modelos', [ModeloCertificadoController::class, 'store'])->name('modelos.store');
    Route::put('modelos/{modelo}', [ModeloCertificadoController::class, 'update'])->name('modelos.update');
    Route::delete('modelos/{modelo}', [ModeloCertificadoController::class, 'destroy'])->name('modelos.destroy');
    Route::get('modelos/{modelo}/imagem', [ModeloCertificadoController::class, 'imagem'])->name('modelos.imagem');
```

- [ ] **Step 8: Criar a página placeholder**

`resources/js/pages/meus-certificados/index.tsx`. É a versão mínima para o Inertia ter o que renderizar; a interface real vem no Plano 3:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export type CompartilhamentoResumo = { id: string; email: string; pode_editar: boolean };

export type ModeloResumo = {
    id: string;
    title: string;
    description: string | null;
    imagem_url: string;
    dono_nome: string;
    eh_dono: boolean;
    pode_editar: boolean;
    compartilhamentos: CompartilhamentoResumo[];
};

type Props = { modelos: ModeloResumo[]; compartilhados: ModeloResumo[] };

export default function MeusCertificados({ modelos, compartilhados }: Props) {
    return (
        <AppLayout>
            <Head title="Meus certificados" />
            <div className="space-y-6 p-4">
                <section>
                    <h1 className="text-xl font-semibold">Meus modelos</h1>
                    <ul>{modelos.map((m) => <li key={m.id}>{m.title}</li>)}</ul>
                </section>
                <section>
                    <h2 className="text-lg font-semibold">Compartilhados comigo</h2>
                    <ul>{compartilhados.map((m) => <li key={m.id}>{m.title} — {m.dono_nome}</li>)}</ul>
                </section>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 9: Rodar testes e build**

```bash
php artisan test --filter=ModeloCertificadoTest && npm run build && npx tsc --noEmit
```

Esperado: 12 testes passando (contando os casos do dataset) e build sem erros.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: CRUD de modelos com imagem privada no RustFS

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Campos do certificado

**Files:**
- Create: `config/fontes.php`, `app/Services/CatalogoFontes.php`, `app/Http/Controllers/CampoCertificadoController.php`, `resources/js/pages/meus-certificados/campos.tsx`
- Modify: `routes/web.php`
- Test: `tests/Feature/Campos/CampoCertificadoTest.php`

**Interfaces:**
- Consumes: Policy (`view`, `editarCampos`), `ModeloResumo` não é usado aqui.
- Produces:
  - `config('fontes.grupos')`: `array<string grupo, array<string familia, string slug>>`. O slug é a pasta das fontes que o Plano 2 usa.
  - `CatalogoFontes::familias(): list<string>` e `CatalogoFontes::grupos(): array`. O Plano 2 acrescenta `arquivo()`.
  - Rotas: `meus-certificados.campos` (`GET /meus-certificados/{modelo}/campos`), `campos.store` (`POST /modelos/{modelo}/campos`), `campos.update` (`PATCH /modelos/{modelo}/campos/{campo}`) e `campos.destroy` (`DELETE /modelos/{modelo}/campos/{campo}`).
  - Props da página: `modelo { id, title, imagem_url }`, `campos: CampoCertificado[]` (todas as colunas), `pode_editar: bool` e `fontes: config('fontes.grupos')`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Campos/CampoCertificadoTest.php`:

```php
<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

function modeloCompartilhado(bool $podeEditar): array
{
    $modelo = ModeloCertificado::factory()->create();
    $user = User::factory()->create(['email' => 'convidado@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create([
        'email' => 'convidado@example.com',
        'pode_editar' => $podeEditar,
    ]);

    return [$modelo, $user];
}

test('página de campos entrega modelo, campos em ordem, permissão e catálogo de fontes', function () {
    $modelo = ModeloCertificado::factory()->create();
    $a = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);
    $b = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Data']);

    $this->actingAs($modelo->user)->get(route('meus-certificados.campos', $modelo))
        ->assertInertia(fn ($page) => $page
            ->component('meus-certificados/campos')
            ->where('modelo.id', $modelo->id)
            ->where('modelo.imagem_url', route('modelos.imagem', $modelo))
            ->where('campos.0.id', $a->id)
            ->where('campos.1.id', $b->id)
            ->where('pode_editar', true)
            ->has('fontes.Serif'));
});

test('leitor compartilhado vê a página sem permissão de edição', function () {
    [$modelo, $leitor] = modeloCompartilhado(podeEditar: false);

    $this->actingAs($leitor)->get(route('meus-certificados.campos', $modelo))
        ->assertInertia(fn ($page) => $page->where('pode_editar', false));
});

test('estranho não vê a página', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('meus-certificados.campos', $modelo))->assertForbidden();
});

test('novo campo recebe o primeiro nome livre e herda o estilo enviado', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Campo 1']);
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Campo 3']);

    $this->actingAs($modelo->user)->post(route('campos.store', $modelo), [
        'fonte_familia' => 'Cinzel',
        'cor' => '#1a1a1a',
        'x' => 50,
        'y' => 80,
    ])->assertRedirect();

    $novo = $modelo->campos()->where('nome', 'Campo 2')->sole();
    expect($novo->fonte_familia)->toBe('Cinzel')
        ->and($novo->cor)->toBe('#1a1a1a')
        ->and($novo->x)->toBe(50);
});

test('editor compartilhado atualiza parcialmente um campo', function () {
    [$modelo, $editor] = modeloCompartilhado(podeEditar: true);
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create(['x' => 10]);

    $this->actingAs($editor)->patch(route('campos.update', [$modelo, $campo]), [
        'x' => 320,
        'centralizado' => true,
        'espacamento_letras' => 1.5,
    ])->assertRedirect();

    $campo->refresh();
    expect($campo->x)->toBe(320)
        ->and($campo->centralizado)->toBeTrue()
        ->and($campo->espacamento_letras)->toBe(1.5)
        ->and($campo->y)->toBe(100);
});

test('renomear para nome já usado no modelo é recusado', function () {
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);
    $outro = CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Data']);

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $outro]), ['nome' => 'Nome'])
        ->assertSessionHasErrors('nome');
});

test('valores inválidos são recusados', function (array $dados, string $erro) {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $campo]), $dados)
        ->assertSessionHasErrors($erro);
})->with([
    'fonte fora do catálogo' => [['fonte_familia' => 'Comic Sans MS'], 'fonte_familia'],
    'cor não hex' => [['cor' => 'red'], 'cor'],
    'peso fora da escala' => [['peso_fonte' => 650], 'peso_fonte'],
    'x negativo' => [['x' => -1], 'x'],
]);

test('leitor compartilhado não cria, altera nem exclui campos', function () {
    [$modelo, $leitor] = modeloCompartilhado(podeEditar: false);
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($leitor)->post(route('campos.store', $modelo))->assertForbidden();
    $this->actingAs($leitor)->patch(route('campos.update', [$modelo, $campo]), ['x' => 1])->assertForbidden();
    $this->actingAs($leitor)->delete(route('campos.destroy', [$modelo, $campo]))->assertForbidden();
});

test('campo de outro modelo não é alcançável pela rota', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campoDeOutro = CampoCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->patch(route('campos.update', [$modelo, $campoDeOutro]), ['x' => 1])
        ->assertNotFound();
});

test('dono exclui um campo', function () {
    $modelo = ModeloCertificado::factory()->create();
    $campo = CampoCertificado::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)->delete(route('campos.destroy', [$modelo, $campo]))->assertRedirect();

    expect(CampoCertificado::count())->toBe(0);
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=CampoCertificadoTest`
Esperado: FAIL com `Route [meus-certificados.campos] not defined`.

- [ ] **Step 3: Criar o catálogo de fontes**

`config/fontes.php`:

```php
<?php

// Família exibida no editor => pasta em resources/fonts/ (arquivos .ttf chegam no Plano 2).
// As fontes de "Sistema" usam equivalentes livres com as mesmas medidas.
return [
    'grupos' => [
        'Sistema' => [
            'Georgia' => 'gelasio',
            'Arial' => 'liberation-sans',
            'Times New Roman' => 'liberation-serif',
            'Courier New' => 'liberation-mono',
        ],
        'Serif' => [
            'Cormorant Garamond' => 'cormorant-garamond',
            'EB Garamond' => 'eb-garamond',
            'Lora' => 'lora',
            'Merriweather' => 'merriweather',
            'Playfair Display' => 'playfair-display',
            'Libre Baskerville' => 'libre-baskerville',
        ],
        'Sem serifa' => [
            'Roboto' => 'roboto',
            'Montserrat' => 'montserrat',
            'Open Sans' => 'open-sans',
            'Lato' => 'lato',
            'Raleway' => 'raleway',
            'Quicksand' => 'quicksand',
        ],
        'Display' => [
            'Cinzel' => 'cinzel',
            'Crimson Text' => 'crimson-text',
            'Dancing Script' => 'dancing-script',
        ],
    ],
];
```

`app/Services/CatalogoFontes.php`:

```php
<?php

namespace App\Services;

class CatalogoFontes
{
    public const PESOS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    /** @return array<string, array<string, string>> */
    public static function grupos(): array
    {
        return config('fontes.grupos');
    }

    /** @return list<string> */
    public static function familias(): array
    {
        return collect(self::grupos())->flatMap(fn (array $familias) => array_keys($familias))->values()->all();
    }
}
```

- [ ] **Step 4: Criar o controller**

`app/Http/Controllers/CampoCertificadoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use App\Services\CatalogoFontes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampoCertificadoController extends Controller
{
    public function edit(Request $request, ModeloCertificado $modelo): Response
    {
        Gate::authorize('view', $modelo);

        return Inertia::render('meus-certificados/campos', [
            'modelo' => [
                'id' => $modelo->id,
                'title' => $modelo->title,
                'imagem_url' => route('modelos.imagem', $modelo),
            ],
            'campos' => $modelo->campos,
            'pode_editar' => $request->user()->can('editarCampos', $modelo),
            'fontes' => CatalogoFontes::grupos(),
        ]);
    }

    public function store(Request $request, ModeloCertificado $modelo): RedirectResponse
    {
        Gate::authorize('editarCampos', $modelo);

        $dados = $request->validate($this->regras($modelo));

        $modelo->campos()->create([
            ...$dados,
            'nome' => $dados['nome'] ?? $this->proximoNome($modelo),
        ]);

        return back();
    }

    public function update(Request $request, ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse
    {
        Gate::authorize('editarCampos', $modelo);

        $campo->update($request->validate($this->regras($modelo, $campo)));

        return back();
    }

    public function destroy(ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse
    {
        Gate::authorize('editarCampos', $modelo);

        $campo->delete();

        return back();
    }

    /** @return array<string, mixed> */
    private function regras(ModeloCertificado $modelo, ?CampoCertificado $campo = null): array
    {
        return [
            'nome' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('campos_certificado', 'nome')->where('modelo_id', $modelo->id)->ignore($campo?->id),
            ],
            'label' => ['sometimes', 'nullable', 'string', 'max:150'],
            'x' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'y' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'cor' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tamanho_fonte' => ['sometimes', 'integer', 'min:4', 'max:1000'],
            'fonte_familia' => ['sometimes', Rule::in(CatalogoFontes::familias())],
            'peso_fonte' => ['sometimes', 'integer', Rule::in(CatalogoFontes::PESOS)],
            'espacamento_letras' => ['sometimes', 'numeric', 'min:-50', 'max:200'],
            'centralizado' => ['sometimes', 'boolean'],
        ];
    }

    private function proximoNome(ModeloCertificado $modelo): string
    {
        $existentes = $modelo->campos()->pluck('nome')->all();
        $n = 1;

        while (in_array("Campo {$n}", $existentes, true)) {
            $n++;
        }

        return "Campo {$n}";
    }
}
```

- [ ] **Step 5: Registrar as rotas**

Em `routes/web.php`, adicione o import `use App\Http\Controllers\CampoCertificadoController;` e, no grupo `auth` + `verified`:

```php
    Route::get('meus-certificados/{modelo}/campos', [CampoCertificadoController::class, 'edit'])->name('meus-certificados.campos');
    Route::scopeBindings()->group(function () {
        Route::post('modelos/{modelo}/campos', [CampoCertificadoController::class, 'store'])->name('campos.store');
        Route::patch('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'update'])->name('campos.update');
        Route::delete('modelos/{modelo}/campos/{campo}', [CampoCertificadoController::class, 'destroy'])->name('campos.destroy');
    });
```

`scopeBindings` resolve `{campo}` pela relação `campos()` do modelo. Um campo de outro modelo retorna 404 antes mesmo de chegar à Policy.

- [ ] **Step 6: Criar a página placeholder**

`resources/js/pages/meus-certificados/campos.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export type CampoCertificado = {
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

export type GruposFontes = Record<string, Record<string, string>>;

type Props = {
    modelo: { id: string; title: string; imagem_url: string };
    campos: CampoCertificado[];
    pode_editar: boolean;
    fontes: GruposFontes;
};

export default function Campos({ modelo, campos, pode_editar }: Props) {
    return (
        <AppLayout>
            <Head title={`Campos — ${modelo.title}`} />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">{modelo.title}</h1>
                <img src={modelo.imagem_url} alt={modelo.title} className="max-w-full" />
                <p>{pode_editar ? 'Você pode editar os campos.' : 'Somente leitura.'}</p>
                <ul>{campos.map((c) => <li key={c.id}>{c.label || c.nome}</li>)}</ul>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 7: Rodar testes e build**

```bash
php artisan test --filter=CampoCertificadoTest && npm run build && npx tsc --noEmit
```

Esperado: todos os testes passando e build limpo.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: campos do certificado com catálogo de fontes e nomes únicos

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Compartilhamentos

**Files:**
- Create: `app/Http/Controllers/CompartilhamentoController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Compartilhamentos/CompartilhamentoTest.php`

**Interfaces:**
- Consumes: Policy (`gerenciarCompartilhamentos`) e o model `CompartilhamentoModelo` (email em minúsculas).
- Produces: rotas `compartilhamentos.store` (`POST /modelos/{modelo}/compartilhamentos`, corpo `{ email, pode_editar? }`), `compartilhamentos.update` (`PATCH /modelos/{modelo}/compartilhamentos/{compartilhamento}`, corpo `{ pode_editar }`) e `compartilhamentos.destroy` (`DELETE`). A lista de compartilhamentos chega pela prop `modelos[].compartilhamentos` da Task 5.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Compartilhamentos/CompartilhamentoTest.php`:

```php
<?php

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;

test('dono compartilha por email, normalizado em minúsculas e só leitura por padrão', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('compartilhamentos.store', $modelo), ['email' => '  Joao@Example.COM '])
        ->assertRedirect()->assertSessionHas('success');

    $c = CompartilhamentoModelo::sole();
    expect($c->email)->toBe('joao@example.com')->and($c->pode_editar)->toBeFalse();
});

test('pode compartilhar com quem ainda não tem conta', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)
        ->post(route('compartilhamentos.store', $modelo), ['email' => 'sem-conta@example.com', 'pode_editar' => true])
        ->assertSessionHasNoErrors();

    expect(CompartilhamentoModelo::sole()->pode_editar)->toBeTrue();
});

test('recusa email repetido no mesmo modelo, o próprio email do dono e email inválido', function (string $email) {
    $dono = User::factory()->create(['email' => 'dono@example.com']);
    $modelo = ModeloCertificado::factory()->for($dono)->create();
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'joao@example.com']);

    $this->actingAs($dono)
        ->post(route('compartilhamentos.store', $modelo), ['email' => $email])
        ->assertSessionHasErrors('email');
})->with(['JOAO@example.com', 'dono@example.com', 'nao-e-email']);

test('dono alterna pode_editar e remove o compartilhamento', function () {
    $modelo = ModeloCertificado::factory()->create();
    $c = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create();

    $this->actingAs($modelo->user)
        ->patch(route('compartilhamentos.update', [$modelo, $c]), ['pode_editar' => true])
        ->assertRedirect();
    expect($c->fresh()->pode_editar)->toBeTrue();

    $this->actingAs($modelo->user)
        ->delete(route('compartilhamentos.destroy', [$modelo, $c]))
        ->assertRedirect();
    expect(CompartilhamentoModelo::count())->toBe(0);
});

test('editor compartilhado não gerencia compartilhamentos', function () {
    $modelo = ModeloCertificado::factory()->create();
    $editor = User::factory()->create(['email' => 'editor@example.com']);
    $c = CompartilhamentoModelo::factory()->for($modelo, 'modelo')->editor()->create(['email' => 'editor@example.com']);

    $this->actingAs($editor)->post(route('compartilhamentos.store', $modelo), ['email' => 'x@example.com'])->assertForbidden();
    $this->actingAs($editor)->patch(route('compartilhamentos.update', [$modelo, $c]), ['pode_editar' => false])->assertForbidden();
    $this->actingAs($editor)->delete(route('compartilhamentos.destroy', [$modelo, $c]))->assertForbidden();
});

test('compartilhamento de outro modelo não é alcançável pela rota', function () {
    $modelo = ModeloCertificado::factory()->create();
    $deOutro = CompartilhamentoModelo::factory()->create();

    $this->actingAs($modelo->user)
        ->delete(route('compartilhamentos.destroy', [$modelo, $deOutro]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=CompartilhamentoTest`
Esperado: FAIL com `Route [compartilhamentos.store] not defined`.

- [ ] **Step 3: Criar o controller**

`app/Http/Controllers/CompartilhamentoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompartilhamentoController extends Controller
{
    public function store(Request $request, ModeloCertificado $modelo): RedirectResponse
    {
        Gate::authorize('gerenciarCompartilhamentos', $modelo);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $dados = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                Rule::notIn([$modelo->user->email]),
                Rule::unique('compartilhamentos_modelo', 'email')->where('modelo_id', $modelo->id),
            ],
            'pode_editar' => ['sometimes', 'boolean'],
        ], [
            'email.not_in' => 'Você não precisa compartilhar um modelo com você mesmo.',
            'email.unique' => 'Este modelo já está compartilhado com este email.',
        ]);

        $modelo->compartilhamentos()->create($dados);

        return back()->with('success', 'Modelo compartilhado.');
    }

    public function update(Request $request, ModeloCertificado $modelo, CompartilhamentoModelo $compartilhamento): RedirectResponse
    {
        Gate::authorize('gerenciarCompartilhamentos', $modelo);

        $compartilhamento->update($request->validate(['pode_editar' => ['required', 'boolean']]));

        return back();
    }

    public function destroy(ModeloCertificado $modelo, CompartilhamentoModelo $compartilhamento): RedirectResponse
    {
        Gate::authorize('gerenciarCompartilhamentos', $modelo);

        $compartilhamento->delete();

        return back()->with('success', 'Compartilhamento removido.');
    }
}
```

- [ ] **Step 4: Registrar as rotas**

Em `routes/web.php`, adicione o import `use App\Http\Controllers\CompartilhamentoController;` e, **dentro do mesmo** `Route::scopeBindings()->group(...)` da Task 6:

```php
        Route::post('modelos/{modelo}/compartilhamentos', [CompartilhamentoController::class, 'store'])->name('compartilhamentos.store');
        Route::patch('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'update'])->name('compartilhamentos.update');
        Route::delete('modelos/{modelo}/compartilhamentos/{compartilhamento}', [CompartilhamentoController::class, 'destroy'])->name('compartilhamentos.destroy');
```

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --filter=CompartilhamentoTest`
Esperado: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: compartilhamento de modelos por email

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Histórico e exclusão de conta

**Files:**
- Create: `app/Http/Controllers/HistoricoController.php`, `resources/js/pages/historico/index.tsx`
- Modify: `routes/web.php`, `app/Http/Controllers/Settings/ProfileController.php` (método `destroy`), `resources/js/components/delete-user.tsx`
- Test: `tests/Feature/Historico/HistoricoTest.php`, `tests/Feature/Conta/ExcluirContaTest.php`

**Interfaces:**
- Consumes: `User::geracoes()`, `User::modelos()`, `User->tem_senha`, job `RemoverImagemModelo`.
- Produces:
  - Rota `historico.index` (`GET /historico`) com a prop `geracoes: { id, modelo_id, modelo_title, campos_valores, created_at }[]` (no máximo 50, mais recentes primeiro).
  - `DELETE` de conta do kit aceitando `password` (usuários com senha) ou `confirmacao` igual ao email (usuários só-Google).

- [ ] **Step 1: Descobrir a rota de exclusão de conta do kit**

```bash
php artisan route:list --method=DELETE --path=settings
```

Anote o nome da rota (esperado: `profile.destroy`, `DELETE settings/profile`). Se for outro, use o nome observado nos testes abaixo.

- [ ] **Step 2: Escrever os testes que falham**

`tests/Feature/Historico/HistoricoTest.php`:

```php
<?php

use App\Models\GeracaoCertificado;
use App\Models\User;

test('mostra as 50 gerações mais recentes do próprio usuário', function () {
    $user = User::factory()->create();
    GeracaoCertificado::factory()->for($user)->count(55)->sequence(
        fn ($seq) => ['created_at' => now()->subMinutes(55 - $seq->index)],
    )->create();
    GeracaoCertificado::factory()->create();

    $maisRecente = $user->geracoes()->latest('created_at')->first();

    $this->actingAs($user)->get(route('historico.index'))
        ->assertInertia(fn ($page) => $page
            ->component('historico/index')
            ->has('geracoes', 50)
            ->where('geracoes.0.id', $maisRecente->id)
            ->has('geracoes.0', fn ($g) => $g
                ->hasAll(['id', 'modelo_id', 'modelo_title', 'campos_valores', 'created_at'])));
});
```

`tests/Feature/Conta/ExcluirContaTest.php`:

```php
<?php

use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('s3'));

test('usuário com senha exclui a conta e as imagens dos modelos saem do bucket', function () {
    $user = User::factory()->create();
    $modelo = ModeloCertificado::factory()->for($user)->create();
    Storage::disk('s3')->put($modelo->image_path, 'png');

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertGuest();
    expect(User::count())->toBe(0)->and(ModeloCertificado::count())->toBe(0);
    Storage::disk('s3')->assertMissing($modelo->image_path);
});

test('usuário só-Google confirma digitando o próprio email', function () {
    $user = User::factory()->google()->create(['email' => 'ana@example.com']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirmacao' => 'ana@example.com'])
        ->assertRedirect('/');

    expect(User::count())->toBe(0);
});

test('usuário só-Google com confirmação errada não exclui', function () {
    $user = User::factory()->google()->create(['email' => 'ana@example.com']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['confirmacao' => 'outro@example.com'])
        ->assertSessionHasErrors('confirmacao');

    expect(User::count())->toBe(1);
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `php artisan test --filter="HistoricoTest|ExcluirContaTest"`
Esperado: FAIL. O histórico não tem rota, e a exclusão só-Google é recusada por falta de `password`. O caso com senha pode já passar, mas a imagem continua no bucket, porque cascade no banco não dispara o evento `deleted` do model.

- [ ] **Step 4: Criar o controller de histórico e a rota**

`app/Http/Controllers/HistoricoController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoricoController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('historico/index', [
            'geracoes' => $request->user()->geracoes()
                ->latest('created_at')
                ->limit(50)
                ->get(['id', 'modelo_id', 'modelo_title', 'campos_valores', 'created_at']),
        ]);
    }
}
```

Em `routes/web.php`, adicione o import `use App\Http\Controllers\HistoricoController;` e, no grupo `auth` + `verified`:

```php
    Route::get('historico', [HistoricoController::class, 'index'])->name('historico.index');
```

`resources/js/pages/historico/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export type Geracao = {
    id: string;
    modelo_id: string | null;
    modelo_title: string;
    campos_valores: Record<string, string>;
    created_at: string;
};

export default function Historico({ geracoes }: { geracoes: Geracao[] }) {
    return (
        <AppLayout>
            <Head title="Histórico" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Histórico</h1>
                <ul>
                    {geracoes.map((g) => (
                        <li key={g.id}>
                            {g.modelo_title} — {new Date(g.created_at).toLocaleString('pt-BR')}
                        </li>
                    ))}
                </ul>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 5: Adaptar a exclusão de conta**

Em `app/Http/Controllers/Settings/ProfileController.php`, substitua o método `destroy` pela versão abaixo. Se o kit usar um Form Request específico na assinatura (ex.: `ProfileDeleteRequest`), troque-o por `Request` e remova o import que sobrar.

```php
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(
            $user->tem_senha
                ? ['password' => ['required', 'current_password']]
                : ['confirmacao' => ['required', Rule::in([$user->email])]],
            ['confirmacao.in' => 'Digite o seu email para confirmar.'],
        );

        // O cascade do banco não dispara o evento "deleted" dos modelos: as imagens são removidas aqui.
        $imagens = $user->modelos()->pluck('image_path');

        Auth::logout();
        $user->delete();
        $imagens->each(fn (string $path) => RemoverImagemModelo::dispatch($path));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
```

Garanta os imports no topo: `use App\Jobs\RemoverImagemModelo;`, `use Illuminate\Http\Request;`, `use Illuminate\Http\RedirectResponse;`, `use Illuminate\Support\Facades\Auth;`, `use Illuminate\Validation\Rule;`.

- [ ] **Step 6: Adaptar o diálogo de exclusão no frontend**

Em `resources/js/components/delete-user.tsx`, leia o usuário compartilhado:

```tsx
import { usePage } from '@inertiajs/react';
// ...
const { auth } = usePage<{ auth: { user: { email: string; tem_senha: boolean } } }>().props;
```

No diálogo, troque o bloco do campo de senha por:

```tsx
{auth.user.tem_senha ? (
    <>
        {/* bloco original do campo "password" do kit, sem alterações */}
    </>
) : (
    <div className="grid gap-2">
        <Label htmlFor="confirmacao">Digite {auth.user.email} para confirmar</Label>
        <Input id="confirmacao" name="confirmacao" autoComplete="off" />
        <InputError message={errors.confirmacao} />
    </div>
)}
```

Mantenha o bloco original do campo `password` **literalmente** dentro do primeiro ramo. `Label`, `Input` e `InputError` já são importados pelo componente do kit. Se o kit usa `useForm`, adicione `confirmacao: ''` ao estado inicial. Se usa o componente `<Form>`, o atributo `name` basta.

- [ ] **Step 7: Rodar a suíte inteira e o build**

```bash
php artisan test && npm run build && npx tsc --noEmit
```

Esperado: tudo verde, incluindo os testes de perfil do kit ("user can delete their account" e "correct password must be provided").

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: histórico de gerações e exclusão de conta para usuários Google

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Verificação final do Plano 1

- [ ] `php artisan test` verde, com os testes do kit e os 8 arquivos novos.
- [ ] `npm run build && npx tsc --noEmit` sem erros.
- [ ] `php artisan route:list --except-vendor` mostra `meus-certificados.*`, `modelos.*`, `campos.*`, `compartilhamentos.*`, `historico.index` e `auth.google*`.
- [ ] Teste manual com `composer run dev` e `docker compose up -d`: registrar com email, criar um modelo com imagem e ver a imagem em `/meus-certificados/{id}/campos`. Com `GOOGLE_CLIENT_ID/SECRET` reais e a URI `http://localhost:8000/auth/google/callback` cadastrada no Google Console, entrar pelo Google.
- [ ] Nenhuma referência ao Supabase: `grep -ri supabase app config resources routes` sem resultados.
