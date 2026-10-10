<?php

namespace Database\Factories;

use App\Enums\CorDoEspaco;
use App\Models\Espaco;
use App\Models\EspacoHorario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Espaco>
 */
class EspacoFactory extends Factory
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
            'capacidade' => fake()->numberBetween(10, 200),
            'localizacao' => 'Térreo',
            'cor' => fake()->randomElement(CorDoEspaco::cases()),
            'recursos_fixos' => [],
            'aceita_solicitacoes' => true,
            'antecedencia_min_horas' => 0,
            'duracao_max_min' => null,
            'intervalo_min' => 0,
            'ativo' => true,
        ];
    }

    /**
     * Abre de segunda a sexta das 08:00 às 22:00 e aos sábados das 08:00 às 18:00. Domingo fica fechado.
     */
    public function comHorarioComercial(): static
    {
        return $this->afterCreating(function (Espaco $espaco): void {
            foreach ([1, 2, 3, 4, 5] as $diaSemana) {
                EspacoHorario::factory()->for($espaco, 'espaco')->create([
                    'dia_semana' => $diaSemana,
                    'abre' => '08:00',
                    'fecha' => '22:00',
                ]);
            }

            EspacoHorario::factory()->for($espaco, 'espaco')->create([
                'dia_semana' => 6,
                'abre' => '08:00',
                'fecha' => '18:00',
            ]);
        });
    }

    /**
     * Espaço desativado: não aparece para reservar nem para solicitar.
     */
    public function inativo(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }
}
