# PequenaVia Laravel — Plano 2: Geração do certificado

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gerar o PNG do certificado no servidor, com Imagick: catálogo de fontes em TTF versionado, serviço `GeradorCertificado`, endpoint de geração com histórico e a rota que serve as fontes ao editor.

**Architecture:** `CatalogoFontes` resolve família + peso para um arquivo TTF em `resources/fonts/`. `GeradorCertificado` recebe os bytes do fundo, os campos e os valores, e devolve o PNG. Cada texto é desenhado numa camada transparente ampliada 8× e reduzida depois (supersampling), com a linha de base calculada como no CSS `line-height: 1` do editor. O controller só autoriza, valida, lê o fundo do bucket, chama o serviço e grava o histórico.

**Tech Stack:** PHP 8.5, Laravel 13, extensão Imagick (ImageMagick 7.1), Pest, Google Fonts (só para baixar os TTFs, uma vez).

**Spec:** `docs/superpowers/specs/2026-10-06-migracao-laravel-design.md`, seção 7 (este plano a corrige na Task 6).
**Depende de:** Plano 1 (já em `main`).
**Antes de começar:** `git checkout -b feat/geracao` (parta de `main`); o serviço Docker (`docker compose up -d`) e o `export PATH` do `php.new` precisam estar de pé.

## O que a pesquisa mostrou (e por que o plano difere do spec)

Antes de escrever este plano foi feito um spike com o Imagick do `php.new`, comparado ao código do canvas original e ao Chrome real (mesmas fontes, `line-height: 1`).

| Achado                                                                                       | Dado medido                                                                                                 | Consequência                                                                                                                                                   |
| -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| O CSS do Google Fonts, pedido sem User-Agent de navegador, devolve **TTF estático por peso** | 10 famílias testadas: nenhuma com tabela `fvar`; hashes diferentes entre 400 e 700                          | O ImageMagick não escolhe eixos de fonte variável, então este é o único jeito de ele renderizar pesos corretos.                                                |
| Peso que a família não tem responde **HTTP 400**                                             | Cinzel 100, Lato 600, Tinos 600 etc.                                                                        | O comando de download trata 400 como "peso inexistente".                                                                                                       |
| O spec dizia `y + ascender` (queryFontMetrics)                                               | O Imagick desenhava o texto 4 a 12 px mais baixo que o canvas                                               | **Errado.** A linha de base certa é `(tamanho + ascender + descender) / 2` a partir do topo da caixa de linha.                                                 |
| Preview do editor = `top: y` + `line-height: 1`                                              | Chrome mediu `41,5 / 48 / 59 / 26,5 / 31,5 / 20` px; a fórmula dá `41,6 / 48,1 / 59,0 / 26,9 / 31,7 / 20,3` | A fórmula acompanha o navegador em até 0,44 px. O canvas antigo errava de 2 a 3 px em algumas fontes.                                                          |
| O Imagick arredonda os avanços das letras em escala 1×                                       | Largura em 1×: 484 contra 482,88 em 16× (Lora)                                                              | Medir e desenhar ampliado (8×) e reduzir resolve; `type:hinting=off` não tem efeito.                                                                           |
| O Imagick só aplica o kerning da tabela `kern`, não o da GPOS que o navegador aplica         | Largura do texto: Lora +0,4%, Cinzel +1,5%, Dancing Script +3,1%; Roboto, Gelasio e Arimo +0,2 a 0,3%       | **Limitação aceita e documentada:** texto até ~3% mais largo em fontes que dependem de GPOS. Centralizado continua centralizado (o texto abre simetricamente). |
| `pango:` não existe no Imagick do `php.new`                                                  | `NoDecodeDelegateForThisImageFormat`                                                                        | Descartado: dev e produção divergiriam.                                                                                                                        |
| Catálogo completo                                                                            | 99 arquivos, 18 MB                                                                                          | Cabe no git; geração e build não dependem de rede.                                                                                                             |
| Imagem de 35 MB (3500×3500)                                                                  | 1,6 s no total, pico de 644 MB de memória                                                                   | Limite de memória do Imagick em 1 GiB; acima disso ele usa disco.                                                                                              |

## Global Constraints

