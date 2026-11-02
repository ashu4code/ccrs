<?php

namespace Database\Factories;

use App\Models\Consumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumer>
 */
class ConsumerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // Consumers authenticate by mobile OTP first; the password is created afterwards.
            'consumer_number' => fake()->unique()->numerify('##########'),
            'mobile' => fake()->numerify('98########'),
            'address' => fake()->address(),
            'load' => fake()->randomElement(['50 kW', '100 kW', '150 kW', '200 kW']),
            'meter_number' => fake()->unique()->bothify('MT-####-####'),
            'tariff' => fake()->randomElement(['HT-I', 'HT-II', 'HT-III']),
            'region' => 'North',
            'discom_id' => null,
            'circle_id' => null,
            'division_id' => null,
            'subdivision_id' => null,
            'section_id' => null,
            'password' => null,
        ];
    }
}
