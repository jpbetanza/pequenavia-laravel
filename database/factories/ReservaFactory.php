<?php

namespace Database\Factories;

use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
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
            'espaco_id' => fn (array $attributes) => Espaco::factory()->create(['user_id' => $attributes['user_id']])->id,
            'grupo_id' => null,
            'titulo' => 'Encontro de noivos',
            'data' => Carbon::now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'inicio' => '09:00',
            'fim' => '12:00',
            'pessoas' => null,
            'observacoes' => null,
            'serie_id' => null,
            'solicitacao_id' => null,
        ];
    }
}
