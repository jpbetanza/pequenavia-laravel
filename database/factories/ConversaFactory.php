<?php

namespace Database\Factories;

use App\Models\Conversa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversa>
 */
class ConversaFactory extends Factory
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
            'titulo' => 'Salão no sábado',
            'ultima_mensagem_em' => null,
        ];
    }
}
