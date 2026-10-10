<?php

use App\Ai\Agents\BusinessAgent;
use App\Ai\Agents\IntentClassifier;
use App\Enums\LanguagesEnum;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Models\Conversation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

it('returns the agent reply and stores the conversation under the project', function () {
    Embeddings::fake();
    $user = User::factory()->create();
    $project = Project::factory()->create(['slug' => 'demo-es', 'language' => LanguagesEnum::Spanish]);
    IntentClassifier::fake([['intent' => 'product']])->preventStrayPrompts();
    BusinessAgent::fake(['Abrimos de 9 a 18.'])->preventStrayPrompts();

    $response = actingAs($user)
        ->postJson('/api/chat', ['project' => 'demo-es', 'message' => '¿Cuál es el horario?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Abrimos de 9 a 18.')
        ->assertJsonMissingPath('debug');

    BusinessAgent::assertPrompted('¿Cuál es el horario?');
    assertDatabaseHas(Conversation::class, [
        'id' => $response->json('conversation_id'),
        'participant_id' => $user->id,
        'project_id' => $project->id,
    ]);
});

it('answers out-of-scope questions with the project language refusal without calling the agent', function () {
    Project::factory()->create(['slug' => 'demo-fr', 'language' => LanguagesEnum::French]);
    IntentClassifier::fake([['intent' => 'out_of_scope']])->preventStrayPrompts();
    BusinessAgent::fake()->preventStrayPrompts();

    actingAs(User::factory()->create())
        ->postJson('/api/chat', ['project' => 'demo-fr', 'message' => 'Write me a poem'])
        ->assertOk()
        ->assertExactJson([
            'reply' => 'Je peux uniquement vous aider avec des questions sur notre entreprise et vos commandes.',
            'conversation_id' => null,
        ]);

    BusinessAgent::assertNeverPrompted();
});

it('returns 422 when the project and message are missing', function () {
    actingAs(User::factory()->create())
        ->postJson('/api/chat', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'project' => 'The project field is required.',
            'message' => 'The message field is required.',
        ]);
});

it('returns 422 when the project does not exist', function () {
    actingAs(User::factory()->create())
        ->postJson('/api/chat', ['project' => 'unknown', 'message' => 'Hola'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project' => 'The selected project is invalid.']);
});

it('returns 422 when the message is longer than 2000 characters', function () {
    Project::factory()->create(['slug' => 'demo-en']);

    actingAs(User::factory()->create())
        ->postJson('/api/chat', ['project' => 'demo-en', 'message' => str_repeat('a', 2001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message' => 'The message field must not be greater than 2000 characters.']);
});

it('returns 429 after twenty messages in a minute', function () {
    $user = User::factory()->create();
    Project::factory()->create(['slug' => 'demo-en', 'language' => LanguagesEnum::English]);
    IntentClassifier::fake(fn () => ['intent' => 'out_of_scope']);

    foreach (range(1, 20) as $attempt) {
        actingAs($user)->postJson('/api/chat', ['project' => 'demo-en', 'message' => 'Hi'])->assertOk();
    }

    actingAs($user)->postJson('/api/chat', ['project' => 'demo-en', 'message' => 'Hi'])->assertTooManyRequests();
});

it('returns 401 for a guest', function () {
    postJson('/api/chat', ['project' => 'demo-en', 'message' => 'Hi'])->assertUnauthorized();
});
