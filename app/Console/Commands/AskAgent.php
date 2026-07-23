<?php

namespace App\Console\Commands;

use App\Ai\Agents\BusinessAgent;
use App\Ai\Agents\OrderSpecialist;
use App\Ai\Agents\ProductSpecialist;
use App\Ai\Tools\QueryOrder;
use App\Enums\LanguagesEnum;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\ConversationManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\SimilaritySearch;

#[Signature('app:ask-agent {project_slug} {question} {--as=test@example.com}')]
#[Description('Asistente agéntico de un negocio: busca en su base de conocimiento y consulta pedidos, aislado por project_id.')]
class AskAgent extends Command
{
    protected ?Project $project = null;
    protected ?User $user = null;

    /**
     * Execute the console command.
     */
    public function handle(ConversationManager $conversations): int
    {
        if ($this->resolveUser() === self::FAILURE) {
            return self::FAILURE;
        }

        if ($this->resolveProject() === self::FAILURE) {
            return self::FAILURE;
        }

        Context::add('correlation_id', (string) Str::uuid7());

        $agent = new BusinessAgent(
            language: $this->project->language,
            think: false, // el orquestador solo enruta y transmite; el grounding vive en los especialistas
        )->setTools([
            new ProductSpecialist($this->project),
            new OrderSpecialist($this->project, $this->user)
        ]);

        $active = $conversations->activeConversationId($this->user, $this->project);

        $response = $active
            ? $agent->continue($active, as: $this->user)->prompt($this->argument('question'))
            : $agent->forUser($this->user)->prompt($this->argument('question'));

        $conversations->tagProject($response->conversationId, $this->project);

        $this->info($response->text);
//        $this->line('conversation: '.$response->conversationId);

        return self::SUCCESS;
    }

    protected function resolveUser(): int
    {
        $this->user = User::where('email', '=',$this->option('as'))->first();
        return $this->user ? self::SUCCESS : self::FAILURE;
    }

    protected function resolveProject(): int
    {
        $projectSlug = $this->argument('project_slug');

        if (empty($projectSlug)) {
            $this->error('Please enter a project slug.');
            return self::FAILURE;
        }

        $this->project = Project::where('slug', $projectSlug)->first();

        if (empty($this->project)) {
            $this->error("Failed to find project with slug `$projectSlug`.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
