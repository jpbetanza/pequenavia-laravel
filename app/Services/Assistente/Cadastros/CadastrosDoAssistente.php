<?php

namespace App\Services\Assistente\Cadastros;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\User;
use App\Services\Agenda\Cadastros\GravadorDeEquipamento;
use App\Services\Agenda\Cadastros\GravadorDeEspaco;
use App\Services\Agenda\Cadastros\GravadorDeGrupo;
use App\Services\Agenda\Cadastros\RegrasDeEquipamento;
use App\Services\Agenda\Cadastros\RegrasDeEspaco;
use App\Services\Agenda\Cadastros\RegrasDeGrupo;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Mapa dos cadastros que o assistente propõe: modelo, regras e gravador de cada entidade.
 * Propor e executar leem daqui, para que a validação e a gravação sejam as mesmas da tela.
 */
final class CadastrosDoAssistente
{
    /** @var array<string, array{modelo: class-string<Model>, regras: class-string, gravador: class-string}> */
    private const CADASTROS = [
        'espaco' => ['modelo' => Espaco::class, 'regras' => RegrasDeEspaco::class, 'gravador' => GravadorDeEspaco::class],
        'grupo' => ['modelo' => Grupo::class, 'regras' => RegrasDeGrupo::class, 'gravador' => GravadorDeGrupo::class],
        'equipamento' => ['modelo' => Equipamento::class, 'regras' => RegrasDeEquipamento::class, 'gravador' => GravadorDeEquipamento::class],
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @return class-string<Model>
     */
    public function modelo(string $entidade): string
    {
        return $this->definicao($entidade)['modelo'];
    }

    public function gravador(string $entidade): object
    {
        return $this->container->make($this->definicao($entidade)['gravador']);
    }

    /**
     * Impressão digital do estado completo do cadastro: colunas, e no espaço também horários e bloqueios.
     * Muda com qualquer edição, inclusive as que não tocam `updated_at`, e não depende da resolução do relógio.
     */
    public function impressao(Model $alvo): string
    {
        $estado = Arr::except($alvo->attributesToArray(), ['created_at', 'updated_at']);

        if ($alvo instanceof Espaco) {
            $estado['horarios'] = $alvo->horarios()->lockForUpdate()->get()
                ->map(fn ($horario) => [
                    'dia_semana' => (int) $horario->dia_semana,
                    'abre' => substr((string) $horario->abre, 0, 5),
                    'fecha' => substr((string) $horario->fecha, 0, 5),
                ])
                ->sortBy('dia_semana')->values()->all();

            $estado['bloqueios'] = $alvo->bloqueios()->lockForUpdate()->get()
                ->map(fn ($bloqueio) => [
                    'inicio' => $bloqueio->inicio->toDateString(),
                    'fim' => $bloqueio->fim->toDateString(),
                    'motivo' => (string) $bloqueio->motivo,
                ])
                ->sortBy(['inicio', 'fim', 'motivo'])->values()->all();
        }

        return hash('sha256', json_encode($this->ordenar($estado), JSON_THROW_ON_ERROR));
    }

    /**
     * Regras do Validator, com o nome único entre os cadastros do dono (ignora o próprio `$atual`).
     *
     * @return array<string, mixed>
     */
    public function regras(string $entidade, User $dono, ?Model $atual = null): array
    {
        $definicao = $this->definicao($entidade);
        $modelo = $definicao['modelo'];
        $classeRegras = $definicao['regras'];

        return $classeRegras::regras($dono, $atual instanceof $modelo ? $atual : null);
    }

    /**
     * @return array<string, string>
     */
    public function mensagens(string $entidade): array
    {
        $classeRegras = $this->definicao($entidade)['regras'];

        return $classeRegras::mensagens();
    }

    /**
     * Valida com as regras e mensagens da tela e, quando houver, com as regras que cruzam campos.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validar(string $entidade, User $dono, array $dados, ?Model $atual = null): array
    {
        $validados = Validator::make($dados, $this->regras($entidade, $dono, $atual), $this->mensagens($entidade))->validate();

        $erros = $this->errosCruzados($entidade, $validados);

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }

        return $validados;
    }

    /**
     * Só o espaço tem regras que cruzam campos (fecha depois de abre, fim do bloqueio depois do início).
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, string>
     */
    private function errosCruzados(string $entidade, array $dados): array
    {
        $classeRegras = $this->definicao($entidade)['regras'];

        return method_exists($classeRegras, 'errosCruzados') ? $classeRegras::errosCruzados($dados) : [];
    }

    /**
     * Ordena as chaves em todos os níveis, para que a ordem de leitura não mude a impressão digital.
     *
     * @param  array<mixed>  $valor
     * @return array<mixed>
     */
    private function ordenar(array $valor): array
    {
        ksort($valor);

        return array_map(fn (mixed $item) => is_array($item) ? $this->ordenar($item) : $item, $valor);
    }

    /**
     * @return array{modelo: class-string<Model>, regras: class-string, gravador: class-string}
     */
    private function definicao(string $entidade): array
    {
        return self::CADASTROS[$entidade] ?? throw new InvalidArgumentException("Cadastro desconhecido: {$entidade}.");
    }
}
