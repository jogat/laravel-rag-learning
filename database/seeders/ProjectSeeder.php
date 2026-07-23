<?php

namespace Database\Seeders;

use App\Enums\LanguagesEnum;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (LanguagesEnum::cases() as $language) {
            Project::factory()->create([
                'language' => $language,
                'slug' => 'demo-' .  $language->shortName()
            ]);
        }
    }
}