- PHP 8.5, Laravel 13, Pest. Mensagens ao usuário em português; a mensagem de uma exceção interna nunca vai para o cliente.
- Saída da geração: sempre PNG, com as dimensões do fundo (depois de aplicar a orientação EXIF).
- Texto do campo: até 500 caracteres. Só entram valores de chaves que são `nome` de um campo do modelo.
- Limite de gerações: 60 por minuto por usuário (`certificados.geracoes_por_minuto`).
- Quem não pode ver o modelo recebe 404; quem vê mas não pode gerar, 403 (Policy do Plano 1, ação `gerar`).
- Fontes: só o catálogo de `config/fontes.php`; arquivos em `resources/fonts/{pasta}/{peso}.ttf`; peso ausente usa o mais próximo (empate vai para o mais pesado) e registra aviso no log.
- Imagick: limite de memória de 1 GiB; camada de texto com no máximo 16384 px de lado e 16.000.000 px de área já ampliada.
- Testes: fakes criados dentro de cada teste; `Storage::fake('s3')`; sem rede (`Http::preventStrayRequests()`).
- Diretrizes do Laravel Boost valem: `php artisan make:*` com `--no-interaction`, `vendor/bin/pint --dirty --format agent` depois de editar PHP, testes em Pest.
- Commits terminam com a linha `Co-Authored-By` indicada na sessão (nas execuções anteriores: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`).
- Todo shell novo precisa de `export PATH="$HOME/.config/herd-lite/bin:$PATH"` até o terminal ser reiniciado.

---

## Mapa de arquivos

```
config/fontes.php                                          # Modify: diretorio, slugs de sistema, google (Task 1)
config/certificados.php                                    # Create: limite por minuto (Task 4)
app/Services/CatalogoFontes.php                            # Modify: arquivo(), pastas(), pesos (Task 1)
app/Exceptions/FonteNaoEncontradaException.php             # Create (Task 1)
app/Console/Commands/BaixarFontes.php                      # Create (Task 2)
resources/fonts/{pasta}/{peso}.ttf  +  LICENCAS.md         # Create via comando (Task 2)
app/Exceptions/ImagemInvalidaException.php                 # Create (Task 3)
app/Exceptions/TextoGrandeDemaisException.php              # Create (Task 3)
app/Services/GeradorCertificado.php                        # Create (Task 3)
tests/Pest.php                                             # Modify: helpers de imagem (Task 3)
composer.json, composer.lock                                # Modify: requisito ext-imagick (Task 3)
app/Http/Requests/GerarCertificadoRequest.php              # Create (Task 4)
app/Http/Controllers/GeracaoCertificadoController.php      # Create (Task 4)
app/Providers/AppServiceProvider.php                       # Modify: rate limiter (Task 4)
app/Http/Controllers/FonteController.php                   # Create (Task 5)
app/Http/Controllers/CampoCertificadoController.php        # Modify: pesos_das_fontes (Task 5)
routes/web.php                                             # Modify (Tasks 4 e 5)
resources/js/pages/meus-certificados/campos.tsx            # Modify: tipo da prop (Task 5)
tests/Feature/Services/CatalogoFontesTest.php              # Task 1
tests/Feature/Console/BaixarFontesTest.php                 # Task 2
tests/Feature/Fontes/ArquivosDeFontesTest.php              # Task 2
tests/Feature/Services/GeradorCertificadoTest.php          # Task 3
tests/Feature/Services/GeradorCertificadoVisualTest.php    # Task 3
tests/Fixtures/certificados/{a..f}.png                     # Task 3 (imagens de referência)
tests/Feature/Certificados/GeracaoCertificadoControllerTest.php   # Task 4
tests/Feature/Fontes/FonteControllerTest.php               # Task 5
docs/superpowers/specs/2026-10-06-migracao-laravel-design.md      # Modify (Task 6)
```

---

### Task 1: Resolver de fontes (`CatalogoFontes::arquivo`)

**Files:**

- Modify: `config/fontes.php`, `app/Services/CatalogoFontes.php`
- Create: `app/Exceptions/FonteNaoEncontradaException.php`
- Test: `tests/Feature/Services/CatalogoFontesTest.php`

**Interfaces:**

- Consumes: `config('fontes.grupos')` do Plano 1 (`grupo => [família exibida => pasta]`).
- Produces:
    - `config('fontes.diretorio'): string` (padrão `resource_path('fonts')`) e `config('fontes.google'): array<string, string>` (família exibida => nome no Google Fonts, só para as de sistema).
    - `CatalogoFontes::pastas(): array<string, string>` (família exibida => pasta).
    - `CatalogoFontes::pesosDisponiveis(string $pasta): list<int>` (ordenado).
    - `CatalogoFontes::pesosPorPasta(): array<string, list<int>>`.
    - `CatalogoFontes::arquivo(string $familia, int $peso): string` (caminho absoluto; lança `FonteNaoEncontradaException`).

- [ ] **Step 1: Atualizar `config/fontes.php`**

As fontes de "Sistema" passam a usar as equivalentes livres do Google Fonts com as mesmas medidas (Arimo = Arial, Tinos = Times New Roman, Cousine = Courier New, Gelasio = Georgia). Substitua o arquivo inteiro:

```php
<?php

/*
| Catálogo de fontes dos campos do certificado.
|
| Cada família exibida no editor aponta para uma pasta de arquivos .ttf (um por peso) dentro de
| `diretorio`. As fontes de "Sistema" usam equivalentes livres com as mesmas medidas, porque as
| originais da Microsoft não existem no servidor; `google` diz de qual família do Google Fonts
| cada uma é baixada. As demais são baixadas pelo próprio nome.
*/

return [
    'diretorio' => resource_path('fonts'),

    'grupos' => [
        'Sistema' => [
            'Georgia' => 'gelasio',
            'Arial' => 'arimo',
            'Times New Roman' => 'tinos',
            'Courier New' => 'cousine',
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

    'google' => [
        'Georgia' => 'Gelasio',
        'Arial' => 'Arimo',
        'Times New Roman' => 'Tinos',
        'Courier New' => 'Cousine',
    ],
];
```

- [ ] **Step 2: Criar a exceção**

```bash
php artisan make:exception FonteNaoEncontradaException --no-interaction
```

Substitua o conteúdo de `app/Exceptions/FonteNaoEncontradaException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class FonteNaoEncontradaException extends RuntimeException {}
```

- [ ] **Step 3: Escrever o teste que falha**

`tests/Feature/Services/CatalogoFontesTest.php`:

```php
<?php

use App\Exceptions\FonteNaoEncontradaException;
use App\Services\CatalogoFontes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/** @var list<string> $GLOBALS['fontes_temporarias'] */
$GLOBALS['fontes_temporarias'] = [];

afterEach(function () {
    foreach ($GLOBALS['fontes_temporarias'] as $diretorio) {
        File::deleteDirectory($diretorio);
    }

    $GLOBALS['fontes_temporarias'] = [];
});

/**
 * Cria um diretório temporário de fontes com os pesos indicados e aponta o catálogo para ele.
 *
 * @param  array<string, list<int>>  $pesosPorPasta
 */
function fontesTemporarias(array $pesosPorPasta): string
{
    $diretorio = sys_get_temp_dir().'/fontes-'.uniqid();
    $GLOBALS['fontes_temporarias'][] = $diretorio;
    File::ensureDirectoryExists($diretorio);

    foreach ($pesosPorPasta as $pasta => $pesos) {
        File::ensureDirectoryExists("{$diretorio}/{$pasta}");

        foreach ($pesos as $peso) {
            File::put("{$diretorio}/{$pasta}/{$peso}.ttf", 'ttf');
        }
    }

    config(['fontes.diretorio' => $diretorio]);

    return $diretorio;
}

it('devolve o arquivo do peso pedido quando ele existe', function () {
    $diretorio = fontesTemporarias(['lora' => [400, 700]]);
    Log::spy();

    expect(CatalogoFontes::arquivo('Lora', 700))->toBe("{$diretorio}/lora/700.ttf");
    Log::shouldNotHaveReceived('warning');
});

it('usa o peso mais próximo e avisa no log; no empate vence o mais pesado', function (int $pedido, int $esperado) {
    $diretorio = fontesTemporarias(['lora' => [400, 700]]);
    Log::spy();

    expect(CatalogoFontes::arquivo('Lora', $pedido))->toBe("{$diretorio}/lora/{$esperado}.ttf");
    Log::shouldHaveReceived('warning')->once();
})->with([
    'mais perto do 400' => [450, 400],
    'mais perto do 700' => [650, 700],
    'empate entre 400 e 700' => [550, 700],
    'abaixo de todos' => [100, 400],
    'acima de todos' => [900, 700],
]);

it('lança exceção para uma família fora do catálogo', function () {
    fontesTemporarias(['lora' => [400]]);

    CatalogoFontes::arquivo('Comic Sans MS', 400);
})->throws(FonteNaoEncontradaException::class, 'Comic Sans MS');

it('lança exceção quando a família do catálogo não tem nenhum arquivo', function () {
    fontesTemporarias(['lora' => [400]]);

    CatalogoFontes::arquivo('Cinzel', 400);
})->throws(FonteNaoEncontradaException::class, 'Cinzel');

it('lista os pesos disponíveis de cada pasta em ordem crescente', function () {
    fontesTemporarias(['lora' => [700, 400]]);

    $pesos = CatalogoFontes::pesosPorPasta();

    expect($pesos['lora'])->toBe([400, 700])
        ->and($pesos['cinzel'])->toBe([]);
});
```

- [ ] **Step 4: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Services/CatalogoFontesTest.php`
Expected: FAIL (`Call to undefined method App\Services\CatalogoFontes::arquivo()` e similares).

- [ ] **Step 5: Implementar**

Substitua `app/Services/CatalogoFontes.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\FonteNaoEncontradaException;
use Illuminate\Support\Facades\Log;

class CatalogoFontes
{
    /** @var list<int> */
    public const PESOS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    /**
     * Grupos de fontes: grupo => (família exibida => pasta dos arquivos).
     *
     * @return array<string, array<string, string>>
     */
    public static function grupos(): array
    {
        return config('fontes.grupos');
    }

    /**
     * @return list<string>
     */
    public static function familias(): array
    {
        return collect(self::grupos())
            ->flatMap(fn (array $familias) => array_keys($familias))
            ->values()
            ->all();
    }

    /**
     * Família exibida => pasta dos arquivos.
     *
     * @return array<string, string>
     */
    public static function pastas(): array
    {
        return collect(self::grupos())->collapse()->all();
    }

    /**
     * Pesos que têm arquivo na pasta, em ordem crescente.
     *
     * @return list<int>
     */
    public static function pesosDisponiveis(string $pasta): array
    {
        $arquivos = glob(config('fontes.diretorio')."/{$pasta}/*.ttf") ?: [];

        $pesos = array_map(fn (string $arquivo) => (int) basename($arquivo, '.ttf'), $arquivos);
        sort($pesos);

        return $pesos;
    }

    /**
     * @return array<string, list<int>>
     */
    public static function pesosPorPasta(): array
    {
        return collect(self::pastas())
            ->unique()
            ->mapWithKeys(fn (string $pasta) => [$pasta => self::pesosDisponiveis($pasta)])
            ->all();
    }

    /**
     * Caminho do arquivo da fonte. Se a família não tem o peso pedido, usa o mais próximo
     * (no empate, o mais pesado) e registra um aviso.
     *
     * @throws FonteNaoEncontradaException
     */
    public static function arquivo(string $familia, int $peso): string
    {
        $pasta = self::pastas()[$familia]
            ?? throw new FonteNaoEncontradaException("A fonte \"{$familia}\" não está no catálogo.");

        $pesos = self::pesosDisponiveis($pasta);

        if ($pesos === []) {
            throw new FonteNaoEncontradaException("Não há arquivos de fonte para \"{$familia}\".");
        }

        $usado = self::pesoMaisProximo($pesos, $peso);

        if ($usado !== $peso) {
            Log::warning('Peso de fonte indisponível; usando o mais próximo.', [
                'familia' => $familia,
                'pedido' => $peso,
                'usado' => $usado,
            ]);
        }

        return config('fontes.diretorio')."/{$pasta}/{$usado}.ttf";
    }

    /**
     * @param  non-empty-list<int>  $pesos
     */
    private static function pesoMaisProximo(array $pesos, int $pedido): int
    {
        $melhor = $pesos[0];

        foreach ($pesos as $peso) {
            $distancia = abs($peso - $pedido);
            $melhorDistancia = abs($melhor - $pedido);

            if ($distancia < $melhorDistancia || ($distancia === $melhorDistancia && $peso > $melhor)) {
                $melhor = $peso;
            }
        }

        return $melhor;
    }
}
```

- [ ] **Step 6: Rodar e ver passar; formatar e rodar a suíte**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Services/CatalogoFontesTest.php
php artisan test --compact
```

Expected: o arquivo novo passa (5 testes + datasets) e a suíte inteira continua verde (125 + novos).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: resolve família e peso para o arquivo de fonte mais próximo

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

### Task 2: Comando `fontes:baixar` e os arquivos de fonte

**Files:**

- Create: `app/Console/Commands/BaixarFontes.php`, `resources/fonts/**/*.ttf` (99 arquivos, 18 MB), `resources/fonts/LICENCAS.md`
- Test: `tests/Feature/Console/BaixarFontesTest.php`, `tests/Feature/Fontes/ArquivosDeFontesTest.php`

**Interfaces:**

- Consumes: `CatalogoFontes::pastas()`, `CatalogoFontes::PESOS`, `CatalogoFontes::pesosDisponiveis()`, `config('fontes.diretorio')`, `config('fontes.google')` (Task 1).
- Produces: comando `php artisan fontes:baixar {--familia=*}`; arquivos `resources/fonts/{pasta}/{peso}.ttf` para toda família do catálogo.

- [ ] **Step 1: Escrever os testes do comando (falham)**

`tests/Feature/Console/BaixarFontesTest.php`:

```php
<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/** @var list<string> $GLOBALS['downloads_temporarios'] */
$GLOBALS['downloads_temporarios'] = [];

afterEach(function () {
    foreach ($GLOBALS['downloads_temporarios'] as $diretorio) {
        File::deleteDirectory($diretorio);
    }

    $GLOBALS['downloads_temporarios'] = [];
});

/**
 * Aponta o catálogo para um diretório vazio e para duas famílias: Lora e Arial (baixada como Arimo).
 */
function catalogoParaDownload(): string
{
    $diretorio = sys_get_temp_dir().'/baixar-fontes-'.uniqid();
    $GLOBALS['downloads_temporarios'][] = $diretorio;

    config([
        'fontes.diretorio' => $diretorio,
        'fontes.grupos' => ['Serif' => ['Lora' => 'lora'], 'Sistema' => ['Arial' => 'arimo']],
        'fontes.google' => ['Arial' => 'Arimo'],
    ]);

    return $diretorio;
}

/**
 * Simula o Google Fonts: só os pesos 400 e 700 existem; os outros respondem 400, como o serviço real.
 */
function googleFontsFalso(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'fonts.googleapis.com/css2*' => function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $consulta);
            [$familia, $peso] = explode(':wght@', $consulta['family']);

            if (! in_array($peso, ['400', '700'], true)) {
                return Http::response('Invalid', 400);
            }

            $pasta = strtolower($familia);

            return Http::response("@font-face { font-family: '{$familia}'; src: url(https://fonts.gstatic.com/s/{$pasta}/{$peso}.ttf) format('truetype'); }");
        },
        'fonts.gstatic.com/*' => Http::response('bytes-da-fonte'),
    ]);
}

it('baixa um arquivo por peso existente e ignora os pesos que a família não tem', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar')->assertSuccessful();

    expect(File::get("{$diretorio}/lora/400.ttf"))->toBe('bytes-da-fonte');
    expect(File::exists("{$diretorio}/lora/700.ttf"))->toBeTrue();
    expect(File::exists("{$diretorio}/lora/100.ttf"))->toBeFalse();
});

it('baixa as fontes de sistema pelas equivalentes livres do Google', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar')->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'family=Arimo'));
    expect(File::exists("{$diretorio}/arimo/400.ttf"))->toBeTrue();
});

