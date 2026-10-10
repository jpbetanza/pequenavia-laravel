<?php

namespace Database\Factories;

use App\Models\Espaco;
use App\Models\EspacoHorario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EspacoHorario>
 */
class EspacoHorarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'espaco_id' => Espaco::factory(),
            'dia_semana' => 1,
            'abre' => '08:00',
            'fecha' => '18:00',
        ];
    }
}
