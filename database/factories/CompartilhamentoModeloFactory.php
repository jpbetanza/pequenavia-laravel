<?php

namespace Database\Factories;

use App\Models\CompartilhamentoModelo;
use App\Models\ModeloCertificado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompartilhamentoModelo>
 */
class CompartilhamentoModeloFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modelo_id' => ModeloCertificado::factory(),
            'email' => fake()->unique()->safeEmail(),
            'pode_editar' => false,
        ];
    }

    /**
     * Indicate that the recipient may edit the model.
     */
    public function editor(): static
    {
        return $this->state(fn (array $attributes) => ['pode_editar' => true]);
    }
}
