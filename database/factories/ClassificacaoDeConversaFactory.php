<?php

namespace Database\Factories;

use App\Enums\CasoDeUso;
use App\Enums\ResultadoDaConversa;
use App\Models\ClassificacaoDeConversa;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassificacaoDeConversa>
 */
class ClassificacaoDeConversaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversa_id' => Conversa::factory(),
            'user_id' => User::factory(),
            'rodada_id' => null,
            'mensagens_ate' => now(),
            'caso_de_uso' => CasoDeUso::Reserva->value,
            'caso_confianca' => 0.9,
            'resultado' => ResultadoDaConversa::Resolvida->value,
            'resultado_confianca' => 0.85,
            'abandonada' => false,
            'frustracao' => 0.5,
            'frustracao_confianca' => 0.8,
            'confusa' => 0.1,
            'pedido_fora' => 0.1,
            'erro_do_assistente' => 0.0,
            'tema_id' => null,
            'tema_origem' => null,
            'tema_confianca' => null,
            'a_revisar' => false,
            'resumo' => null,
            'ferramentas' => [],
            'acoes_propostas' => 0,
            'acoes_confirmadas' => 0,
            'mensagens' => 4,
            'tokens_assistente_entrada' => 1200,
            'tokens_assistente_saida' => 300,
            'modelo_jev' => '~typesafe/jev-latest',
            'correcao' => null,
            'classificada_em' => now(),
        ];
    }
}
