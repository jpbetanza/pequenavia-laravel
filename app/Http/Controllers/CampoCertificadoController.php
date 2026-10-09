<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarCampoCertificadoRequest;
use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use App\Services\CatalogoFontes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampoCertificadoController extends Controller
{
    /**
     * Mostra o editor de campos do modelo.
     */
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
            'pesos_das_fontes' => CatalogoFontes::pesosPorPasta(),
        ]);
    }

    /**
     * Cria um campo. O nome, se não vier, é o primeiro "Campo N" livre.
     */
    public function store(SalvarCampoCertificadoRequest $request, ModeloCertificado $modelo): RedirectResponse
    {
        $modelo->campos()->create([
            ...$request->validated(),
            'nome' => $request->validated('nome') ?? $this->proximoNome($modelo),
        ]);

        return back();
    }

    public function update(SalvarCampoCertificadoRequest $request, ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse
    {
        $campo->update($request->validated());

        return back();
    }

    public function destroy(ModeloCertificado $modelo, CampoCertificado $campo): RedirectResponse
    {
        Gate::authorize('editarCampos', $modelo);

        $campo->delete();

        return back();
    }

    private function proximoNome(ModeloCertificado $modelo): string
    {
        $existentes = $modelo->campos()->pluck('nome')->all();
        $numero = 1;

        while (in_array("Campo {$numero}", $existentes, true)) {
            $numero++;
        }

        return "Campo {$numero}";
    }
}
