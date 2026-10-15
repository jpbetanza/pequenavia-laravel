<?php

namespace App\Http\Middleware;

use App\Enums\SituacaoDoTema;
use App\Enums\StatusSolicitacao;
use App\Models\TemaDePedido;
use App\Services\Gestao\PainelDeGestao;
use App\Services\PrimeirosPassos\PrimeirosPassos;
use App\Support\VersaoDoSite;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'versao' => VersaoDoSite::atual(),
            'auth' => [
                'user' => $request->user(),
            ],
            'agenda' => fn () => $request->user() === null ? null : [
                'solicitacoes_pendentes' => $request->user()
                    ->solicitacoes()
                    ->where('status', StatusSolicitacao::Pendente)
                    ->count(),
            ],
            'primeiros_passos' => fn () => $request->user() === null ? null : app(PrimeirosPassos::class)->estado($request->user()),
            'assistente' => fn () => $request->user() === null ? null : [
                'disponivel' => filled(config('assistente.chave')),
            ],
            // Só para gestores: o menu de gestão e o contador de pedidos não atendidos.
            'gestao' => fn () => $request->user()?->admin ? [
                'temas_novos' => TemaDePedido::query()->ativos()->where('situacao', SituacaoDoTema::Novo->value)->count(),
                'classificacao' => app(PainelDeGestao::class)->classificacao(),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
