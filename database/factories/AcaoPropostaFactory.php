<?php

namespace Database\Factories;

use App\Enums\StatusDaAcao;
use App\Models\AcaoProposta;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'dados' => [
                'titulo' => 'Catequese',
                'espaco_id' => (string) Str::uuid(),
                'grupo_id' => null,
                'data' => '2026-10-16',
                'inicio' => '14:00',
                'fim' => '17:00',
                'pessoas' => null,
                'observacoes' => null,
                'equipamentos' => [],
            ],
            'status' => StatusDaAcao::Proposta,
            'resultado_id' => null,
            'confirmada_em' => null,
        ];
    }
}
