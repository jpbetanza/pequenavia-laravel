<?php

namespace App\Providers;

use App\Services\Assistente\CaixaDeFerramentas;
use App\Services\Assistente\Ferramentas\ConsultarAgenda;
use App\Services\Assistente\Ferramentas\ListarCadastros;
use App\Services\Assistente\Ferramentas\ListarSolicitacoes;
use App\Services\Assistente\Ferramentas\ResumoDeCertificados;
use App\Services\Assistente\Ferramentas\VerificarDisponibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CaixaDeFerramentas::class, fn ($app) => new CaixaDeFerramentas([
            $app->make(ConsultarAgenda::class),
            $app->make(VerificarDisponibilidade::class),
            $app->make(ListarSolicitacoes::class),
            $app->make(ListarCadastros::class),
            $app->make(ResumoDeCertificados::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureSolicitacoesRateLimiting();
    }

    /**
     * Limita a geração de certificados por usuário. Um valor não positivo na configuração bloquearia
     * todas as gerações, então nesse caso vale o padrão de 60 por minuto.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('gerar-certificado', function (Request $request): Limit {
            $geracoesPorMinuto = (int) config('certificados.geracoes_por_minuto');

            return Limit::perMinute($geracoesPorMinuto > 0 ? $geracoesPorMinuto : 60)
                ->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Limita os envios do formulário público por IP: 5 por minuto e 20 por hora. Cada janela tem a própria chave,
     * senão o throttle conta os dois limites num contador só.
     */
    protected function configureSolicitacoesRateLimiting(): void
    {
        RateLimiter::for('solicitacoes', fn (Request $request): array => [
            Limit::perMinute(5)->by('minuto:'.$request->ip()),
            Limit::perHour(20)->by('hora:'.$request->ip()),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
