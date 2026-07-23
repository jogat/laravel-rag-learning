<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\ChunkingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Embeddings;

class IndexDocument implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $projectId,
        protected string $sourceText,
        protected string $sourceName)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(ChunkingService $chunkingService): void
    {
        $chunks = $chunkingService->chunk($this->sourceText);

        if ($chunks === []) {
            return;
        }

        // El SDK devuelve un vector por chunk, alineado por indice.
        $vectors = Embeddings::for($chunks)->generate()->embeddings;

        // Un Document POR fragmento: mismo project_id y fuente, distinto chunk_index.
        foreach ($chunks as $index => $chunk) {
            Document::create([
                'project_id' => $this->projectId,
                'content' => $chunk,
                'source' => $this->sourceName,
                'chunk_index' => $index,
                'embedding' => $vectors[$index],
            ]);
        }
    }
}
