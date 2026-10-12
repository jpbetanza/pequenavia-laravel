<?php

namespace App\Services\Assistente\Cadastros;

use App\Models\AcaoProposta;
use App\Models\Equipamento;
use App\Models\Grupo;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEquipamento;
use App\Services\Agenda\Cadastros\GravadorDeGrupo;
use App\Services\Agenda\Cadastros\RegrasDeEquipamento;
use App\Services\Agenda\Cadastros\RegrasDeGrupo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpResponseException;

/**
 * Executa uma proposta de cadastro depois do clique em Confirmar. Revalida tudo com as regras da tela,
 * porque o cadastro pode ter mudado ou o nome pode ter sido usado desde a proposta.
 */
final class ExecutorDeCadastro
{
    public function __construct(
        private readonly GravadorDeGrupo $grupos,
        private readonly GravadorDeEquipamento $equipamentos,
    ) {}

    /**
     * Grava a ação de cadastro e devolve o id do cadastro criado ou alterado (null na exclusão).
     *
     * @throws HttpResponseException 422 com {message} quando o cadastro mudou ou o dado é inválido
     */
    public function executar(User $usuario, AcaoProposta $acao): ?string
    {
        $proposta = $acao->dados;
        $entidade = $proposta['entidade'];

        if ($proposta['operacao'] === 'criar') {
            return (string) $this->gravarNovo($usuario, $entidade, $proposta['final'])->getKey();
        }

        $alvo = $this->alvoAtual($usuario, $entidade, $proposta['alvo_id'] ?? null);
        $this->conferirVersao($alvo, $proposta['alvo_atualizado_em'] ?? null);

        if ($proposta['operacao'] === 'alterar') {
            $this->gravarAlteracao($usuario, $entidade, $alvo, $proposta['final']);

            return (string) $alvo->getKey();
        }

        $this->gravarExclusao($entidade, $alvo);

        return null;
    }

    /**
     * @param  array<string, mixed>  $final
     */
    private function gravarNovo(User $usuario, string $entidade, array $final): Model
    {
        $dados = $this->validarFinal($usuario, $entidade, $final, null);

        return match ($entidade) {
            'grupo' => $this->grupos->criar($usuario, $dados),
            'equipamento' => $this->equipamentos->criar($usuario, $dados),
        };
    }

    /**
     * @param  array<string, mixed>  $final
     */
    private function gravarAlteracao(User $usuario, string $entidade, Model $alvo, array $final): void
    {
        $dados = $this->validarFinal($usuario, $entidade, $final, $alvo);

        match ($entidade) {
            'grupo' => $this->grupos->atualizar($alvo, $dados),
            'equipamento' => $this->equipamentos->atualizar($alvo, $dados),
        };
    }

    private function gravarExclusao(string $entidade, Model $alvo): void
    {
        try {
            match ($entidade) {
                'grupo' => $this->grupos->excluir($alvo),
                'equipamento' => $this->equipamentos->excluir($alvo),
            };
        } catch (ValidationException $erro) {
            $this->recusar(collect($erro->errors())->flatten()->first());
        }
    }

    /**
     * @return Grupo|Equipamento
     */
    private function alvoAtual(User $usuario, string $entidade, ?string $id): Model
    {
        $classe = match ($entidade) {
            'grupo' => Grupo::class,
            'equipamento' => Equipamento::class,
        };

        $alvo = $id === null ? null : $classe::query()->where('user_id', $usuario->id)->whereKey($id)->first();

        return $alvo ?? $this->recusar('O cadastro não existe mais.');
    }

    private function conferirVersao(Model $alvo, ?string $atualizadoEm): void
    {
        if ($atualizadoEm === null || $alvo->updated_at === null || ! Carbon::parse($atualizadoEm)->equalTo($alvo->updated_at)) {
            $this->recusar('O cadastro mudou desde a proposta. Peça de novo.');
        }
    }

    /**
     * @param  array<string, mixed>  $final
     * @return array<string, mixed>
     */
    private function validarFinal(User $usuario, string $entidade, array $final, ?Model $alvo): array
    {
        [$regras, $mensagens] = match ($entidade) {
            'grupo' => [RegrasDeGrupo::regras($usuario, $alvo instanceof Grupo ? $alvo : null), RegrasDeGrupo::mensagens()],
            'equipamento' => [RegrasDeEquipamento::regras($usuario, $alvo instanceof Equipamento ? $alvo : null), RegrasDeEquipamento::mensagens()],
        };

        $validador = Validator::make($final, $regras, $mensagens);

        if ($validador->fails()) {
            $this->recusar($validador->errors()->first());
        }

        return $validador->validated();
    }

    private function recusar(string $mensagem): never
    {
        abort(response()->json(['message' => $mensagem], 422));
    }
}
