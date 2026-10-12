<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Equipamento;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEquipamento;
use App\Services\Assistente\Cadastros\CadastrosDoAssistente;
use App\Services\Assistente\ResumoDaAcao;
use Illuminate\Database\Eloquent\Model;

/**
 * Prepara criar, alterar ou excluir um equipamento para a secretaria confirmar. Nada é gravado aqui.
 */
final class ProporEquipamento extends PropoeCadastro
{
    public function __construct(
        ResolvedorDeCadastros $resolvedor,
        ResumoDaAcao $resumo,
        CadastrosDoAssistente $cadastros,
        private readonly GravadorDeEquipamento $gravador,
    ) {
        parent::__construct($resolvedor, $resumo, $cadastros);
    }

    public function nome(): string
    {
        return 'propor_equipamento';
    }

    public function descricao(): string
    {
        return 'Prepara criar, alterar ou excluir um equipamento. Nada é gravado: a secretaria precisa clicar em Confirmar. Para alterar ou excluir, informe o equipamento pelo nome ou id e, ao alterar, envie só os campos que mudam. Equipamento com reservas futuras não pode ser excluído.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operacao' => ['type' => 'string', 'enum' => ['criar', 'alterar', 'excluir'], 'description' => 'Criar um equipamento novo, alterar um existente ou excluir um existente.'],
                'alvo' => ['type' => 'string', 'description' => 'Nome ou id do equipamento que será alterado ou excluído. Obrigatório em alterar e excluir.'],
                'nome' => ['type' => 'string', 'description' => 'Nome do equipamento, até 120 caracteres. Obrigatório ao criar.'],
                'descricao' => ['type' => 'string', 'description' => 'Descrição, até 160 caracteres. Opcional.'],
                'quantidade' => ['type' => 'integer', 'description' => 'Quantas unidades existem, de 1 a 1000. Obrigatório ao criar.'],
                'local_guarda' => ['type' => 'string', 'description' => 'Onde o equipamento fica guardado, até 120 caracteres. Opcional.'],
                'manutencao_ate' => ['type' => 'string', 'description' => 'Data até a qual o equipamento está em manutenção, no formato AAAA-MM-DD. Envie vazio para tirar a manutenção. Opcional.'],
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
        return 'equipamento';
    }

    protected function nomeNaFrase(): string
    {
        return 'equipamento';
    }

    protected function campos(): array
    {
        return [
            'nome' => ['rotulo' => 'Nome', 'tipo' => 'texto'],
            'descricao' => ['rotulo' => 'Descrição', 'tipo' => 'texto'],
            'quantidade' => ['rotulo' => 'Quantidade', 'tipo' => 'inteiro'],
            'local_guarda' => ['rotulo' => 'Local de guarda', 'tipo' => 'texto'],
            'manutencao_ate' => ['rotulo' => 'Manutenção até', 'tipo' => 'data'],
        ];
    }

    protected function resolverAlvo(User $usuario, string $idOuNome): Model
    {
        return $this->resolvedor->equipamento($usuario, $idOuNome);
    }

    protected function avisosDaExclusao(Model $alvo): array
    {
        assert($alvo instanceof Equipamento);

        $motivo = $this->gravador->motivoParaNaoExcluir($alvo);

        if ($motivo !== null) {
            throw new ArgumentoInvalido($motivo);
        }

        return [];
    }
}
