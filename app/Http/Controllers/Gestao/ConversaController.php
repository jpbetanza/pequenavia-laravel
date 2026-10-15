<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\CorrigirClassificacaoRequest;
use App\Models\Conversa;
use App\Models\User;
use App\Services\Gestao\ConversasDaGestao;
use App\Services\Gestao\EscopoDaGestao;
use App\Services\Gestao\PainelDeGestao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Conversas classificadas: lista com filtros, matriz de casos, detalhe (com registro de acesso) e correção.
 */
class ConversaController extends Controller
{
    public function __construct(
        private readonly PainelDeGestao $painel,
        private readonly ConversasDaGestao $conversas,
        private readonly EscopoDaGestao $escopo,
    ) {}

    public function index(Request $request): Response
    {
        $periodo = $this->painel->periodo($request->query('periodo'));

        // Valores que não existem na lista viram filtro vazio, em vez de erro de validação numa página de leitura.
        $filtros = [
            'caso' => CasoDeUso::tryFrom((string) $request->query('caso')),
            'resultado' => ResultadoDaConversa::tryFrom((string) $request->query('resultado')),
            'cliente' => $this->uuidOuNulo($request->query('cliente')),
            'frustrada' => $request->boolean('frustrada'),
            'a_revisar' => $request->boolean('a_revisar'),
        ];

        $selecionada = null;
        $conversaId = $this->uuidOuNulo($request->query('conversa'));

        if ($request->query('conversa') !== null) {
            $conversa = $conversaId === null ? null : Conversa::query()
                ->whereKey($conversaId)
                ->whereIn('user_id', $this->escopo->ids())
                ->first();

            abort_if($conversa === null, 404);

            /** @var User $gestor */
            $gestor = $request->user();
            $selecionada = $this->conversas->detalhe($conversa, $gestor);
        }

        return Inertia::render('gestao/conversas', [
            'periodo' => $periodo->dias,
            'filtros' => [
                'caso' => $filtros['caso']?->value,
                'resultado' => $filtros['resultado']?->value,
                'cliente' => $filtros['cliente'],
                'frustrada' => $filtros['frustrada'],
                'a_revisar' => $filtros['a_revisar'],
            ],
            'opcoes' => $this->conversas->opcoes(),
            'matriz' => $this->conversas->matriz($periodo, $filtros['cliente']),
            'conversas' => $this->conversas->lista($periodo, $filtros),
            'a_revisar_total' => $this->conversas->aRevisarTotal(),
            'selecionada' => $selecionada,
        ]);
    }

    /**
     * Corrige os campos enviados. Nada é apagado do JEV: a correção fica num campo à parte e vence na leitura.
     */
    public function corrigir(CorrigirClassificacaoRequest $request, Conversa $conversa): RedirectResponse
    {
        $classificacao = $conversa->classificacao;

        abort_if($classificacao === null, 404);

        $classificacao->correcao = array_merge($classificacao->correcao ?? [], $request->validated());
        $classificacao->a_revisar = false;
        $classificacao->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Classificação corrigida.']);

        return back();
    }

    private function uuidOuNulo(mixed $valor): ?string
    {
        return is_string($valor) && Str::isUuid($valor) ? $valor : null;
    }
}
