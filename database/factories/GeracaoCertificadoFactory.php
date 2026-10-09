<?php

namespace Database\Factories;

use App\Models\GeracaoCertificado;
use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeracaoCertificado>
 */
class GeracaoCertificadoFactory extends Factory
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
            'modelo_id' => ModeloCertificado::factory(),
            'modelo_title' => 'Batismo',
            'campos_valores' => ['Campo 1' => 'Maria'],
        ];
    }
}
