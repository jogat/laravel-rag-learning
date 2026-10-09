<?php

use App\Enums\LanguagesEnum;
use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('lists projects ordered by name with only their public fields', function () {
    Project::factory()->create(['name' => 'Zeta Shop', 'slug' => 'zeta', 'language' => LanguagesEnum::French]);
    Project::factory()->create(['name' => 'Alpha Shop', 'slug' => 'alpha', 'language' => LanguagesEnum::Spanish]);

    actingAs(User::factory()->create())
        ->getJson('/api/projects')
        ->assertOk()
        ->assertExactJson([
            ['slug' => 'alpha', 'name' => 'Alpha Shop', 'language' => 'Español'],
            ['slug' => 'zeta', 'name' => 'Zeta Shop', 'language' => 'Français'],
        ]);
});

it('returns 401 for a guest', function () {
    getJson('/api/projects')->assertUnauthorized();
});
