<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Services\BusinessAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

#[Signature('app:benchmark-assistant {question} {--followup=} {--project=demo-es} {--as=test@example.com} {--label=}')]
#[Description('Measure live assistant requests in an isolated A/B database and roll back conversation writes.')]
class BenchmarkAssistant extends Command
{
    /** @var list<array<string, mixed>> */
    private array $requests = [];

    private float $requestStartedAt = 0;

    public function handle(BusinessAssistant $assistant): int
    {
        if (! str_starts_with((string) DB::connection()->getDatabaseName(), 'rag_ab_20261009_')) {
            $this->error('This benchmark requires a dedicated rag_ab_20261009_* database.');

            return self::FAILURE;
        }

        config(['ai.conversations.generate_title' => false]);

        $project = Project::where('slug', $this->option('project'))->firstOrFail();
        $user = User::where('email', $this->option('as'))->firstOrFail();

        Event::listen(RequestSending::class, function (RequestSending $event): void {
            if (str_contains($event->request->url(), '/api/')) {
                $this->requestStartedAt = microtime(true);
            }
        });
        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            $url = $event->request->url();

            if (! str_contains($url, '/api/')) {
                return;
            }

            $body = $event->request->data();
            $result = $event->response->json();
            $this->requests[] = [
                'endpoint' => parse_url($url, PHP_URL_PATH),
                'model' => $body['model'] ?? null,
                'think' => $body['think'] ?? null,
                'options' => $body['options'] ?? [],
                'wall_ms' => round((microtime(true) - $this->requestStartedAt) * 1000, 1),
                'load_ms' => round(($result['load_duration'] ?? 0) / 1000000, 1),
                'prompt_ms' => round(($result['prompt_eval_duration'] ?? 0) / 1000000, 1),
                'decode_ms' => round(($result['eval_duration'] ?? 0) / 1000000, 1),
                'input_tokens' => $result['prompt_eval_count'] ?? null,
                'output_tokens' => $result['eval_count'] ?? null,
                'tool_calls' => $result['message']['tool_calls'] ?? [],
                'text' => $result['message']['content'] ?? null,
                'status' => $event->response->status(),
                'finish_reason' => $result['done_reason'] ?? null,
            ];
        });

        DB::beginTransaction();
        DB::table('agent_conversation_messages')->delete();
        DB::table('agent_conversations')->delete();
        $turns = [];
        $questions = [$this->argument('question')];

        if ($this->option('followup')) {
            $questions[] = $this->option('followup');
        }

        try {
            foreach ($questions as $question) {
                $this->requests = [];
                $startedAt = microtime(true);
                $result = $assistant->ask($project, $user, $question);
                $turns[] = [
                    'question' => $question,
                    'wall_ms' => round((microtime(true) - $startedAt) * 1000, 1),
                    'generations' => count(array_filter($this->requests, fn (array $request): bool => $request['endpoint'] === '/api/chat')),
                    'embeddings' => count(array_filter($this->requests, fn (array $request): bool => $request['endpoint'] === '/api/embed')),
                    'result' => $result,
                    'requests' => $this->requests,
                ];
            }
        } catch (Throwable $exception) {
            $this->line(json_encode([
                'label' => $this->option('label'),
                'error' => $exception->getMessage(),
                'turns' => $turns,
                'requests' => $this->requests,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::FAILURE;
        } finally {
            DB::rollBack();
        }

        $this->line(json_encode([
            'label' => $this->option('label'),
            'model' => config('ai.providers.ollama.models.text.default'),
            'turns' => $turns,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
