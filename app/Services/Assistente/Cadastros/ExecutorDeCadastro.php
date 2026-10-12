<?php

namespace App\Services\Assistente\Cadastros;

use App\Models\AcaoProposta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpResponseException;

/**
 * Executa uma proposta de cadastro depois do clique em Confirmar. Revalida tudo com as regras da tela,
 * porque o cadastro pode ter mudado ou o nome pode ter sido usado desde a proposta.
 */
final class ExecutorDeCadastro
{
    public function __construct(private readonly CadastrosDoAssistente $cadastros) {}

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
            $dados = $this->validarFinal($usuario, $entidade, $proposta['final'], null);

            return (string) $this->cadastros->gravador($entidade)->criar($usuario, $dados)->getKey();
        }

        $alvo = $this->alvoAtual($usuario, $entidade, $proposta['alvo_id'] ?? null);
        $this->conferirVersao($alvo, $proposta);

        if ($proposta['operacao'] === 'alterar') {
            $dados = $this->validarFinal($usuario, $entidade, $proposta['final'], $alvo);
            $this->cadastros->gravador($entidade)->atualizar($alvo, $dados);

            return (string) $alvo->getKey();
        }

        $this->gravarExclusao($entidade, $alvo);

        return null;
    }

    private function gravarExclusao(string $entidade, Model $alvo): void
    {
        try {
            $this->cadastros->gravador($entidade)->excluir($alvo);
        } catch (ValidationException $erro) {
            $this->recusar(collect($erro->errors())->flatten()->first());
        }
    }

    /**
     * O alvo é travado para a gravação: a conferência de versão e a escrita acontecem sem outra alteração no meio.
     */
    private function alvoAtual(User $usuario, string $entidade, ?string $id): Model
    {
        $classe = $this->cadastros->modelo($entidade);

        $alvo = $id === null ? null : $classe::query()->where('user_id', $usuario->id)->whereKey($id)->lockForUpdate()->first();

        return $alvo ?? $this->recusar('O cadastro não existe mais.');
    }

    /**
     * Compara com a impressão digital da proposta. Proposta antiga, sem impressão, cai na data de atualização.
     *
     * @param  array<string, mixed>  $proposta
     */
    private function conferirVersao(Model $alvo, array $proposta): void
    {
        $mudou = isset($proposta['alvo_impressao'])
            ? ! hash_equals($proposta['alvo_impressao'], $this->cadastros->impressao($alvo))
            : ! $this->mesmaDataDeAtualizacao($alvo, $proposta['alvo_atualizado_em'] ?? null);

        if ($mudou) {
            $this->recusar('O cadastro mudou desde a proposta. Peça de novo.');
        }
    }

    private function mesmaDataDeAtualizacao(Model $alvo, ?string $atualizadoEm): bool
    {
        return $atualizadoEm !== null && $alvo->updated_at !== null && Carbon::parse($atualizadoEm)->equalTo($alvo->updated_at);
    }

    /**
     * @param  array<string, mixed>  $final
     * @return array<string, mixed>
     */
    private function validarFinal(User $usuario, string $entidade, array $final, ?Model $alvo): array
    {
        try {
            return $this->cadastros->validar($entidade, $usuario, $final, $alvo);
        } catch (ValidationException $erro) {
            $this->recusar(collect($erro->errors())->flatten()->first());
        }
    }

    private function recusar(string $mensagem): never
    {
        abort(response()->json(['message' => $mensagem], 422));
    }
}
