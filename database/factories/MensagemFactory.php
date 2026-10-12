<?php

namespace Database\Factories;

use App\Enums\PapelDaMensagem;
use App\Models\Conversa;
use App\Models\Mensagem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mensagem>
 */
class MensagemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversa_id' => Conversa::factory(),
            'papel' => PapelDaMensagem::Usuario,
            'conteudo' => 'O salão está livre?',
            'cartoes' => [],
            'chamadas' => null,
            'ferramenta_id' => null,
            'tokens_entrada' => null,
            'tokens_saida' => null,
            'erro' => false,
        ];
    }
}
