<?php

namespace Database\Factories;

use App\Models\Equipamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Equipamento>
 */
class EquipamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => fake()->unique()->words(2, true),
            'descricao' => null,
            'quantidade' => fake()->numberBetween(1, 5),
            'local_guarda' => 'Armário da sacristia',
            'manutencao_ate' => null,
        ];
    }

    /**
     * Equipamento em manutenção: não aparece para reservar e conta como zero disponível.
     */
    public function emManutencao(): static
    {
        return $this->state(fn (array $attributes) => [
            'manutencao_ate' => Carbon::now()->addWeeks(2)->toDateString(),
        ]);
    }
}
