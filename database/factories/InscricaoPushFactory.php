<?php

namespace Database\Factories;

use App\Models\InscricaoPush;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InscricaoPush>
 */
class InscricaoPushFactory extends Factory
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
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->unique()->sha256(),
            'chave_p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'chave_auth' => 'tBHItJI5svbpez7KI4CCXg',
            'dispositivo' => 'Android · Chrome',
        ];
    }
}
