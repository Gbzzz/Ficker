<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'description' => 'Salário',
            'date' => date("Y-m-d"),
            'type_id' => random_int(1,2),
            'value' => random_int(100, 1000),
            'category_id' => random_int(1,4),
        ];
    }
}
