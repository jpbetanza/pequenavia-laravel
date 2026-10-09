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
    /**
     * Lista os modelos do usuário e, à parte, os compartilhados com ele.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $modelos = ModeloCertificado::query()
            ->acessiveisPor($user)
            ->with(['user:id,name', 'compartilhamentos'])->withCount('campos')
            ->latest()
            ->get()
            ->map(fn (ModeloCertificado $modelo) => $this->resumo($modelo, $user));

        return Inertia::render('meus-certificados/index', [
            'modelos' => $modelos->where('eh_dono', true)->values(),
            'compartilhados' => $modelos->where('eh_dono', false)->values(),
        ]);
    }

    /**
     * Cria um modelo e envia a imagem dele para o bucket.
     */
    public function store(StoreModeloCertificadoRequest $request): RedirectResponse
    {
        $user = $request->user();

        $modelo = new ModeloCertificado($request->safe()->only(['title', 'description']));
        $modelo->user()->associate($user);
        $modelo->image_path = $this->salvarImagem($request->file('imagem'), $user->id);
        $modelo->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo criado.']);

        return to_route('meus-certificados.index');
    }

    /**
     * Atualiza o texto do modelo e, se enviada, troca a imagem.
     */
    public function update(UpdateModeloCertificadoRequest $request, ModeloCertificado $modelo): RedirectResponse
    {
        $imagemAntiga = null;

        if ($request->hasFile('imagem')) {
            $imagemAntiga = $modelo->image_path;
            // A imagem fica sempre na pasta do dono, mesmo quando um editor compartilhado a troca.
            $modelo->image_path = $this->salvarImagem($request->file('imagem'), $modelo->user_id);
        }

        $modelo->fill($request->safe()->only(['title', 'description']))->save();

        if ($imagemAntiga !== null) {
            Storage::disk('s3')->delete($imagemAntiga);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo atualizado.']);

        return back();
    }

    /**
     * Exclui o modelo; a imagem sai do bucket por um job disparado pelo model.
     */
    public function destroy(ModeloCertificado $modelo): RedirectResponse
    {
        Gate::authorize('delete', $modelo);

        $modelo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo excluído.']);

        return to_route('meus-certificados.index');
    }

    /**
     * Serve a imagem do modelo. O bucket é privado: só quem pode ver o modelo chega a ela.
     */
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

    /**
     * @return array{id: string, title: string, description: string|null, imagem_url: string, dono_nome: string, eh_dono: bool, pode_editar: bool, campos_count: int, compartilhamentos: list<array{id: string, email: string, pode_editar: bool}>}
     */
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
            'campos_count' => (int) $modelo->campos_count,
            'compartilhamentos' => $ehDono
                ? $modelo->compartilhamentos->map(fn (CompartilhamentoModelo $compartilhamento) => [
                    'id' => $compartilhamento->id,
                    'email' => $compartilhamento->email,
                    'pode_editar' => $compartilhamento->pode_editar,
                ])->values()->all()
                : [],
        ];
    }
}
