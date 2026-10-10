<?php

namespace Database\Factories;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
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
            'coordenacao' => null,
            'contato' => null,
            'observacao' => null,
            'ativo' => true,
        ];
    }
}
