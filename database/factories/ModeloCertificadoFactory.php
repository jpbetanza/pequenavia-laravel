<?php

namespace Database\Factories;

use App\Models\ModeloCertificado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModeloCertificado>
 */
class ModeloCertificadoFactory extends Factory
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
            'title' => 'Batismo',
            'description' => null,
            'image_path' => 'modelos/'.Str::uuid().'.png',
        ];
    }
}
