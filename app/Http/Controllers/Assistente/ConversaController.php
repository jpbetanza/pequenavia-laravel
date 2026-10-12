<?php

namespace App\Http\Controllers\Assistente;

use App\Enums\PapelDaMensagem;
use App\Http\Controllers\Controller;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\Assistente\ResumoDaAcao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Lista, cria, mostra e exclui as conversas do assistente. Conversa de outro usuário responde 404.
 */
class ConversaController extends Controller
{
    private const TITULO_INICIAL = 'Nova conversa';

    public function __construct(private readonly ResumoDaAcao $resumo) {}

    public function index(Request $request): JsonResponse
    {
        $termo = $request->query('busca');
        $busca = is_string($termo) ? Str::lower(Str::ascii(trim($termo))) : '';

        $conversas = Conversa::query()
            ->where('user_id', $request->user()->id)
            ->addSelect(['previa' => Mensagem::query()
                ->select('conteudo')
                ->whereColumn('conversa_id', 'conversas.id')
                ->where('papel', PapelDaMensagem::Assistente)
                ->where('erro', false)
                ->whereNull('chamadas')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(1),
            ])
            ->orderByRaw('ultima_mensagem_em DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        if ($busca !== '') {
            $conversas = $conversas->filter(fn (Conversa $conversa) => str_contains(Str::lower(Str::ascii($conversa->titulo)), $busca))->values();
        }

        return response()->json([
            'conversas' => $conversas->map(fn (Conversa $conversa) => [
                'id' => $conversa->id,
                'titulo' => $conversa->titulo,
                'previa' => $conversa->previa === null ? null : Str::limit($conversa->previa, 80),
                'ultima_mensagem_em' => $conversa->ultima_mensagem_em?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $conversa = $request->user()->conversas()->create(['titulo' => self::TITULO_INICIAL]);

        return response()->json(['conversa' => ['id' => $conversa->id, 'titulo' => $conversa->titulo]], 201);
    }

    public function show(Request $request, Conversa $conversa): JsonResponse
    {
        Gate::authorize('view', $conversa);

        $mensagens = $conversa->mensagens()
            ->where(fn ($consulta) => $consulta
                ->where('papel', PapelDaMensagem::Usuario)
                ->orWhere(fn ($assistente) => $assistente
                    ->where('papel', PapelDaMensagem::Assistente)
                    ->whereNull('chamadas')))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $acoes = $this->acoesDoUsuario($request, $mensagens);

        return response()->json([
            'conversa' => ['id' => $conversa->id, 'titulo' => $conversa->titulo],
            'mensagens' => $mensagens->map(fn (Mensagem $mensagem) => [
                'id' => $mensagem->id,
                'papel' => $mensagem->papel->value,
                'conteudo' => $mensagem->conteudo,
                'cartoes' => $this->cartoesAtuais($mensagem->cartoes ?? [], $acoes),
                'erro' => $mensagem->erro,
                'criada_em' => $mensagem->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function destroy(Conversa $conversa): Response
    {
        Gate::authorize('delete', $conversa);

        $conversa->delete();

        return response()->noContent();
    }

    /**
     * Ações propostas citadas nas mensagens, só as do usuário logado, indexadas pelo id.
     *
     * @param  Collection<int, Mensagem>  $mensagens
     * @return Collection<string, AcaoProposta>
     */
    private function acoesDoUsuario(Request $request, Collection $mensagens): Collection
    {
        $ids = $mensagens
            ->flatMap(fn (Mensagem $mensagem) => $mensagem->cartoes ?? [])
            ->where('tipo', 'acao_proposta')
            ->map(fn (array $cartao) => $cartao['dados']['id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return AcaoProposta::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * Cartões de ação saem com o status atual da ação. Ação que não existe mais não aparece.
     *
     * @param  list<array{tipo: string, dados: array<string, mixed>}>  $cartoes
     * @param  Collection<string, AcaoProposta>  $acoes
     * @return list<array{tipo: string, dados: array<string, mixed>}>
     */
    private function cartoesAtuais(array $cartoes, Collection $acoes): array
    {
        return collect($cartoes)
            ->map(function (array $cartao) use ($acoes) {
                if ($cartao['tipo'] !== 'acao_proposta') {
                    return $cartao;
                }

                $acao = $acoes->get($cartao['dados']['id'] ?? null);

                return $acao === null ? null : ['tipo' => 'acao_proposta', 'dados' => $this->resumo->paraCartao($acao)];
            })
            ->filter()
            ->values()
            ->all();
    }
}
