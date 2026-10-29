<?php

namespace Database\Factories;

use App\Models\NonConformity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonConformity>
 */
class NonConformityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plant = fake()->randomElement(array_keys(config('smk3.plants')));

        return [
            'tanggal' => fake()->dateTimeBetween('-6 months'),
            'pabrik' => $plant,
            'departemen' => fake()->randomElement(array_keys(NonConformity::departmentsFor($plant))),
            'deskripsi_temuan' => fake()->sentence(10),
            'no_klausul' => fake()->numerify('#.#.#'),
            'kategori' => fake()->randomElement(config('smk3.categories')),
            'pic' => fake()->name(),
            'status' => fake()->boolean(40),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => false]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => true]);
    }
}
