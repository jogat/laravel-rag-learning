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
        $names = [
            LanguagesEnum::English->value => ['name' => 'Demo Store (EN)', 'description' => 'English-language demo business.'],
            LanguagesEnum::Spanish->value => ['name' => 'Tienda Demo (ES)', 'description' => 'Negocio de demostración en español.'],
            LanguagesEnum::French->value => ['name' => 'Boutique Démo (FR)', 'description' => 'Entreprise de démonstration en français.'],
        ];

        foreach (LanguagesEnum::cases() as $language) {
            Project::factory()->create([
                ...$names[$language->value],
                'language' => $language,
                'slug' => 'demo-'.$language->shortName(),
            ]);
        }
    }
}
