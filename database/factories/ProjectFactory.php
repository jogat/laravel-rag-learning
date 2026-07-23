<?php

namespace Database\Factories;

use App\Enums\LanguagesEnum;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cases = LanguagesEnum::cases();
        $randomLanguage = $cases[array_rand($cases)];

        return [
            'name' => fake()->company(),
            'description' => fake()->text(),
            'language' => $randomLanguage->name,
            'slug' => fake()->slug(),
        ];
    }
}
