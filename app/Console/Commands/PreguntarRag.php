<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:preguntar-rag {pregunta}')]
#[Description('Etapa 4: RAG sobre Postgres. Busca con pgvector y responde con Qwen.')]
class PreguntarRag extends Command
{
    protected string $embedUrl   = "http://localhost:1234/v1/embeddings";
    protected string $chatUrl    = "http://localhost:1234/v1/chat/completions";
    protected string $embedModel = "text-embedding-bge-m3";
    protected string $chatModel  = "qwen/qwen3-8b";
    protected int $topK = 2;
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pregunta = $this->argument('pregunta');

        // 1. Embedding de la pregunta (igual que siempre).
        $vectorPregunta = $this->embed($pregunta);
        if ($vectorPregunta === null) {
            $this->error('Fallo el embedding. Esta cargado bge-m3?');
            return self::FAILURE;
        }

        // 2. Recuperar contexto: aqui la BUSQUEDA la hace Postgres, no PHP.
        $contexto = $this->recuperarContexto($vectorPregunta, $pregunta);

        // 3. Generar respuesta con Qwen usando ese contexto.
        $respuesta = $this->generarRespuesta($pregunta, $contexto);
        if ($respuesta === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== RESPUESTA FINAL ===');
        $this->line($respuesta);

        return self::SUCCESS;
    }

    protected function recuperarContexto(array $vectorPregunta, string $pregunta): string
    {
        // pgvector necesita el vector en su formato de texto: [0.1,0.2,...]
        $vectorSql = '[' . implode(',', $vectorPregunta) . ']';

        // La consulta clave. El operador <=> calcula DISTANCIA coseno.
        // Menor distancia = mas parecido, por eso ordenamos ascendente.
        // Postgres usa el indice HNSW para no revisar todas las filas.
        $mejores = Document::query()
            ->select('contenido')
            ->selectRaw('embedding <=> ? AS distancia', [$vectorSql])
            ->orderBy('distancia') // ascendente: los mas cercanos primero
            ->limit($this->topK)
            ->get();

        $this->newLine();
        $this->info("Contexto recuperado para: \"{$pregunta}\"");
        foreach ($mejores as $doc) {
            // distancia mas baja = mas relevante
            $this->line('  [dist ' . number_format($doc->distancia, 3) . '] ' . $doc->contenido);
        }

        return $mejores
            ->map(fn ($doc) => '- ' . $doc->contenido)
            ->implode("\n");
    }

    protected function generarRespuesta(string $pregunta, string $contexto): ?string
    {
        $system = "Eres el asistente de un negocio. Responde usando UNICAMENTE la "
            . "informacion del contexto. Si la respuesta no esta en el contexto, di "
            . "honestamente que no tienes esa informacion. Responde breve y en espanol. /no_think";

        $respuesta = Http::acceptJson()->post($this->chatUrl, [
            'model' => $this->chatModel,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => "Contexto:\n{$contexto}\n\nPregunta: {$pregunta}"],
            ],
            'temperature' => 0.3,
        ]);

        if ($respuesta->failed()) {
            $this->error('Fallo el chat. Esta cargado Qwen?');
            $this->line($respuesta->body());
            return null;
        }

        $texto = data_get($respuesta->json(), 'choices.0.message.content');
        return $texto ? trim($texto) : null;
    }

    protected function embed(string $texto): ?array
    {
        $respuesta = Http::acceptJson()->post($this->embedUrl, [
            'model' => $this->embedModel,
            'input' => $texto,
        ]);

        if ($respuesta->failed()) {
            $this->line($respuesta->body());
            return null;
        }

        $vector = data_get($respuesta->json(), 'data.0.embedding');
        return is_array($vector) ? $vector : null;
    }
}
