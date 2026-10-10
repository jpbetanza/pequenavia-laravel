<?php

namespace Database\Factories;

use App\Models\Espaco;
use App\Models\EspacoBloqueio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<EspacoBloqueio>
 */
class EspacoBloqueioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = Carbon::now()->addDays(3);

        return [
            'espaco_id' => Espaco::factory(),
            'inicio' => $inicio->toDateString(),
            'fim' => $inicio->copy()->addDays(2)->toDateString(),
            'motivo' => 'Reforma',
        ];
    }
}
