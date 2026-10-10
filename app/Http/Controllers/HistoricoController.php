<?php

namespace App\Http\Controllers;

use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoricoController extends Controller
{
    /**
     * Mostra as 50 gerações mais recentes do usuário, filtradas pela busca e pelo modelo.
     */
    public function index(Request $request): Response
    {
        $dados = $request->validate([
            'busca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'uuid'],
        ]);

        $busca = (string) ($dados['busca'] ?? '');
        $modelo = $dados['modelo'] ?? null;

        $geracoes = $request->user()->geracoes()
            ->with('modelo.campos:id,modelo_id,nome,created_at', 'user:id,name')
            ->when($busca !== '', fn (Builder $query) => $query->whereRaw(
                'campos_valores::text ilike ?',
                [$this->padraoDaBusca($busca)],
            ))
            ->when($modelo !== null, fn (Builder $query) => $query->where('modelo_id', $modelo))
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (GeracaoCertificado $geracao) => [
                'id' => $geracao->id,
                'modelo_id' => $geracao->modelo_id,
                'modelo_title' => $geracao->modelo_title,
                'campos_valores' => $geracao->campos_valores,
                'ordem_campos' => $geracao->modelo?->campos->pluck('nome')->values()->all() ?? [],
                'gerado_por' => $geracao->user->name,
                'created_at' => $geracao->created_at,
            ]);

        $modelos = ModeloCertificado::query()
            ->whereIn('id', $request->user()->geracoes()->whereNotNull('modelo_id')->select('modelo_id'))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (ModeloCertificado $modelo) => ['id' => $modelo->id, 'title' => $modelo->title])
            ->values();

        return Inertia::render('historico/index', [
            'geracoes' => $geracoes,
            'filtros' => ['busca' => $busca, 'modelo' => $modelo],
            'modelos' => $modelos,
        ]);
    }

    /**
     * Padrão ILIKE para o termo. Como a busca é feita sobre o JSON de `campos_valores`, o termo é
     * codificado como o jsonb o escreve (a barra invertida vira `\\`), e depois `%`, `_` e `\` viram texto literal.
     */
    private function padraoDaBusca(string $busca): string
    {
        $codificado = json_encode($busca, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $trecho = is_string($codificado) ? substr($codificado, 1, -1) : $busca;

        return '%'.addcslashes($trecho, '%_\\').'%';
    }
}