it('baixa só a família pedida', function () {
    $diretorio = catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar', ['--familia' => ['Lora']])->assertSuccessful();

    expect(File::isDirectory("{$diretorio}/lora"))->toBeTrue()
        ->and(File::isDirectory("{$diretorio}/arimo"))->toBeFalse();
});

it('pede o CSS sem User-Agent de navegador, para o Google devolver TTF estático', function () {
    catalogoParaDownload();
    googleFontsFalso();

    $this->artisan('fontes:baixar', ['--familia' => ['Lora']])->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'googleapis')
        && str_starts_with($request->header('User-Agent')[0], 'curl/'));
});

it('falha quando o Google não devolve nenhum arquivo da família', function () {
    catalogoParaDownload();
    Http::preventStrayRequests();
    Http::fake(['fonts.googleapis.com/*' => Http::response('Invalid', 400)]);

    $this->artisan('fontes:baixar')->assertFailed();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Console/BaixarFontesTest.php`
Expected: FAIL (`The command "fontes:baixar" does not exist`).

- [ ] **Step 3: Implementar o comando**

```bash
php artisan make:command BaixarFontes --no-interaction
```

Substitua `app/Console/Commands/BaixarFontes.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\CatalogoFontes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BaixarFontes extends Command
{
    /**
     * Sem User-Agent de navegador o Google Fonts devolve um TTF estático por peso, que o ImageMagick
     * sabe usar. Com um navegador ele serviria fontes variáveis em WOFF2.
     */
    private const USER_AGENT = 'curl/8.7.1';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fontes:baixar {--familia=* : Baixa só estas famílias (nome exibido no editor)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Baixa do Google Fonts os arquivos TTF do catálogo de fontes';

    public function handle(): int
    {
        $familias = collect(CatalogoFontes::pastas());

        if ($this->option('familia') !== []) {
            $familias = $familias->only($this->option('familia'));
        }

        $falhas = 0;

        foreach ($familias as $familia => $pasta) {
            $baixados = $this->baixarFamilia($familia, $pasta);

            if ($baixados === 0) {
                $this->error("{$familia}: o Google Fonts não devolveu nenhum arquivo.");
                $falhas++;

                continue;
            }

            $this->info("{$familia}: {$baixados} arquivo(s) em {$pasta}/");
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Baixa um arquivo por peso que a família oferece e devolve quantos baixou.
     */
    private function baixarFamilia(string $familia, string $pasta): int
    {
        $nomeNoGoogle = config("fontes.google.{$familia}", $familia);
        $baixados = 0;

        foreach (CatalogoFontes::PESOS as $peso) {
            $css = Http::withUserAgent(self::USER_AGENT)->get($this->urlDoCss($nomeNoGoogle, $peso));

            // A família não tem este peso.
            if ($css->status() === 400) {
                continue;
            }

            $css->throw();

            if (! preg_match('/url\(([^)]+\.ttf)\)/', $css->body(), $achado)) {
                $this->warn("{$familia} {$peso}: a resposta do Google não traz um arquivo .ttf.");

                continue;
            }

            $destino = config('fontes.diretorio')."/{$pasta}";
            File::ensureDirectoryExists($destino);
            File::put("{$destino}/{$peso}.ttf", Http::get($achado[1])->throw()->body());

            $baixados++;
        }

        return $baixados;
    }

    private function urlDoCss(string $familia, int $peso): string
    {
        return 'https://fonts.googleapis.com/css2?family='.urlencode($familia).":wght@{$peso}&display=swap";
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Console/BaixarFontesTest.php
```

Expected: PASS (5 testes).

- [ ] **Step 5: Baixar as fontes de verdade (usa a rede)**

```bash
php artisan fontes:baixar
find resources/fonts -name '*.ttf' | wc -l
du -sh resources/fonts
```

Expected: uma linha `Família: N arquivo(s) em pasta/` para cada uma das 19 famílias, nenhuma linha de erro; `99` arquivos e cerca de `18M`. Se uma família falhar, rode de novo só com ela: `php artisan fontes:baixar --familia="Nome da Família"`.

- [ ] **Step 6: Escrever os testes de integridade dos arquivos baixados**

`tests/Feature/Fontes/ArquivosDeFontesTest.php`:

```php
<?php

use App\Services\CatalogoFontes;

it('tem ao menos um arquivo de fonte para cada família do catálogo', function () {
    $semArquivo = collect(CatalogoFontes::pastas())
        ->reject(fn (string $pasta) => CatalogoFontes::pesosDisponiveis($pasta) !== [])
        ->keys()
        ->all();

    expect($semArquivo)->toBe([]);
});

it('guarda só arquivos TrueType de verdade, e não uma página de erro salva como .ttf', function () {
    $invalidos = collect(glob(config('fontes.diretorio').'/*/*.ttf'))
        ->reject(fn (string $arquivo) => file_get_contents($arquivo, false, null, 0, 4) === "\x00\x01\x00\x00")
        ->map(fn (string $arquivo) => basename(dirname($arquivo)).'/'.basename($arquivo))
        ->values()
        ->all();

    expect($invalidos)->toBe([]);
});
```

- [ ] **Step 7: Rodar os testes de integridade**

Run: `php artisan test --compact tests/Feature/Fontes/ArquivosDeFontesTest.php`
Expected: PASS (2 testes). Se o segundo listar arquivos, apague-os e baixe a família de novo.

- [ ] **Step 8: Avisar as licenças**

Os arquivos são redistribuídos com o repositório; a licença OFL pede que o aviso acompanhe as fontes. Crie `resources/fonts/LICENCAS.md`:

```markdown
# Licenças das fontes

Os arquivos `.ttf` desta pasta foram baixados do [Google Fonts](https://fonts.google.com) pelo comando
`php artisan fontes:baixar` e são distribuídos sob a **SIL Open Font License 1.1** ou a
**Apache License 2.0**, conforme a família. As duas permitem usar, incorporar e redistribuir as fontes.

O texto e o autor de cada licença estão na página da família no Google Fonts (seção _License_) e no
repositório <https://github.com/google/fonts>, na pasta de cada família.

As fontes de "Sistema" do editor usam equivalentes livres com as mesmas medidas:

| Exibida no editor | Arquivo usado |
| ----------------- | ------------- |
| Georgia           | Gelasio       |
| Arial             | Arimo         |
| Times New Roman   | Tinos         |
| Courier New       | Cousine       |
```

```bash
npx vp fmt resources/fonts/LICENCAS.md
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: comando fontes:baixar e catálogo de fontes TTF versionado

99 arquivos do Google Fonts (18 MB), um por peso, para a geração não depender de rede.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `GeradorCertificado`

**Files:**

- Create: `app/Services/GeradorCertificado.php`, `app/Exceptions/ImagemInvalidaException.php`, `app/Exceptions/TextoGrandeDemaisException.php`, `tests/Fixtures/certificados/{a,b,c,d,e,f}.png`
- Modify: `composer.json` (requisito `ext-imagick`), `tests/Pest.php` (helpers)
- Test: `tests/Feature/Services/GeradorCertificadoTest.php`, `tests/Feature/Services/GeradorCertificadoVisualTest.php`

**Interfaces:**

- Consumes: `CatalogoFontes::arquivo(string $familia, int $peso): string` (Task 1) e os arquivos de fonte (Task 2); atributos de `CampoCertificado`: `nome`, `x`, `y`, `cor`, `tamanho_fonte` (int), `fonte_familia`, `peso_fonte` (int), `espacamento_letras` (float), `centralizado` (bool).
- Produces:
    - `GeradorCertificado::gerar(string $imagemDeFundo, iterable $campos, array $valores): string`, com `$campos: iterable<CampoCertificado>` e `$valores: array<string, string>` indexado pelo `nome` do campo; devolve os bytes de um PNG com as dimensões do fundo.
    - `ImagemInvalidaException` e `TextoGrandeDemaisException` (ambas `RuntimeException`, com mensagem em português própria para mostrar ao usuário).
    - Helpers de teste em `tests/Pest.php`: `fundoBranco(int $largura = 1200, int $altura = 800): string`, `caixaDaTinta(string $png): array{0: int, 1: int, 2: int, 3: int}` (esquerda, topo, direita exclusiva, base exclusiva) e `campoDeTeste(array $atributos = []): CampoCertificado`.

- [ ] **Step 1: Declarar o requisito da extensão**

```bash
composer require ext-imagick --no-interaction
git diff --stat composer.json composer.lock
```

Expected: `composer.json` ganha `"ext-imagick": "*"` em `require`. (O Composer enxerga a versão do `php.new` como `0`, e a restrição `*` aceita.)

- [ ] **Step 2: Criar as exceções**

```bash
php artisan make:exception ImagemInvalidaException --no-interaction
php artisan make:exception TextoGrandeDemaisException --no-interaction
```

`app/Exceptions/ImagemInvalidaException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class ImagemInvalidaException extends RuntimeException {}
```

`app/Exceptions/TextoGrandeDemaisException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class TextoGrandeDemaisException extends RuntimeException {}
```

- [ ] **Step 3: Adicionar os helpers de teste**

Em `tests/Pest.php`, junto dos outros `use` do topo, acrescente:

```php
use App\Models\CampoCertificado;
```

E no fim do arquivo:

```php
/**
 * PNG branco usado como fundo nos testes de geração de certificado.
 */
function fundoBranco(int $largura = 1200, int $altura = 800): string
{
    $imagem = new Imagick;
    $imagem->newImage($largura, $altura, new ImagickPixel('white'));
    $imagem->setImageFormat('png');

    return $imagem->getImageBlob();
}

/**
 * Caixa da tinta (pixels que não são brancos): esquerda, topo, direita e base, com direita e base
 * exclusivas. Só use em imagens que tenham alguma tinta.
 *
 * @return array{0: int, 1: int, 2: int, 3: int}
 */
function caixaDaTinta(string $png): array
{
    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    $imagem->trimImage(0);
    $pagina = $imagem->getImagePage();

    return [
        $pagina['x'],
        $pagina['y'],
        $pagina['x'] + $imagem->getImageWidth(),
        $pagina['y'] + $imagem->getImageHeight(),
    ];
}

/**
 * Campo em memória, sem banco: o factory criaria o modelo pai até com make().
 *
 * @param  array<string, mixed>  $atributos
 */
function campoDeTeste(array $atributos = []): CampoCertificado
{
    return new CampoCertificado(array_merge([
        'nome' => 'Nome',
        'x' => 100,
        'y' => 100,
        'cor' => '#000000',
        'tamanho_fonte' => 40,
        'fonte_familia' => 'Roboto',
        'peso_fonte' => 600,
        'espacamento_letras' => 0,
        'centralizado' => false,
    ], $atributos));
}
```

- [ ] **Step 4: Escrever os testes que falham**

`tests/Feature/Services/GeradorCertificadoTest.php`:

```php
<?php

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\Log;

it('devolve um PNG com as dimensões do fundo', function () {
    $png = (new GeradorCertificado)->gerar(fundoBranco(1000, 700), [campoDeTeste()], ['Nome' => 'Maria']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and($imagem->getImageWidth())->toBe(1000)
        ->and($imagem->getImageHeight())->toBe(700);
});

it('devolve o fundo intacto quando não há texto para desenhar', function (array $valores) {
    $fundo = fundoBranco();

    $png = (new GeradorCertificado)->gerar($fundo, [campoDeTeste()], $valores);

    $original = new Imagick;
    $original->readImageBlob($fundo);
    $gerada = new Imagick;
    $gerada->readImageBlob($png);
    [, $diferenca] = $original->compareImages($gerada, Imagick::METRIC_ROOTMEANSQUAREDERROR);

    expect($diferenca)->toBe(0.0);
})->with([
    'sem valores' => [[]],
    'valor vazio' => [['Nome' => '']],
    'só espaços' => [['Nome' => '   ']],
    'chave que não é campo do modelo' => [['Outro' => 'texto']],
]);

/*
| Valores medidos no Chrome com `line-height: 1`, como o editor posiciona o texto: distância do topo
| do campo (`top: y`) até a linha de base. A letra "H" assenta exatamente nela. Dancing Script fica
| de fora porque o "H" dela tem um floreio que desce da linha de base.
*/
dataset('fontes com a linha de base medida no navegador', [
    'Lora 400 a 48px' => ['Lora', 400, 48, 41.5],
    'Cinzel 600 a 60px' => ['Cinzel', 600, 60, 48.0],
    'Roboto 600 a 32px' => ['Roboto', 600, 32, 26.5],
    'Gelasio 600 a 40px (Georgia)' => ['Georgia', 600, 40, 31.5],
    'Arimo 400 a 24px (Arial)' => ['Arial', 400, 24, 20.0],
]);

it('assenta a linha de base onde o navegador a coloca', function (string $familia, int $peso, int $tamanho, float $linhaDeBase) {
    $campo = campoDeTeste(['fonte_familia' => $familia, 'peso_fonte' => $peso, 'tamanho_fonte' => $tamanho]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'H']);

    [, , , $base] = caixaDaTinta($png);
    expect($base - 100)->toEqualWithDelta($linhaDeBase, 1.5);
})->with('fontes com a linha de base medida no navegador');

it('centraliza o texto na largura da imagem, com ou sem espaçamento', function (float $espacamento) {
    $campo = campoDeTeste(['centralizado' => true, 'espacamento_letras' => $espacamento]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(1200), [$campo], ['Nome' => 'HHH']);

    [$esquerda, , $direita] = caixaDaTinta($png);
    expect(($esquerda + $direita) / 2)->toEqualWithDelta(600, 1.0);
})->with([
    'sem espaçamento' => [0],
    'com espaçamento de 4px' => [4],
]);

it('soma o espaçamento entre letras a cada vão', function () {
    $larguraDaTinta = function (float $espacamento): int {
        $png = (new GeradorCertificado)->gerar(
            fundoBranco(),
            [campoDeTeste(['espacamento_letras' => $espacamento])],
            ['Nome' => 'HHHH'],
        );
        [$esquerda, , $direita] = caixaDaTinta($png);

        return $direita - $esquerda;
    };

    // "HHHH" tem 3 vãos entre letras.
    expect($larguraDaTinta(10) - $larguraDaTinta(0))->toEqualWithDelta(30, 2);
});

it('desenha com a cor exata do campo', function () {
    $campo = campoDeTeste(['cor' => '#7a1f1f', 'tamanho_fonte' => 100]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'H']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    $encontrados = 0;

    foreach ($imagem->getPixelIterator() as $linha) {
        foreach ($linha as $pixel) {
            $cor = $pixel->getColor();
            $encontrados += [$cor['r'], $cor['g'], $cor['b']] === [122, 31, 31] ? 1 : 0;
        }
    }

    expect($encontrados)->toBeGreaterThan(500);
});

it('gira o fundo conforme a orientação EXIF, como o navegador exibe', function () {
    $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $fundo = new Imagick;
    $fundo->newImage(600, 400, new ImagickPixel('white'));
    $fundo->setImageFormat('jpeg');
    $jpeg = $fundo->getImageBlob();
    $comOrientacao = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $png = (new GeradorCertificado)->gerar($comOrientacao, [campoDeTeste()], ['Nome' => 'Maria']);

    $imagem = new Imagick;
    $imagem->readImageBlob($png);
    expect($imagem->getImageWidth())->toBe(400)
        ->and($imagem->getImageHeight())->toBe(600);
});

it('usa o peso mais próximo quando a família não tem o peso do campo', function () {
    Log::spy();
    $campo = campoDeTeste(['fonte_familia' => 'Lato', 'peso_fonte' => 600]);

    $png = (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => 'Maria']);

    Log::shouldHaveReceived('warning')->once();
    expect($png)->not->toBeEmpty();
});

it('recusa um fundo que não é imagem', function () {
    (new GeradorCertificado)->gerar('isto não é uma imagem', [campoDeTeste()], ['Nome' => 'Maria']);
})->throws(ImagemInvalidaException::class, 'Não foi possível ler a imagem do modelo.');

it('recusa o texto grande demais para ser desenhado', function () {
    $campo = campoDeTeste(['tamanho_fonte' => 1000]);

    (new GeradorCertificado)->gerar(fundoBranco(), [$campo], ['Nome' => str_repeat('W', 500)]);
})->throws(TextoGrandeDemaisException::class, 'Nome');
```

- [ ] **Step 5: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Services/GeradorCertificadoTest.php`
Expected: FAIL (`Class "App\Services\GeradorCertificado" not found`).

- [ ] **Step 6: Implementar o serviço**

`app/Services/GeradorCertificado.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Models\CampoCertificado;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;

/**
 * Desenha os campos de um modelo sobre a imagem de fundo e devolve o PNG.
 *
 * O texto é desenhado numa camada transparente ampliada (supersampling) e reduzida depois. Em escala 1×
 * o Imagick arredonda o avanço de cada letra para pixels inteiros; ampliado, a medida fica próxima da do
 * navegador e a posição guarda precisão de sub-pixel.
 *
 * A linha de base segue o editor, que posiciona o texto com `top: y` e `line-height: 1`: o conteúdo
 * (ascendente + descendente) fica centralizado numa caixa de altura igual ao tamanho da fonte.
 *
 * Limitação conhecida: o Imagick só aplica o kerning da tabela `kern`, não o da tabela GPOS que o
 * navegador aplica. Em fontes que dependem de GPOS o texto sai até ~3% mais largo; o centralizado
 * continua centralizado, porque o texto abre dos dois lados.
 */
class GeradorCertificado
{
    private const ESCALA = 8;

    private const LADO_MAXIMO_DA_CAMADA = 16384;

    private const AREA_MAXIMA_DA_CAMADA = 16_000_000;

    private const MEMORIA_MAXIMA = 1024 * 1024 * 1024;

    /**
     * @param  iterable<CampoCertificado>  $campos
     * @param  array<string, string>  $valores  texto de cada campo, indexado pelo nome do campo
     * @return string bytes do PNG, com as dimensões do fundo
     *
     * @throws ImagemInvalidaException
     * @throws TextoGrandeDemaisException
     */
    public function gerar(string $imagemDeFundo, iterable $campos, array $valores): string
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MEMORIA_MAXIMA);

        $imagem = $this->abrir($imagemDeFundo);

        try {
            foreach ($campos as $campo) {
                $texto = trim((string) ($valores[$campo->nome] ?? ''));

                if ($texto !== '') {
                    $this->escrever($imagem, $campo, $texto);
                }
            }

            $imagem->setImageFormat('png');

            return $imagem->getImageBlob();
        } finally {
            $imagem->clear();
        }
    }

    /**
     * @throws ImagemInvalidaException
     */
    private function abrir(string $bytes): Imagick
    {
        try {
            $imagem = new Imagick;
            $imagem->readImageBlob($bytes);
            $imagem->setFirstIterator();
            // O navegador exibe a foto já girada; o editor posiciona os campos nessa orientação.
            $imagem->autoOrient();
            $imagem->setImagePage(0, 0, 0, 0);
            $imagem->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        } catch (ImagickException $e) {
            throw new ImagemInvalidaException('Não foi possível ler a imagem do modelo.', previous: $e);
        }

        return $imagem;
    }

    /**
     * @throws TextoGrandeDemaisException
     */
    private function escrever(Imagick $imagem, CampoCertificado $campo, string $texto): void
    {
        $arquivo = CatalogoFontes::arquivo($campo->fonte_familia, $campo->peso_fonte);
        $tamanho = $campo->tamanho_fonte;
        $espacamento = $campo->espacamento_letras;

        // Folga para floreios e ascendentes que passam da caixa de linha.
        $margemX = (int) ceil($tamanho * 0.5);
        $margemY = (int) ceil($tamanho * 0.75);

        for ($escala = self::ESCALA; $escala >= 1; $escala--) {
            $medidas = $this->medir($arquivo, $tamanho, $espacamento, $texto, $escala);
            $larguraDaCamada = (int) ceil($medidas['largura']) + 2 * $margemX + 1;
            $alturaDaCamada = (int) ceil($tamanho) + 2 * $margemY + 1;

            if (max($larguraDaCamada, $alturaDaCamada) * $escala <= self::LADO_MAXIMO_DA_CAMADA
                && $larguraDaCamada * $alturaDaCamada * $escala ** 2 <= self::AREA_MAXIMA_DA_CAMADA) {
                break;
            }
        }

        if ($escala < 1) {
            throw new TextoGrandeDemaisException("O texto do campo \"{$campo->nome}\" é grande demais para ser desenhado.");
        }

        $x = $campo->centralizado ? ($imagem->getImageWidth() - $medidas['largura']) / 2 : $campo->x;
        $y = $campo->y;

        $pincel = $this->pincel($arquivo, $tamanho, $espacamento, $escala);
        $pincel->setFillColor(new ImagickPixel($campo->cor));

        $camada = new Imagick;
        $camada->newImage($larguraDaCamada * $escala, $alturaDaCamada * $escala, new ImagickPixel('none'));
        $camada->annotateImage(
            $pincel,
            ($margemX + $x - floor($x)) * $escala,
            ($margemY + $y - floor($y) + $medidas['linhaDeBase']) * $escala,
            0,
            $texto,
        );

        if ($escala > 1) {
            $camada->resizeImage($larguraDaCamada, $alturaDaCamada, Imagick::FILTER_BOX, 1);
        }

        $imagem->compositeImage($camada, Imagick::COMPOSITE_OVER, (int) floor($x) - $margemX, (int) floor($y) - $margemY);
        $camada->clear();
    }

    /**
     * @return array{largura: float, linhaDeBase: float}
     */
    private function medir(string $arquivo, int $tamanho, float $espacamento, string $texto, int $escala): array
    {
        $sonda = new Imagick;
        $sonda->newImage(1, 1, new ImagickPixel('none'));
        $metricas = $sonda->queryFontMetrics($this->pincel($arquivo, $tamanho, $espacamento, $escala), $texto);
        $sonda->clear();

        return [
            'largura' => $metricas['textWidth'] / $escala,
            // O descendente vem negativo.
            'linhaDeBase' => ($tamanho + ($metricas['ascender'] + $metricas['descender']) / $escala) / 2,
        ];
    }

    private function pincel(string $arquivo, int $tamanho, float $espacamento, int $escala): ImagickDraw
    {
        $pincel = new ImagickDraw;
        $pincel->setFont($arquivo);
        $pincel->setFontSize($tamanho * $escala);
        $pincel->setTextKerning($espacamento * $escala);
        $pincel->setTextAntialias(true);

        return $pincel;
    }
}
```

- [ ] **Step 7: Rodar e ver passar**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Services/GeradorCertificadoTest.php
```

Expected: PASS (todos). Se o teste da linha de base falhar em uma fonte por pouco (> 1,5 px), não aumente a tolerância: confira se o arquivo de fonte do peso pedido foi baixado (Task 2) e se `CatalogoFontes::arquivo` não caiu em outro peso (procure o aviso no log).

- [ ] **Step 8: Escrever o teste de regressão visual**

`tests/Feature/Services/GeradorCertificadoVisualTest.php`:

```php
<?php

use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\File;

/*
| Imagens de referência em tests/Fixtures/certificados. Para recriá-las depois de uma mudança
| proposital no desenho, rode `UPDATE_GOLDEN=1 php artisan test --filter=GeradorCertificadoVisualTest`
| e confira as imagens antes de commitar.
*/
dataset('certificados de referência', [
    'a: Lora 400 à esquerda' => ['a', [
        'fonte_familia' => 'Lora', 'peso_fonte' => 400, 'tamanho_fonte' => 48,
        'cor' => '#1a1a1a', 'x' => 100, 'y' => 100,
    ], 'Maria da Silva Pereira'],
    'b: Cinzel 600 centralizado com espaçamento' => ['b', [
        'fonte_familia' => 'Cinzel', 'peso_fonte' => 600, 'tamanho_fonte' => 60,
        'cor' => '#7a1f1f', 'espacamento_letras' => 2, 'centralizado' => true, 'y' => 200,
    ], 'CERTIDÃO DE BATISMO'],
    'c: Dancing Script 700' => ['c', [
        'fonte_familia' => 'Dancing Script', 'peso_fonte' => 700, 'tamanho_fonte' => 72,
        'x' => 300, 'y' => 400,
    ], 'João Pedro'],
    'd: Roboto 600 centralizado' => ['d', [
        'fonte_familia' => 'Roboto', 'peso_fonte' => 600, 'tamanho_fonte' => 32,
        'cor' => '#223344', 'espacamento_letras' => 1.5, 'centralizado' => true, 'y' => 520,
    ], 'Texto com acentuação: ção ñ'],
    'e: Georgia (Gelasio) 600' => ['e', [
        'fonte_familia' => 'Georgia', 'peso_fonte' => 600, 'tamanho_fonte' => 40,
        'x' => 50, 'y' => 650,
    ], 'Gjpqy Typography'],
    'f: Arial (Arimo) 400 com espaçamento negativo' => ['f', [
        'fonte_familia' => 'Arial', 'peso_fonte' => 400, 'tamanho_fonte' => 24,
        'espacamento_letras' => -0.5, 'x' => 700, 'y' => 650,
    ], 'Espaçamento negativo'],
]);

it('reproduz a imagem de referência', function (string $id, array $campo, string $texto) {
    $png = (new GeradorCertificado)->gerar(fundoBranco(), [campoDeTeste($campo)], ['Nome' => $texto]);
    $caminho = base_path("tests/Fixtures/certificados/{$id}.png");

    if (getenv('UPDATE_GOLDEN')) {
        File::ensureDirectoryExists(dirname($caminho));
        File::put($caminho, $png);
        $this->markTestSkipped('Imagem de referência atualizada; confira-a antes de commitar.');
    }

    $this->assertFileExists($caminho, 'Falta a imagem de referência. Rode com UPDATE_GOLDEN=1 e confira o resultado.');

    $referencia = new Imagick($caminho);
    $gerada = new Imagick;
    $gerada->readImageBlob($png);
    [, $diferenca] = $referencia->compareImages($gerada, Imagick::METRIC_ROOTMEANSQUAREDERROR);

    expect($diferenca)->toBeLessThan(0.01);
})->with('certificados de referência');
```

- [ ] **Step 9: Gerar e conferir as imagens de referência**

```bash
UPDATE_GOLDEN=1 php artisan test --compact tests/Feature/Services/GeradorCertificadoVisualTest.php
ls -la tests/Fixtures/certificados/
```

Expected: 6 testes pulados e 6 arquivos `a.png` … `f.png` (poucos KB cada). **Abra cada imagem** e confira: texto legível, na posição certa (esquerda em x=100/300/50/700; centralizados no meio), sem cortes no topo, na base ou nas laterais, acentos e floreios completos (em `c.png`, o "J" e o "P" de Dancing Script inteiros). Só siga se as seis estiverem corretas.

- [ ] **Step 10: Rodar o teste visual e a suíte**

```bash
php artisan test --compact tests/Feature/Services/GeradorCertificadoVisualTest.php
php artisan test --compact
```

Expected: PASS (6 testes visuais) e suíte inteira verde.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "feat: GeradorCertificado com Imagick e supersampling

Linha de base como o line-height: 1 do editor (medida no Chrome, até 0,44 px de diferença).
Limitação conhecida: sem o kerning GPOS do navegador, o texto sai até ~3% mais largo em
algumas fontes.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Endpoint de geração

**Files:**

- Create: `config/certificados.php`, `app/Http/Requests/GerarCertificadoRequest.php`, `app/Http/Controllers/GeracaoCertificadoController.php`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/web.php`
- Test: `tests/Feature/Certificados/GeracaoCertificadoControllerTest.php`

**Interfaces:**

- Consumes: `GeradorCertificado::gerar(string, iterable<CampoCertificado>, array<string, string>): string`, `ImagemInvalidaException`, `TextoGrandeDemaisException` (Task 3); Policy `gerar` do Plano 1 (404 para quem não vê o modelo, 403 para quem vê mas não pode); `User::geracoes()`, `ModeloCertificado::campos()`; helpers `fundoBranco()` e `caixaDaTinta()` de `tests/Pest.php` (Task 3).
- Produces:
    - Rota `certificados.store` (`POST /modelos/{modelo}/certificados`), no grupo `auth` + `verified`, com `throttle:gerar-certificado`. Corpo: `{ "valores": { "<nome do campo>": "texto" } }`.
    - Respostas: `200` com `Content-Type: image/png` e `Content-Disposition: attachment`; `422` JSON `{ "message": "..." }` (imagem ilegível ou texto grande demais) ou JSON de validação; `429` no limite; `404`/`403` pela Policy.
    - `config('certificados.geracoes_por_minuto')` (padrão 60).

- [ ] **Step 1: Configurar o limite e o rate limiter**

`config/certificados.php`:

```php
<?php

return [
    /*
    | Gerações de certificado por usuário, por minuto. O lote do Excel faz uma requisição por linha,
    | então o cliente precisa esperar e tentar de novo ao receber 429.
    */
    'geracoes_por_minuto' => (int) env('CERTIFICADOS_GERACOES_POR_MINUTO', 60),
];
```

Em `app/Providers/AppServiceProvider.php`, acrescente os imports ao bloco de `use` (mantendo os que já existem):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
```

Troque o corpo de `boot()` por:

```php
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }
```

E acrescente o método, depois de `configureDefaults()`:

```php
    /**
     * Limita a geração de certificados por usuário.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('gerar-certificado', fn (Request $request) => Limit::perMinute(config('certificados.geracoes_por_minuto'))
            ->by($request->user()?->id ?: $request->ip()));
    }
```

- [ ] **Step 2: Escrever os testes que falham**

`tests/Feature/Certificados/GeracaoCertificadoControllerTest.php`:

```php
<?php

use App\Models\CampoCertificado;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use App\Models\User;
use App\Services\GeradorCertificado;
use Illuminate\Support\Facades\Storage;

/**
 * Cria um modelo com fundo branco no bucket falso e um campo "Nome". Chame Storage::fake('s3') antes.
 */
function modeloGeravel(?User $dono = null, string $titulo = 'Batismo'): ModeloCertificado
{
    $modelo = ModeloCertificado::factory()->for($dono ?? User::factory()->create())->create(['title' => $titulo]);
    Storage::disk('s3')->put($modelo->image_path, fundoBranco());
    CampoCertificado::factory()->for($modelo, 'modelo')->create([
        'nome' => 'Nome',
        'fonte_familia' => 'Roboto',
        'peso_fonte' => 600,
        'tamanho_fonte' => 40,
        'x' => 100,
        'y' => 100,
    ]);

    return $modelo;
}

it('gera o PNG do modelo e registra a geração no histórico', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria', 'Outro' => 'ignorado']]);

    $resposta->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($resposta->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    [, , $direita] = caixaDaTinta($resposta->getContent());
    expect($direita)->toBeGreaterThan(100);
    $geracao = $modelo->geracoes()->sole();
    expect($geracao)
        ->user_id->toBe($modelo->user_id)
        ->modelo_title->toBe('Batismo')
        ->and($geracao->campos_valores)->toBe(['Nome' => 'Maria']);
});

it('nomeia o download com o título do modelo e o primeiro valor', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: 'Batismo ção');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    expect($resposta->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('filename="Batismo cao - Maria.png"')
        ->toContain("filename*=utf-8''Batismo%20%C3%A7%C3%A3o%20-%20Maria.png");
});

it('tira do nome do arquivo os caracteres que não podem aparecer nele', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel(titulo: 'Crisma "2024"/Turma');

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    expect($resposta->headers->get('Content-Disposition'))->toContain('filename="Crisma 2024Turma - Maria.png"');
});

it('guarda como texto os valores que chegam como número', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 2024]])
        ->assertOk();

    expect($modelo->geracoes()->sole()->campos_valores)->toBe(['Nome' => '2024']);
});

it('deixa o leitor compartilhado gerar o certificado', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    $leitor = User::factory()->create(['email' => 'leitor@example.com']);
    CompartilhamentoModelo::factory()->for($modelo, 'modelo')->create(['email' => 'leitor@example.com']);

    $this->actingAs($leitor)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertOk();

    expect($modelo->geracoes()->sole()->user_id)->toBe($leitor->id);
});

it('responde 404 a quem não pode ver o modelo e não registra a geração', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs(User::factory()->create())
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertNotFound();

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('redireciona o visitante para o login', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->post(route('certificados.store', $modelo), ['valores' => []])->assertRedirect(route('login'));
});

it('recusa valores inválidos e não gera nem registra', function (array $dados, string $mensagem) {
    Storage::fake('s3');
    $modelo = modeloGeravel();

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), $dados)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['valores' => $mensagem]);

    $this->assertDatabaseCount('geracoes_certificado', 0);
})->with([
    'sem valores' => [[], 'Informe os valores dos campos.'],
    'valores que não é lista' => [['valores' => 'texto'], 'Os valores dos campos devem ser uma lista.'],
    'valor com mais de 500 caracteres' => [['valores' => ['Nome' => str_repeat('a', 501)]], 'Cada valor pode ter no máximo 500 caracteres.'],
    'valor que não é texto' => [['valores' => ['Nome' => ['a']]], 'Cada valor deve ser um texto.'],
]);

it('responde 422 quando a imagem do modelo não está no bucket', function () {
    Storage::fake('s3');
    $modelo = ModeloCertificado::factory()->create();
    CampoCertificado::factory()->for($modelo, 'modelo')->create(['nome' => 'Nome']);

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Não foi possível ler a imagem do modelo.']);

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('responde 422 quando o texto é grande demais para ser desenhado', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    $modelo->campos()->update(['tamanho_fonte' => 1000]);

    $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => str_repeat('W', 500)]])
        ->assertUnprocessable()
        ->assertJson(['message' => 'O texto do campo "Nome" é grande demais para ser desenhado.']);

    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('não expõe a mensagem da exceção quando a geração falha', function () {
    Storage::fake('s3');
    $modelo = modeloGeravel();
    config(['app.debug' => false]);
    $this->mock(GeradorCertificado::class, fn ($mock) => $mock
        ->shouldReceive('gerar')
        ->andThrow(new RuntimeException('segredo interno')));

    $resposta = $this->actingAs($modelo->user)
        ->postJson(route('certificados.store', $modelo), ['valores' => ['Nome' => 'Maria']]);

    $resposta->assertInternalServerError();
    expect($resposta->getContent())->not->toContain('segredo interno');
    $this->assertDatabaseCount('geracoes_certificado', 0);
});

it('limita a geração por minuto a cada usuário', function () {
    Storage::fake('s3');
    config(['certificados.geracoes_por_minuto' => 2]);
    $modelo = modeloGeravel();
    $dados = ['valores' => ['Nome' => 'Maria']];

    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertOk();
    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertOk();
    $this->actingAs($modelo->user)->postJson(route('certificados.store', $modelo), $dados)->assertTooManyRequests();

    $outro = modeloGeravel();
    $this->actingAs($outro->user)->postJson(route('certificados.store', $outro), $dados)->assertOk();
});
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Certificados/GeracaoCertificadoControllerTest.php`
Expected: FAIL (`Route [certificados.store] not defined`).

- [ ] **Step 4: Criar o Form Request**

```bash
php artisan make:request GerarCertificadoRequest --no-interaction
```

Substitua `app/Http/Requests/GerarCertificadoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GerarCertificadoRequest extends FormRequest
{
    private const LIMITE_DE_CARACTERES = 500;

    /**
     * A autorização vem antes da validação: quem não vê o modelo recebe 404 mesmo com dados inválidos.
     */
    public function authorize(): Response
    {
        return Gate::inspect('gerar', $this->route('modelo'));
    }

    /**
     * Os valores vêm indexados pelo nome do campo, que pode ter pontos. Por isso a regra `valores.*`
     * não serve (ela perde as chaves com ponto) e cada valor é conferido aqui.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'valores' => [
                'bail', 'present', 'array',
                function (string $atributo, mixed $valores, Closure $falhar): void {
                    foreach ($valores as $valor) {
                        if ($valor !== null && ! is_string($valor) && ! is_numeric($valor)) {
                            $falhar('Cada valor deve ser um texto.');

                            return;
                        }

                        if (mb_strlen((string) $valor) > self::LIMITE_DE_CARACTERES) {
                            $falhar('Cada valor pode ter no máximo 500 caracteres.');

                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valores.present' => 'Informe os valores dos campos.',
            'valores.array' => 'Os valores dos campos devem ser uma lista.',
        ];
    }
}
```

- [ ] **Step 5: Criar o controller e a rota**

```bash
php artisan make:controller GeracaoCertificadoController --no-interaction
```

Substitua `app/Http/Controllers/GeracaoCertificadoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Exceptions\ImagemInvalidaException;
use App\Exceptions\TextoGrandeDemaisException;
use App\Http\Requests\GerarCertificadoRequest;
use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use App\Services\GeradorCertificado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class GeracaoCertificadoController extends Controller
{
    /**
     * Gera o PNG do certificado e registra a geração no histórico.
     */
    public function store(GerarCertificadoRequest $request, ModeloCertificado $modelo, GeradorCertificado $gerador): Response|JsonResponse
    {
        $campos = $modelo->campos;
        $valores = collect($request->validated('valores'))
            ->only($campos->pluck('nome')->all())
            ->map(fn (mixed $valor) => (string) $valor)
            ->all();

        try {
            $png = $gerador->gerar($this->lerFundo($modelo), $campos, $valores);
        } catch (ImagemInvalidaException|TextoGrandeDemaisException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $request->user()->geracoes()->create([
            'modelo_id' => $modelo->id,
            'modelo_title' => $modelo->title,
            'campos_valores' => $valores,
        ]);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => $this->cabecalhoDeDownload($modelo, $campos, $valores),
        ]);
    }

    /**
     * @throws ImagemInvalidaException
     */
    private function lerFundo(ModeloCertificado $modelo): string
    {
        $disco = Storage::disk('s3');

        if (! $disco->exists($modelo->image_path)) {
            throw new ImagemInvalidaException('Não foi possível ler a imagem do modelo.');
        }

        return $disco->get($modelo->image_path);
    }

    /**
     * "{título} - {primeiro valor}.png", sem caracteres de controle nem de caminho, com o nome em UTF-8
     * e uma alternativa ASCII para quem não lê `filename*`.
     *
     * @param  Collection<int, CampoCertificado>  $campos
     * @param  array<string, string>  $valores
     */
    private function cabecalhoDeDownload(ModeloCertificado $modelo, Collection $campos, array $valores): string
    {
        $primeiroValor = $campos
            ->map(fn (CampoCertificado $campo) => $valores[$campo->nome] ?? '')
            ->first(fn (string $valor) => $valor !== '');

        $nome = $this->nomeSeguro($primeiroValor === null ? $modelo->title : "{$modelo->title} - {$primeiroValor}").'.png';
        $alternativa = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', Str::ascii($nome));

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nome, $alternativa);
    }

    private function nomeSeguro(string $nome): string
    {
        $semProibidos = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '', $nome) ?? '';

        return Str::limit(trim($semProibidos), 150, '');
    }
}
```

Em `routes/web.php`, adicione o import `use App\Http\Controllers\GeracaoCertificadoController;` e, **dentro do grupo `auth` + `verified`**, antes da rota `modelos.imagem`:

```php
    Route::post('modelos/{modelo}/certificados', [GeracaoCertificadoController::class, 'store'])
        ->middleware('throttle:gerar-certificado')
        ->name('certificados.store');
```

- [ ] **Step 6: Rodar e ver passar**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Certificados/GeracaoCertificadoControllerTest.php
```

Expected: PASS. Se o teste do nome do arquivo falhar só pelo formato exato do cabeçalho, imprima o valor real (`dump($resposta->headers->get('Content-Disposition'))`), ajuste a asserção para o formato que o Symfony gera e mantenha as duas garantias: nome ASCII de reserva e `filename*` em UTF-8.

- [ ] **Step 7: Suíte, build e tipos (o Wayfinder gera a rota nova no build)**

```bash
php artisan test --compact
npm run build && npx tsc --noEmit
```

Expected: tudo verde.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: endpoint de geração de certificado com histórico e limite por minuto

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Servir as fontes ao editor

**Files:**

- Create: `app/Http/Controllers/FonteController.php`, `tests/Feature/Fontes/FonteControllerTest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/CampoCertificadoController.php`, `tests/Feature/Campos/CampoCertificadoControllerTest.php`, `resources/js/pages/meus-certificados/campos.tsx`

**Interfaces:**

- Consumes: `CatalogoFontes::pastas()`, `CatalogoFontes::pesosDisponiveis()`, `CatalogoFontes::pesosPorPasta()` (Task 1); arquivos de fonte (Task 2).
- Produces:
    - Rota pública `fontes.show` (`GET /fontes/{pasta}/{peso}.ttf`): o arquivo da fonte, com `Content-Type: font/ttf` e cache de um ano. O editor (Plano 3) a usa em `@font-face`, para o preview e o PNG usarem exatamente os mesmos arquivos.
    - Prop `pesos_das_fontes: Record<string, number[]>` (pasta => pesos que existem) na página `meus-certificados/campos`, para o editor declarar só os `@font-face` que têm arquivo.

- [ ] **Step 1: Escrever os testes que falham**

`tests/Feature/Fontes/FonteControllerTest.php`:

```php
<?php

it('serve o arquivo da fonte sem exigir login, com cache longo', function () {
    $resposta = $this->get(route('fontes.show', ['pasta' => 'roboto', 'peso' => 600]));

    $resposta->assertOk()->assertHeader('Content-Type', 'font/ttf');
    expect($resposta->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
    $this->assertFileEquals(resource_path('fonts/roboto/600.ttf'), $resposta->baseResponse->getFile()->getPathname());
});

it('responde 404 para fonte fora do catálogo, peso inexistente ou caminho forjado', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'pasta fora do catálogo' => ['/fontes/inexistente/400.ttf'],
    'peso que a família não tem' => ['/fontes/lato/600.ttf'],
    'peso fora da escala' => ['/fontes/roboto/650.ttf'],
    'subida de diretório' => ['/fontes/%2e%2e/400.ttf'],
    'barra codificada' => ['/fontes/roboto%2F..%2Froboto/600.ttf'],
    'extensão errada' => ['/fontes/roboto/600.woff'],
]);
```

Em `tests/Feature/Campos/CampoCertificadoControllerTest.php`, acrescente no fim:

```php
it('informa os pesos disponíveis de cada fonte, para o editor carregar só o que existe', function () {
    $modelo = ModeloCertificado::factory()->create();

    $this->actingAs($modelo->user)->get(route('meus-certificados.campos', $modelo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pesos_das_fontes.lato', [100, 300, 400, 700, 900])
            ->where('pesos_das_fontes.roboto', [100, 200, 300, 400, 500, 600, 700, 800, 900]));
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Fontes/FonteControllerTest.php tests/Feature/Campos/CampoCertificadoControllerTest.php`
Expected: FAIL (rota `fontes.show` inexistente e prop `pesos_das_fontes` ausente).

- [ ] **Step 3: Implementar o controller e a rota**

```bash
php artisan make:controller FonteController --no-interaction
```

Substitua `app/Http/Controllers/FonteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\CatalogoFontes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FonteController extends Controller
{
    /**
     * Serve o TTF de uma fonte do catálogo. Só chegam aqui pastas e pesos que existem no catálogo.
     */
    public function show(string $pasta, int $peso): BinaryFileResponse
    {
        abort_unless(in_array($pasta, CatalogoFontes::pastas(), true), 404);
        abort_unless(in_array($peso, CatalogoFontes::pesosDisponiveis($pasta), true), 404);

        return response()->file(config('fontes.diretorio')."/{$pasta}/{$peso}.ttf", [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
```

Em `routes/web.php`, adicione o import `use App\Http\Controllers\FonteController;` e, **fora** do grupo autenticado (logo depois da rota `home`):

```php
Route::get('fontes/{pasta}/{peso}.ttf', [FonteController::class, 'show'])
    ->where('pasta', '[a-z0-9-]+')
    ->where('peso', '[1-9]00')
    ->name('fontes.show');
```

- [ ] **Step 4: Expor os pesos na página de campos**

Em `app/Http/Controllers/CampoCertificadoController.php`, no array de `Inertia::render('meus-certificados/campos', [...])` do método `edit`, acrescente depois de `'fontes' => CatalogoFontes::grupos(),`:

```php
            'pesos_das_fontes' => CatalogoFontes::pesosPorPasta(),
```

Em `resources/js/pages/meus-certificados/campos.tsx`, no tipo `Props`, acrescente depois de `fontes: GruposFontes;`:

```ts
/** Pasta => pesos que têm arquivo, para declarar só os @font-face que existem. */
pesos_das_fontes: Record<string, number[]>;
```

- [ ] **Step 5: Rodar e ver passar; suíte, build e tipos**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Fontes tests/Feature/Campos
php artisan test --compact
npm run build && npx tsc --noEmit && npm run check
```

Expected: tudo verde. Se o `Content-Type` vier com outro valor, confira se o servidor de teste respeita o cabeçalho passado a `response()->file`; o `Cache-Control` pode vir com as diretivas em outra ordem, por isso o teste usa `toContain`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: serve as fontes do catálogo ao editor e informa os pesos disponíveis

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Corrigir o spec e verificar de ponta a ponta

**Files:**

- Modify: `docs/superpowers/specs/2026-10-06-migracao-laravel-design.md` (seções 7, 9, 10 e 11)

**Interfaces:**

- Consumes: tudo das Tasks 1 a 5.
- Produces: spec alinhado ao que foi construído (fórmula de linha de base, supersampling, limitação do kerning, equivalentes livres Arimo/Tinos/Cousine, rota das fontes) e o Plano 2 verificado contra o servidor real.

- [ ] **Step 1: Reescrever a seção 7 e ajustar as seções 9, 10 e 11 do spec**

```bash
python3 - <<'EOF'
p = 'docs/superpowers/specs/2026-10-06-migracao-laravel-design.md'
linhas = open(p).read().split('\n')

inicio = next(i for i, l in enumerate(linhas) if l.startswith('## 7.'))
fim = next(i for i, l in enumerate(linhas) if l.startswith('## 8.'))

secao7 = '''## 7. Geração do certificado

### 7.1 Catálogo de fontes

`config/fontes.php` é a fonte única de verdade: `diretorio` (padrão `resources/fonts`), `grupos` (família exibida => pasta dos arquivos) e `google` (família exibida => nome no Google Fonts, só para as de sistema). O React recebe `fontes` (grupos) e `pesos_das_fontes` (pasta => pesos que têm arquivo) como props da página de campos.

| Grupo | Família exibida | Arquivo usado |
| --- | --- | --- |
| Sistema | Georgia | Gelasio |
| Sistema | Arial | Arimo |
| Sistema | Times New Roman | Tinos |
| Sistema | Courier New | Cousine |
| Serif | Cormorant Garamond, EB Garamond, Lora, Merriweather, Playfair Display, Libre Baskerville | Google Fonts |
| Sem serifa | Roboto, Montserrat, Open Sans, Lato, Raleway, Quicksand | Google Fonts |
| Display | Cinzel, Crimson Text, Dancing Script | Google Fonts |

Regras do catálogo:

- As fontes de "Sistema" usam equivalentes livres com as mesmas medidas (Arimo, Tinos, Cousine e Gelasio), porque as originais da Microsoft não existem no servidor.
- `php artisan fontes:baixar` pede ao CSS do Google Fonts, **sem User-Agent de navegador**, um TTF **estático** por peso de 100 a 900. Com um navegador o Google serviria fontes variáveis em WOFF2, e o ImageMagick não escolhe eixos de fonte variável. Peso que a família não tem responde HTTP 400 e é ignorado.
- O resultado fica em `resources/fonts/{pasta}/{peso}.ttf`: 99 arquivos, 18 MB, versionados. A geração e o build nunca acessam a rede. Os arquivos trazem latim estendido (português incluso), sem cirílico nem grego. `resources/fonts/LICENCAS.md` registra a origem e as licenças (OFL 1.1 ou Apache 2.0).
- Peso ausente: usa o peso mais próximo da mesma família (empate vai para o mais pesado) e registra um aviso no log. Família fora do catálogo ou sem nenhum arquivo é erro interno (500).
- O editor carrega os mesmos arquivos por `@font-face` a partir de `GET /fontes/{pasta}/{peso}.ttf`: rota pública, `Cache-Control: public, max-age=31536000, immutable`, que só serve pastas e pesos do catálogo.

### 7.2 Endpoint

`POST /modelos/{modelo}/certificados`, com corpo `{ "valores": { "<nome do campo>": "texto" } }`.

1. Policy `gerar`: 404 para quem não pode ver o modelo, 403 para quem vê e não pode gerar.
2. Validação: `valores` presente e do tipo lista; cada valor é texto ou número, com até 500 caracteres. A regra é própria (não `valores.*`), porque o nome do campo pode ter pontos. Chaves que não são `nome` de um campo do modelo são descartadas.
3. Lê o fundo do bucket. Ausente ou ilegível: 422 "Não foi possível ler a imagem do modelo."
4. `GeradorCertificado` devolve o PNG. Texto grande demais para a camada de desenho: 422 "O texto do campo "X" é grande demais para ser desenhado."
5. Grava uma linha em `geracoes_certificado`, só com os valores dos campos.
6. Responde 200 `image/png` com `Content-Disposition: attachment`, nome `{título} - {primeiro valor}.png`: sem caracteres de controle nem de caminho, com `filename*` em UTF-8 e alternativa ASCII.
7. Limite de `certificados.geracoes_por_minuto` (padrão 60) por usuário; excedido, 429. O lote do Excel faz uma requisição por linha, então o cliente espera e tenta de novo.

### 7.3 Algoritmo (`GeradorCertificado`)

O Imagick arredonda o avanço de cada letra para pixels inteiros em escala 1×. Por isso o texto é medido e desenhado ampliado 8× (supersampling) e reduzido depois.

1. Limite de memória do Imagick em 1 GiB (acima disso ele usa disco).
2. Lê o fundo, usa o primeiro quadro, aplica a orientação EXIF (o navegador exibe a foto já girada e o editor posiciona os campos nessa orientação) e converte para sRGB.
3. Para cada campo, na ordem de criação, com valor não vazio após `trim`:
   1. Mede o texto em escala 8× (`queryFontMetrics`) e divide por 8. A **linha de base** fica a `(tamanho + ascendente + descendente) / 2` do topo do campo, com o descendente negativo. É a geometria do CSS `line-height: 1` que o editor usa (`top: y`).
   2. Calcula a camada transparente (largura do texto mais folga para floreios) e reduz a escala de 8× até 1× se ela passar de 16384 px de lado ou de 16.000.000 px de área. Se nem em 1× couber, 422.
   3. `x = centralizado ? (largura da imagem - largura do texto) / 2 : x do campo`; a parte fracionária de `x` e `y` entra no desenho, para manter a precisão de sub-pixel.
   4. Desenha o texto na camada (fonte do peso mais próximo, cor, `setTextKerning` com o espaçamento), reduz com filtro de caixa e compõe sobre o fundo.
4. Devolve o PNG, com as dimensões do fundo.

### 7.4 Fidelidade ao preview do editor

Medida contra o Chrome com as mesmas fontes e `line-height: 1`:

- **Vertical:** a linha de base fica a no máximo 0,44 px da do navegador. O gerador antigo (canvas) errava de 2 a 3 px em algumas fontes.
- **Horizontal:** o Imagick só aplica o kerning da tabela `kern`, não o da tabela GPOS que o navegador aplica. O texto sai 0,2 a 0,4% mais largo na maioria das fontes, 1,5% mais largo em Cinzel e 3,1% em Dancing Script. O texto centralizado continua centralizado, porque abre simetricamente. Não há caminho para igualar sem um motor de layout com HarfBuzz; `pango:` não existe no Imagick do `php.new`.
- **Desempenho:** um fundo de 35 MB (3500×3500) com 3 campos leva cerca de 1,6 s e pico de 644 MB de memória.
'''.split('\n')

linhas[inicio:fim] = secao7

def depois_da_linha_que_contem(trecho, nova):
    i = next(k for k, l in enumerate(linhas) if trecho in l)
    linhas.insert(i + 1, nova)

depois_da_linha_que_contem('| Imagem de fundo ausente', '| Texto grande demais para a camada de desenho | 422 com "O texto do campo "X" é grande demais para ser desenhado." |')

i = next(k for k, l in enumerate(linhas) if l.startswith('- **Geração (visual):**'))
linhas[i] = ('- **Geração (visual):** `GeradorCertificado` com um fundo branco gerado no teste, comparado com PNGs de referência em '
             '`tests/Fixtures/certificados/` por `Imagick::compareImages` (RMSE abaixo de 0,01); `UPDATE_GOLDEN=1` recria as referências. '
             'Além delas: linha de base contra os valores medidos no Chrome (tolerância de 1,5 px), centramento, espaçamento, cor exata, '
             'orientação EXIF e peso de reserva.')

i = next(k for k, l in enumerate(linhas) if 'coincide com o preview do editor' in l)
linhas[i] = ('3. O PNG gerado coincide com o preview do editor dentro da tolerância documentada na seção 7.4 '
             '(linha de base a até 1,5 px; largura até ~3% maior nas fontes que dependem de kerning GPOS).')

open(p, 'w').write('\n'.join(linhas))
EOF
npx vp fmt docs/superpowers/specs/2026-10-06-migracao-laravel-design.md
git diff --stat docs/superpowers/specs | tail -1
```

Expected: o spec ganha a seção 7 nova, a linha de erro, a linha de teste e o critério de pronto atualizados. Confira com `grep -n "Liberation\|y + ascender\|MinIO" docs/superpowers/specs/*.md`: não deve sobrar `Liberation` nem `y + ascender` (as menções ao MinIO que restarem são as notas históricas).

- [ ] **Step 2: Verificação automática completa**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npx tsc --noEmit
npm run check
```

Expected: suíte inteira verde, `tsc` sem erros e `check` limpo.

- [ ] **Step 3: Verificação ponta a ponta com o servidor real**

Os testes usam `Storage::fake`, então nunca exercitaram o RustFS nem o PHP real com uma imagem grande.

Suba o ambiente e prepare uma imagem de teste de ~35 MB:

```bash
docker compose up -d
composer run dev          # em segundo plano: servidor, fila e Vite
python3 - <<'EOF'
import os, struct, zlib
w = h = 3500
def bloco(t, d):
    c = struct.pack('>I', len(d)) + t + d
    return c + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
cru = b''.join(b'\x00' + os.urandom(w * 3) for _ in range(h))
png = b'\x89PNG\r\n\x1a\n' + bloco(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + bloco(b'IDAT', zlib.compress(cru, 0)) + bloco(b'IEND', b'')
open('/tmp/fundo-grande.png', 'wb').write(png)
print(round(len(png) / 1048576, 1), 'MB')
EOF
php artisan tinker --execute 'App\Models\User::factory()->create(["name" => "Dev E2E", "email" => "e2e@example.com"]); echo "ok";'
```

Faça login, crie o modelo com a imagem, crie dois campos e gere o certificado (cada comando num shell que tenha o `export PATH` do início):

```bash
J=/tmp/cookies.txt; B=http://127.0.0.1:8000; rm -f $J
xsrf() { python3 -c "import urllib.parse;print([urllib.parse.unquote(l.split('\t')[6].strip()) for l in open('$J') if 'XSRF-TOKEN' in l][0])"; }
curl -s -c $J -b $J -o /dev/null $B/login
curl -s -c $J -b $J -o /dev/null -w "login: %{http_code}\n" -X POST $B/login -H "Accept: application/json" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -d '{"email":"e2e@example.com","password":"password"}'
curl -s -c $J -b $J -o /dev/null -w "modelo: %{http_code}\n" -X POST $B/modelos -H "Accept: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -F "title=Batismo E2E" -F "imagem=@/tmp/fundo-grande.png;type=image/png"
ID=$(php artisan tinker --execute 'echo App\Models\ModeloCertificado::sole()->id;' | tail -1); echo "modelo: $ID"
curl -s -c $J -b $J -o /dev/null -w "campo Nome: %{http_code}\n" -X POST $B/modelos/$ID/campos -H "Accept: application/json" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -d '{"nome":"Nome","fonte_familia":"Dancing Script","peso_fonte":700,"tamanho_fonte":220,"x":300,"y":600,"cor":"#ffffff"}'
curl -s -c $J -b $J -o /dev/null -w "campo Titulo: %{http_code}\n" -X POST $B/modelos/$ID/campos -H "Accept: application/json" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -d '{"nome":"Titulo","fonte_familia":"Cinzel","peso_fonte":600,"tamanho_fonte":160,"y":200,"cor":"#ffffff","centralizado":true,"espacamento_letras":6}'
curl -s -c $J -b $J -o /tmp/certificado.png -D /tmp/cabecalho.txt -w "gerar: %{http_code} em %{time_total}s, %{size_download} bytes\n" -X POST $B/modelos/$ID/certificados -H "Accept: application/json" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -d '{"valores":{"Nome":"João Pedro da Silva","Titulo":"CERTIDÃO DE BATISMO"}}'
grep -i -E "^content-(type|disposition)" /tmp/cabecalho.txt | tr -d '\r'
file /tmp/certificado.png
```

Expected: `login: 200`, `modelo: 302`, os dois campos `302`, `gerar: 200` em poucos segundos com cerca de 35 MB, `Content-Type: image/png`, `Content-Disposition: attachment; filename="Batismo E2E - Joao Pedro da Silva.png"; filename*=utf-8''...`, e `PNG image data, 3500 x 3500`. **Abra `/tmp/certificado.png`** (ou reduza com `convert`/Imagick) e confira: "CERTIDÃO DE BATISMO" centralizado no topo, "João Pedro da Silva" em Dancing Script, ambos legíveis e sem cortes. Confira também a fonte servida e o histórico:

```bash
curl -s -o /dev/null -w "fonte: %{http_code} %{content_type}\n" $B/fontes/cinzel/600.ttf
curl -s -o /dev/null -w "fonte inexistente: %{http_code}\n" $B/fontes/lato/600.ttf
php artisan tinker --execute 'echo App\Models\GeracaoCertificado::count(), " geracao(oes) | ", json_encode(App\Models\GeracaoCertificado::first()->campos_valores, JSON_UNESCAPED_UNICODE);'
```

Expected: `fonte: 200 font/ttf`, `fonte inexistente: 404`, `1 geracao(oes)` com os dois valores.

Limpe tudo (a exclusão da conta também apaga a imagem do bucket pela fila):

```bash
curl -s -c $J -b $J -o /dev/null -w "excluir conta: %{http_code}\n" -X DELETE $B/settings/profile -H "Accept: application/json" -H "Content-Type: application/json" -H "X-XSRF-TOKEN: $(xsrf)" -d '{"password":"password"}'
sleep 5
php artisan tinker --execute 'echo "usuarios: ", App\Models\User::count();'
rm -f /tmp/fundo-grande.png /tmp/certificado.png /tmp/cabecalho.txt /tmp/cookies.txt
```

Pare o `composer run dev` ao terminar. Expected: `excluir conta: 302` e `usuarios: 0`.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs: spec reflete o que a pesquisa do Plano 2 mostrou

Fórmula da linha de base, supersampling, limitação do kerning GPOS, Arimo/Tinos/Cousine
no lugar das Liberation e a rota que serve as fontes ao editor.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>"
```

---

## Verificação final do Plano 2

- [ ] `php artisan test --compact` verde, com os testes do Plano 1 e os novos.
- [ ] `npm run build`, `npx tsc --noEmit` e `npm run check` sem erros.
- [ ] `php artisan route:list --except-vendor` mostra `certificados.store` e `fontes.show`.
- [ ] `find resources/fonts -name '*.ttf' | wc -l` dá `99`.
- [ ] Verificação ponta a ponta da Task 6 feita contra o servidor real, com a imagem conferida a olho.
- [ ] Nenhuma referência a `Liberation` ou `y + ascender` no spec.

## O que o Plano 3 herda

- A página `meus-certificados/campos` recebe `fontes` (grupos) e `pesos_das_fontes`; declare os `@font-face` com `url(route('fontes.show', ...))` só para os pesos que existem.
- Ao gerar, chame `certificados.store` com `fetch` e trate `200` (PNG), `422` (JSON `message`), `429` (esperar o `Retry-After` e repetir; o lote do Excel faz uma requisição por linha) e `404`/`403`.
- O preview do editor e o PNG usam os mesmos arquivos de fonte, mas o PNG pode sair até ~3% mais largo em fontes com kerning GPOS (seção 7.4 do spec).
