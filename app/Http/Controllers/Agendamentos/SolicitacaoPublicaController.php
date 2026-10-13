<?php

namespace App\Http\Controllers\Agendamentos;

use App\Enums\StatusSolicitacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agendamentos\StoreSolicitacaoPublicaRequest;
use App\Jobs\NotificarNovaSolicitacao;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulário público de solicitação, identificado pelo slug da agenda. Não exige login.
 * Slug que não existe responde 404; a visita nunca cria slug.
 */
class SolicitacaoPublicaController extends Controller
{
    /**
     * Formulário com os grupos ativos, os espaços que aceitam pedidos e os equipamentos fora de manutenção hoje.
     */
    public function create(string $slug): Response
    {
        $dono = $this->donoDoSlug($slug);
        $hoje = CarbonImmutable::now(config('agendamentos.fuso'))->toDateString();

        return Inertia::render('reservar/create', [
            'agenda' => $this->agenda($dono, $slug),
            'hoje' => $hoje,
            'grupos' => Grupo::query()
                ->where('user_id', $dono->id)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get()
                ->map(fn (Grupo $grupo) => ['id' => $grupo->id, 'nome' => $grupo->nome])
                ->values()
                ->all(),
            'espacos' => Espaco::query()
                ->where('user_id', $dono->id)
                ->where('ativo', true)
                ->where('aceita_solicitacoes', true)
                ->orderBy('nome')
                ->get()
                ->map(fn (Espaco $espaco) => [
                    'id' => $espaco->id,
                    'nome' => $espaco->nome,
                    'capacidade' => $espaco->capacidade,
                    'antecedencia_min_horas' => $espaco->antecedencia_min_horas,
                    'duracao_max_min' => $espaco->duracao_max_min,
                ])
                ->values()
                ->all(),
            'equipamentos' => Equipamento::query()
                ->where('user_id', $dono->id)
                ->where(fn ($query) => $query->whereNull('manutencao_ate')->orWhere('manutencao_ate', '<', $hoje))
                ->orderBy('nome')
                ->get()
                ->map(fn (Equipamento $equipamento) => ['id' => $equipamento->id, 'nome' => $equipamento->nome])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Cria a solicitação pendente, com os equipamentos pedidos (uma unidade cada), e avisa a secretaria no celular.
     * Honeypot preenchido não chega aqui.
     */
    public function store(StoreSolicitacaoPublicaRequest $request): RedirectResponse
    {
        $dono = $request->dono();
        $dados = $request->validated();

        $solicitacao = DB::transaction(function () use ($dono, $dados): Solicitacao {
            $solicitacao = $dono->solicitacoes()->create([
                'grupo_id' => $dados['grupo_id'],
                'nome' => $dados['nome'],
                'telefone' => $dados['telefone'],
                'titulo' => $dados['titulo'],
                'data' => $dados['data'],
                'inicio' => $dados['inicio'],
                'fim' => $dados['fim'],
                'espaco_id' => $dados['espaco_id'] ?? null,
                'pessoas' => $dados['pessoas'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
                'status' => StatusSolicitacao::Pendente,
            ]);

            $solicitacao->equipamentos()->attach(
                collect($dados['equipamentos'] ?? [])
                    ->mapWithKeys(fn (string $equipamentoId) => [$equipamentoId => ['quantidade' => 1]])
                    ->all(),
            );

            return $solicitacao;
        });

        NotificarNovaSolicitacao::dispatch($solicitacao->id);

        return to_route('reservar.enviado', ['slug' => $request->route('slug')]);
    }

    /**
     * Aviso de envio, com o link para fazer outro pedido.
     */
    public function enviado(string $slug): Response
    {
        return Inertia::render('reservar/enviado', [
            'agenda' => $this->agenda($this->donoDoSlug($slug), $slug),
        ]);
    }

    private function donoDoSlug(string $slug): User
    {
        return User::query()->where('agenda_slug', $slug)->firstOrFail();
    }

    /**
     * @return array{nome: string, slug: string}
     */
    private function agenda(User $dono, string $slug): array
    {
        return ['nome' => $dono->name, 'slug' => $slug];
    }
}
