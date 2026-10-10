<?php

namespace Database\Factories;

use App\Enums\StatusSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Solicitacao>
 */
class SolicitacaoFactory extends Factory
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
            'grupo_id' => null,
            'nome' => fake()->name(),
            'telefone' => fake()->numerify('119########'),
            'titulo' => 'Reunião de catequistas',
            'data' => Carbon::now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'inicio' => '19:00',
            'fim' => '21:00',
            'espaco_id' => null,
            'pessoas' => null,
            'observacoes' => null,
            'status' => StatusSolicitacao::Pendente,
            'motivo_recusa' => null,
            'reserva_id' => null,
        ];
    }

    /**
     * Pedido ainda não respondido pela secretaria.
     */
    public function pendente(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StatusSolicitacao::Pendente]);
    }
}
