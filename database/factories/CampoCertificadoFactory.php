<?php

namespace Database\Factories;

use App\Models\CampoCertificado;
use App\Models\ModeloCertificado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampoCertificado>
 */
class CampoCertificadoFactory extends Factory
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
            'nome' => 'Campo '.fake()->unique()->numberBetween(1, 1_000_000),
            'label' => null,
            'x' => 100,
            'y' => 100,
            'cor' => '#000000',
            'tamanho_fonte' => 32,
            'fonte_familia' => 'Georgia',
            'peso_fonte' => 600,
            'espacamento_letras' => 0,
            'centralizado' => false,
        ];
    }
}
