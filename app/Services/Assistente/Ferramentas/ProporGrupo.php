<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Grupo;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeGrupo;
use App\Services\Agenda\Cadastros\RegrasDeGrupo;
use App\Services\Assistente\ResumoDaAcao;
use Illuminate\Database\Eloquent\Model;

/**
 * Prepara criar, alterar ou excluir um grupo ou pastoral para a secretaria confirmar. Nada é gravado aqui.
 */
final class ProporGrupo extends PropoeCadastro
{
    public function __construct(
        ResolvedorDeCadastros $resolvedor,
        ResumoDaAcao $resumo,
        private readonly GravadorDeGrupo $gravador,
    ) {
        parent::__construct($resolvedor, $resumo);
    }

    public function nome(): string
    {
        return 'propor_grupo';
    }

    public function descricao(): string
    {
        return 'Prepara criar, alterar ou excluir um grupo ou pastoral. Nada é gravado: a secretaria precisa clicar em Confirmar. Para alterar ou excluir, informe o grupo pelo nome ou id e, ao alterar, envie só os campos que mudam.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operacao' => ['type' => 'string', 'enum' => ['criar', 'alterar', 'excluir'], 'description' => 'Criar um grupo novo, alterar um existente ou excluir um existente.'],
                'alvo' => ['type' => 'string', 'description' => 'Nome ou id do grupo que será alterado ou excluído. Obrigatório em alterar e excluir.'],
                'nome' => ['type' => 'string', 'description' => 'Nome do grupo, até 120 caracteres. Obrigatório ao criar.'],
                'coordenacao' => ['type' => 'string', 'description' => 'Quem coordena, até 120 caracteres. Opcional.'],
                'contato' => ['type' => 'string', 'description' => 'Telefone ou contato, até 120 caracteres. Opcional.'],
                'observacao' => ['type' => 'string', 'description' => 'Observação livre. Opcional.'],
                'ativo' => ['type' => 'boolean', 'description' => 'Se o grupo está ativo. Opcional.'],
            ],
            'required' => ['operacao'],
        ];
    }

    public function status(): string
    {
        return 'Preparando o cadastro…';
    }

    protected function entidade(): string
    {
        return 'grupo';
    }

    protected function nomeNaFrase(): string
    {
        return 'grupo';
    }

    protected function campos(): array
    {
        return [
            'nome' => ['rotulo' => 'Nome', 'tipo' => 'texto'],
            'coordenacao' => ['rotulo' => 'Coordenação', 'tipo' => 'texto'],
            'contato' => ['rotulo' => 'Contato', 'tipo' => 'texto'],
            'observacao' => ['rotulo' => 'Observação', 'tipo' => 'texto'],
            'ativo' => ['rotulo' => 'Ativo', 'tipo' => 'booleano'],
        ];
    }

    protected function regras(User $dono, ?Model $atual = null): array
    {
        return RegrasDeGrupo::regras($dono, $atual instanceof Grupo ? $atual : null);
    }

    protected function mensagens(): array
    {
        return RegrasDeGrupo::mensagens();
    }

    protected function resolverAlvo(User $usuario, string $idOuNome): Model
    {
        return $this->resolvedor->grupo($usuario, $idOuNome);
    }

    protected function avisosDaExclusao(Model $alvo): array
    {
        assert($alvo instanceof Grupo);

        $afetadas = $this->gravador->reservasAfetadas($alvo);

        return match (true) {
            $afetadas === 0 => [],
            $afetadas === 1 => ['1 reserva futura ficará sem grupo.'],
            default => ["{$afetadas} reservas futuras ficarão sem grupo."],
        };
    }
}
