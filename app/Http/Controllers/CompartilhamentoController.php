<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompartilhamentoRequest;
use App\Http\Requests\UpdateCompartilhamentoRequest;
use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CompartilhamentoController extends Controller
{
    /**
     * Compartilha o modelo com um email. Por padrão o convidado só pode ver e gerar.
     */
    public function store(StoreCompartilhamentoRequest $request, ModeloCertificado $modelo): RedirectResponse
    {
        $modelo->compartilhamentos()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo compartilhado.']);

        return back();
    }

    public function update(UpdateCompartilhamentoRequest $request, ModeloCertificado $modelo, CompartilhamentoModelo $compartilhamento): RedirectResponse
    {
        $compartilhamento->update($request->validated());

        return back();
    }

    public function destroy(ModeloCertificado $modelo, CompartilhamentoModelo $compartilhamento): RedirectResponse
    {
        Gate::authorize('gerenciarCompartilhamentos', $modelo);

        $compartilhamento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compartilhamento removido.']);

        return back();
    }
}
