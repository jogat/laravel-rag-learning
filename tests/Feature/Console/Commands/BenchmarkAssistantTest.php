<?php

use App\Ai\Agents\BusinessAgent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

use function Pest\Laravel\artisan;

/** @param array<string, string> $arguments */
function callBenchmarkWithIsolatedDatabaseLabel(array $arguments): int
{
    $connection = DB::connection();
    $databaseName = $connection->getDatabaseName();
    $connection->setDatabaseName('rag_ab_20261009_test_fixture');

    try {
        return Artisan::call('app:benchmark-assistant', $arguments);
    } finally {
        $connection->setDatabaseName($databaseName);
    }
}

it('refuses to benchmark against a database outside the isolated experiment', function () {
    Http::preventStrayRequests();
    $connection = DB::connection();
    $databaseName = $connection->getDatabaseName();
    $connection->setDatabaseName('rag_laravel');

    try {
        artisan('app:benchmark-assistant', ['question' => 'Hola'])
            ->expectsOutputToContain('requires a dedicated rag_ab_20261009_* database')
            ->assertFailed();
    } finally {
        $connection->setDatabaseName($databaseName);
    }

    Http::assertNothingSent();
});

it('reports actual generation metrics and restores existing history after a two-turn benchmark', function () {
    Http::preventStrayRequests();
    Http::fake(['*/api/chat' => fn (Request $request) => Http::response([
        'model' => 'test-model',
        'message' => ['role' => 'assistant', 'content' => isset($request['format']) ? '{"intent":"greeting"}' : 'Hola.'],
        'done' => true,
        'done_reason' => 'stop',
        'load_duration' => 1000000,
        'prompt_eval_duration' => 2000000,
        'eval_duration' => 3000000,
        'prompt_eval_count' => 8,
        'eval_count' => 6,
    ])]);
    config(['ai.conversations.generate_title' => false]);
    $project = Project::factory()->create();
    $user = User::factory()->create();
    $existing = (new BusinessAgent(intent: 'greeting'))->forUser($user)->prompt('Previous customer message');
    $messages = ConversationMessage::where('conversation_id', $existing->conversationId)->get()->toArray();

    $exitCode = callBenchmarkWithIsolatedDatabaseLabel([
        'question' => 'Hola', '--followup' => 'Gracias', '--project' => $project->slug, '--as' => $user->email, '--label' => 'metrics-test',
    ]);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($exitCode)->toBe(0);
    expect($report['label'])->toBe('metrics-test');
    expect($report['turns'])->toHaveCount(2);
    expect($report['turns'][0]['generations'])->toBe(2);
    expect($report['turns'][0]['embeddings'])->toBe(0);
    expect($report['turns'][0]['requests'][0])->toMatchArray([
        'endpoint' => '/api/chat', 'status' => 200, 'input_tokens' => 8, 'output_tokens' => 6,
        'load_ms' => 1.0, 'prompt_ms' => 2.0, 'decode_ms' => 3.0,
    ]);
    expect(Conversation::pluck('id')->all())->toBe([$existing->conversationId]);
    expect(ConversationMessage::where('conversation_id', $existing->conversationId)->get()->toArray())->toBe($messages);
    Http::assertSentCount(5);
});

it('restores existing history and reports a failed inference when the answer provider fails', function () {
    Http::preventStrayRequests();
    Http::fake(['*/api/chat' => function (Request $request) {
        $messages = $request['messages'];
        if (! isset($request['format']) && $messages[array_key_last($messages)]['content'] === 'fallo') {
            return Http::response(['error' => 'simulated failure'], 500);
        }

        return Http::response([
            'model' => 'test-model',
            'message' => ['role' => 'assistant', 'content' => isset($request['format']) ? '{"intent":"greeting"}' : 'Hola.'],
            'done' => true, 'done_reason' => 'stop',
        ]);
    }]);
    config(['ai.conversations.generate_title' => false]);
    $project = Project::factory()->create();
    $user = User::factory()->create();
    $existing = (new BusinessAgent(intent: 'greeting'))->forUser($user)->prompt('Previous customer message');
    $messages = ConversationMessage::where('conversation_id', $existing->conversationId)->get()->toArray();

    $exitCode = callBenchmarkWithIsolatedDatabaseLabel(['question' => 'fallo', '--project' => $project->slug, '--as' => $user->email]);

    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($exitCode)->toBe(1);
    expect($report['error'])->toContain('simulated failure');
    expect($report['requests'][1]['status'])->toBe(500);
    expect(Conversation::pluck('id')->all())->toBe([$existing->conversationId]);
    expect(ConversationMessage::where('conversation_id', $existing->conversationId)->get()->toArray())->toBe($messages);
    Http::assertSentCount(3);
});
