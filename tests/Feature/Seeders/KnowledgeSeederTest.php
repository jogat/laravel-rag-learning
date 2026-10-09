<?php

use Database\Seeders\KnowledgeSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;

use function Pest\Laravel\seed;

it('does nothing under tests so seeding never reaches Ollama', function () {
    Http::preventStrayRequests();
    Embeddings::fake()->preventStrayEmbeddings();

    seed(KnowledgeSeeder::class);

    Http::assertNothingSent();
    Embeddings::assertNotGenerated(fn () => true);
});
