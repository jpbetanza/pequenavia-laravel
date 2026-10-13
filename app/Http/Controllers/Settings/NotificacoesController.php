<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InscricaoPush;
use App\Services\Push\Notificador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as RespostaVazia;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notificações no celular: liga e desliga o aparelho atual, lista os aparelhos e manda uma notificação de teste.
 * A inscrição é identificada pelo endpoint do serviço de push, que é único por navegador.
 */
class NotificacoesController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notificacoes', [
            'configurado' => Notificador::configurado(),
            'chavePublica' => config('push.chave_publica'),
            'aparelhos' => $request->user()->inscricoesPush()
                ->latest()
                ->get()
                ->map(fn (InscricaoPush $inscricao) => [
                    'id' => $inscricao->id,
                    'endpoint' => $inscricao->endpoint,
                    'dispositivo' => $inscricao->dispositivo,
                    'ativada_em' => $inscricao->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    /**
     * Guarda a inscrição deste navegador. O mesmo navegador com outro usuário logado passa a avisar esse usuário.
     */
    public function store(Request $request): RespostaVazia
    {
        abort_unless(Notificador::configurado(), 503, 'As notificações não estão configuradas no servidor.');

        $dados = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:255'],
        ]);

        $inscricao = InscricaoPush::query()->firstOrNew(['endpoint' => $dados['endpoint']]);
        $inscricao->user()->associate($request->user());
        $inscricao->fill([
            'chave_p256dh' => $dados['keys']['p256dh'],
            'chave_auth' => $dados['keys']['auth'],
            'dispositivo' => $dados['dispositivo'] ?? null,
        ])->save();

        return response()->noContent();
    }

    public function destroy(Request $request): RespostaVazia
    {
        $dados = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);

        $request->user()->inscricoesPush()->where('endpoint', $dados['endpoint'])->delete();

        return response()->noContent();
    }

    public function teste(Request $request, Notificador $notificador): JsonResponse
    {
        abort_unless(Notificador::configurado(), 503, 'As notificações não estão configuradas no servidor.');

        $entregues = $notificador->notificar(
            $request->user(),
            'Notificações ligadas',
            'É assim que os avisos do PequenaVia vão aparecer neste aparelho.',
            route('notificacoes.edit', absolute: false),
            'teste',
        );

        abort_if($entregues === 0, 422, 'Nenhum aparelho recebeu. Ative as notificações neste aparelho e tente de novo.');

        return response()->json(['entregues' => $entregues]);
    }
}
