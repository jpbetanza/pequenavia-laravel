<?php

namespace App\Models;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use Database\Factories\ClassificacaoDeConversaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Leitura de uma conversa: caso de uso, resultado, frustração, tema e resumo. A correção do gestor,
 * quando existe, se sobrepõe ao rótulo que o JEV deu.
 *
 * @property string $id
 * @property string $conversa_id
 * @property string $user_id
 * @property string|null $rodada_id
 * @property Carbon $mensagens_ate
 * @property string $caso_de_uso
 * @property float $caso_confianca
 * @property string $resultado
 * @property float $resultado_confianca
 * @property bool $abandonada
 * @property float $frustracao
 * @property float $frustracao_confianca
 * @property float $confusa
 * @property float $pedido_fora
 * @property float $erro_do_assistente
 * @property string|null $tema_id
 * @property string|null $tema_origem `jev` ou `haiku`
 * @property float|null $tema_confianca
 * @property bool $a_revisar
 * @property string|null $resumo
 * @property list<string> $ferramentas
 * @property int $acoes_propostas
 * @property int $acoes_confirmadas
 * @property int $mensagens
 * @property int $tokens_assistente_entrada
 * @property int $tokens_assistente_saida
 * @property string|null $modelo_jev
 * @property array{caso_de_uso?: string, resultado?: string, tema_id?: string|null}|null $correcao
 * @property Carbon $classificada_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('classificacoes_de_conversa')]
#[Fillable([
    'conversa_id', 'user_id', 'rodada_id', 'mensagens_ate', 'caso_de_uso', 'caso_confianca', 'resultado',
    'resultado_confianca', 'abandonada', 'frustracao', 'frustracao_confianca', 'confusa', 'pedido_fora',
    'erro_do_assistente', 'tema_id', 'tema_origem', 'tema_confianca', 'a_revisar', 'resumo', 'ferramentas',
    'acoes_propostas', 'acoes_confirmadas', 'mensagens', 'tokens_assistente_entrada', 'tokens_assistente_saida',
    'modelo_jev', 'correcao', 'classificada_em',
])]
class ClassificacaoDeConversa extends Model
{
    /** @use HasFactory<ClassificacaoDeConversaFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mensagens_ate' => 'datetime',
            'classificada_em' => 'datetime',
            'caso_confianca' => 'float',
            'resultado_confianca' => 'float',
            'abandonada' => 'boolean',
            'frustracao' => 'float',
            'frustracao_confianca' => 'float',
            'confusa' => 'float',
            'pedido_fora' => 'float',
            'erro_do_assistente' => 'float',
            'tema_confianca' => 'float',
            'a_revisar' => 'boolean',
            'ferramentas' => 'array',
            'correcao' => 'array',
        ];
    }

    /**
     * O caso de uso que vale: a correção do gestor, se houver, senão o do JEV.
     */
    public function casoEfetivo(): CasoDeUso
    {
        return CasoDeUso::from($this->correcao['caso_de_uso'] ?? $this->caso_de_uso);
    }

    /**
     * O resultado que vale: a correção do gestor, se houver, senão o do JEV.
     */
    public function resultadoEfetivo(): ResultadoDaConversa
    {
        return ResultadoDaConversa::from($this->correcao['resultado'] ?? $this->resultado);
    }

    /**
     * O tema que vale. Uma correção com tema nulo quer dizer "nenhum tema", então usa array_key_exists.
     */
    public function temaEfetivoId(): ?string
    {
        return array_key_exists('tema_id', $this->correcao ?? [])
            ? $this->correcao['tema_id']
            : $this->tema_id;
    }

    /** @return BelongsTo<Conversa, $this> */
    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TemaDePedido, $this> */
    public function tema(): BelongsTo
    {
        return $this->belongsTo(TemaDePedido::class, 'tema_id');
    }

    /** @return BelongsTo<RodadaDeClassificacao, $this> */
    public function rodada(): BelongsTo
    {
        return $this->belongsTo(RodadaDeClassificacao::class, 'rodada_id');
    }
}
