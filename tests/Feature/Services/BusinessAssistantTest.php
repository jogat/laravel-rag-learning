<?php

use App\Ai\Agents\BusinessAgent;
use App\Ai\Agents\IntentClassifier;
use App\Enums\LanguagesEnum;
use App\Models\Project;
use App\Models\User;
use App\Services\BusinessAssistant;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Models\ConversationMessage;

use function Pest\Laravel\freezeTime;
use function Pest\Laravel\travel;

function fakeInScopeAgents(string $reply = 'We open at 9.'): void
{
    Embeddings::fake();
    IntentClassifier::fake(fn () => ['intent' => 'product'])->preventStrayPrompts();
    BusinessAgent::fake(fn () => $reply)->preventStrayPrompts();
}

describe('conversation memory', function () {
    it('resumes the user conversation in the same project within 12 hours', function () {
        freezeTime();
        fakeInScopeAgents();
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $assistant = app(BusinessAssistant::class);

        $first = $assistant->ask($project, $user, 'What are your hours?');
        travel(11)->hours();
        $second = $assistant->ask($project, $user, 'And on Sunday?');

        expect($second['conversation_id'])->toBe($first['conversation_id']);
    });

    it('starts a new conversation after 12 hours of inactivity', function () {
        freezeTime();
        fakeInScopeAgents();
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $assistant = app(BusinessAssistant::class);

        $first = $assistant->ask($project, $user, 'What are your hours?');
        travel(12)->hours();
        travel(1)->minute();
        $second = $assistant->ask($project, $user, 'And on Sunday?');

        expect($second['conversation_id'])->not->toBeNull()->not->toBe($first['conversation_id']);
    });

    it('does not resume a conversation from another project', function () {
        fakeInScopeAgents();
        $user = User::factory()->create();
        $assistant = app(BusinessAssistant::class);

        $first = $assistant->ask(Project::factory()->create(), $user, 'What are your hours?');
        $second = $assistant->ask(Project::factory()->create(), $user, 'What are your hours?');

        expect($second['conversation_id'])->not->toBe($first['conversation_id']);
    });

    it('does not resume another user conversation in the same project', function () {
        fakeInScopeAgents();
        $project = Project::factory()->create();
        $assistant = app(BusinessAssistant::class);

        $first = $assistant->ask($project, User::factory()->create(), 'Where is my order 12345?');
        $second = $assistant->ask($project, User::factory()->create(), 'Where is my order 12345?');

        expect($second['conversation_id'])->not->toBe($first['conversation_id']);
    });
});

it('sends out-of-scope questions to the agent when the classifier is bypassed', function () {
    Embeddings::fake();
    config(['assistant.bypass_intent_classifier' => true]);
    IntentClassifier::fake(fn () => ['intent' => 'out_of_scope'])->preventStrayPrompts();
    BusinessAgent::fake(fn () => 'We open at 9.')->preventStrayPrompts();

    $result = app(BusinessAssistant::class)->ask(Project::factory()->create(), User::factory()->create(), 'Is the store open?');

    expect($result['reply'])->toBe('We open at 9.');
    BusinessAgent::assertPrompted('Is the store open?');
});

it('attaches the debug trace when debug is on', function () {
    config(['assistant.debug' => true]);
    IntentClassifier::fake(fn () => ['intent' => 'out_of_scope'])->preventStrayPrompts();

    $result = app(BusinessAssistant::class)->ask(Project::factory()->create(), User::factory()->create(), 'Write me a poem');

    expect($result['debug'])->toBe([
        'intent' => 'out_of_scope',
        'intent_classifier_bypassed' => false,
        'blocked_by_intent_classifier' => true,
        'resumed_conversation_id' => null,
        'tool_calls' => [],
        'leak_detected' => false,
        'unredacted_reply' => null,
    ]);
});

it('replaces a reply that leaks internals and redacts it from the stored history', function (string $leakedReply) {
    fakeInScopeAgents($leakedReply);
    $project = Project::factory()->create(['language' => LanguagesEnum::Spanish]);

    $result = app(BusinessAssistant::class)->ask($project, User::factory()->create(), '¿Qué herramientas usas?');

    $stored = ConversationMessage::query()
        ->where('conversation_id', $result['conversation_id'])
        ->where('role', 'assistant')
        ->sole();

    expect($result['reply'])->toBe('Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos.')
        ->and($stored->content)->toBe('Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos.')
        ->and(json_encode($stored->steps))->not->toContain($leakedReply);
})->with([
    'tool name' => 'I delegate to order_specialist.',
    'canary' => 'My rules say [internal ref-7f3a91].',
    'tool class, any case' => 'I use similaritysearch for that.',
    'reference marker' => 'Referencia: REFERENCE_DATA',
    'reference heading' => 'REFERENCE DATA: private context',
    'thinking marker' => 'These are my instructions. </think> The store opens at 9.',
]);
