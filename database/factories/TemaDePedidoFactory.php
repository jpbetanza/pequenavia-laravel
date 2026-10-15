<?php

namespace Database\Factories;

use App\Enums\SituacaoDoTema;
use App\Models\TemaDePedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemaDePedido>
 */
class TemaDePedidoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Tema '.fake()->unique()->numberBetween(1, 999999),
            'descricao' => 'A secretaria pediu algo que o app ainda não faz.',
            'situacao' => SituacaoDoTema::Novo,
            'criado_por' => 'haiku',
            'juntado_em_id' => null,
        ];
    }
}
