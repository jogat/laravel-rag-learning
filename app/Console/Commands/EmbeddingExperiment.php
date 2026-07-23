<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:embedding-experiment')]
#[Description('Command description')]
class EmbeddingExperiment extends Command
{
    protected string $url = "http://localhost:1234/v1/embeddings";
    protected string $model = "text-embedding-bge-m3";

    protected array $documentos = [
        'A' => 'El perro corre feliz por el parque',
        'B' => 'Un can trota alegre en el jardin',
        'C' => 'La receta lleva harina, huevos y azucar',
    ];

    protected string $pregunta = 'donde juega el perrito?';

    protected array $embeddingDocs = [];
    protected ?array $embeddingPregunta = null;
    protected array $resultados = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->generateEmbeddingDocs();
        $this->generateEmbeddingPregunta();
        $this->comparePreguntaConCadaDocumento();
        $this->muestraResultados();

        return self::SUCCESS;
    }

    protected function muestraResultados(): void
    {
        usort($this->resultados, fn ($a, $b) => $b['similitud'] <=> $a['similitud']);

        $this->table(
            ['Doc', 'Texto', 'Similitud'],
            array_map(fn ($r) => [
                $r['doc'],
                $r['texto'],
                number_format($r['similitud'], 4),
            ], $this->resultados)
        );

        $this->newLine();
        $this->info('Observa: aunque la pregunta no comparte palabras con A o B,');
        $this->info('deberian salir arriba, y la receta (C) hasta abajo.');
        $this->info('Eso, exactamente eso, es lo que hace posible el RAG.');
    }

    protected function comparePreguntaConCadaDocumento(): void
    {
        $this->newLine();
        $this->info("Pregunta: \"{$this->pregunta}\"");
        $this->line('Que tan parecida es a cada documento (1.0 = identico):');

        foreach ($this->embeddingDocs as $clave => $vectorDoc) {
            // La comparacion correcta: PREGUNTA contra el documento de esta vuelta.
            $similitud = $this->cosineSimilarity($this->embeddingPregunta, $vectorDoc);

            $this->resultados[] = [
                'doc'       => $clave,
                'texto'     => $this->documentos[$clave],
                'similitud' => $similitud,
            ];
        }
    }

    protected function generateEmbeddingPregunta()
    {
        $this->embeddingPregunta = $this->embed("search_query: {$this->pregunta}");

        if ($this->embeddingPregunta === null) {
            dd('el error ya se mostro dentro de embed()');
        }
    }

    protected function generateEmbeddingDocs(): void
    {
        $this->info('Generando embeddings... (nomic debe estar cargado en LM Studio)');
        $this->newLine();

        foreach ($this->documentos as $key => $texto) {
            // IMPORTANTE: nomic exige el prefijo "search_document:" para los documentos.
            $vector = $this->embed("search_document: {$texto}");

            if ($vector === null) {
                dd('el error ya se mostro dentro de embed()');
            }

            $this->embeddingDocs[$key] = $vector;

            $muestra = implode(', ', array_map(
                fn ($n) => number_format($n, 3),
                array_slice($vector, 0, 5)
            ));
            $this->line("  {$key}: [{$muestra}, ...] (" . count($vector) . " numeros en total)");
        }

        $this->newLine();
    }

    protected function embed(string $texto): ?array
    {
        $respuesta = Http::acceptJson()
            ->post($this->url, [
                'model' => $this->model,
                'input' => $texto,
            ]);

        if ($respuesta->failed()) {
            $this->error('La peticion fallo. Esta cargado el modelo de embeddings en LM Studio?');
            $this->line($respuesta->body());
            return null;
        }

        $vector = data_get($respuesta->json(), 'data.0.embedding');

        if (!is_array($vector)) {
            $this->error('No encontre el embedding en la respuesta:');
            $this->line($respuesta->body());
            return null;
        }

        return $vector;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = $magA = $magB = 0.0;

        foreach ($a as $i => $valA) {
            $dot  += $valA * $b[$i];
            $magA += $valA ** 2;
            $magB += $b[$i] ** 2;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
