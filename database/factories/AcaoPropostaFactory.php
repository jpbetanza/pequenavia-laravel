<?php

namespace Database\Factories;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcaoProposta>
 */
class AcaoPropostaFactory extends Factory
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
            'tipo' => 'criar_reserva',
            'dados' => [],
            'status' => StatusDaAcao::Proposta,
            'resultado_id' => null,
            'confirmada_em' => null,
        ];
    }
}
