<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\ChunkingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use RuntimeException;

class IndexDocument implements ShouldQueue
{
    use Queueable;

    /** El primer embedding carga bge-m3 en frio en Ollama; el timeout por defecto (30s) se queda corto. */
    private const int EMBEDDINGS_TIMEOUT_SECONDS = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $projectId,
        protected string $sourceText,
        protected string $sourceName) {}

    /**
     * Execute the job.
     *
     * Reindexar es idempotente: primero se generan los embeddings (la llamada de red) y solo entonces,
     * dentro de una transaccion, se reemplazan los fragmentos del proyecto. Si algo falla, el indice
     * anterior queda intacto.
     */
    public function handle(ChunkingService $chunkingService): void
    {
        $chunks = $chunkingService->chunk($this->sourceText);

        if ($chunks === []) {
            return;
        }

        // El SDK devuelve un vector por chunk, alineado por indice.
        $vectors = Embeddings::for($chunks)
            ->timeout(self::EMBEDDINGS_TIMEOUT_SECONDS)
            ->generate()
            ->embeddings;

        $dimensions = (int) config('ai.providers.ollama.models.embeddings.dimensions');

        foreach ($vectors as $vector) {
            if (count($vector) !== $dimensions) {
                throw new RuntimeException(
                    'El modelo de embeddings devolvio '.count($vector)." dimensiones y la columna espera {$dimensions}."
                );
            }
        }

        DB::transaction(function () use ($chunks, $vectors): void {
            Document::where('project_id', $this->projectId)->delete();

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
        });
    }
}
