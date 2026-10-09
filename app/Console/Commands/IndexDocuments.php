<?php

namespace App\Console\Commands;

use App\Enums\LanguagesEnum;
use App\Jobs\IndexDocument;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\AiException;
use Throwable;

#[Signature('app:index-documents {--path=database/seeders/data/docs.csv : CSV con columnas language,content} {--project= : Slug de un solo proyecto a reindexar}')]
#[Description('Divide docs.csv en fragmentos, genera sus embeddings y reemplaza los documentos de cada proyecto en Postgres.')]
class IndexDocuments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = base_path((string) $this->option('path'));

        $rowsByLanguage = $this->readCsv($path);

        if ($rowsByLanguage === null || ! $this->ollamaIsReady()) {
            return self::FAILURE;
        }

        $projects = Project::query()
            ->when($this->option('project'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->get();

        if ($projects->isEmpty()) {
            $this->error('No hay proyectos que indexar. Ejecuta `php artisan migrate --seed` primero.');

            return self::FAILURE;
        }

        foreach ($projects as $project) {
            // Toda la fuente de un idioma es UN documento: unimos las filas con saltos de parrafo
            // para que el ChunkingService la divida con chunk_index secuencial (0..N).
            $sourceText = implode("\n\n", $rowsByLanguage[$project->language->shortName()] ?? []);

            if ($sourceText === '') {
                Document::where('project_id', $project->id)->delete();
                $this->warn("{$project->slug}: sin filas '{$project->language->shortName()}' en el CSV; se eliminaron sus documentos.");

                continue;
            }

            try {
                IndexDocument::dispatchSync($project->id, $sourceText, basename($path));
            } catch (Throwable $exception) {
                $this->error("{$project->slug}: ".$this->explain($exception));

                return self::FAILURE;
            }

            $this->info("{$project->slug}: ".Document::where('project_id', $project->id)->count().' fragmentos indexados.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>|null Contenido agrupado por codigo de idioma, o null si el CSV no es valido.
     */
    protected function readCsv(string $path): ?array
    {
        if (! is_readable($path) || is_dir($path)) {
            $this->error("No se puede leer el CSV: {$path}");

            return null;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header !== ['language', 'content']) {
            fclose($handle);
            $this->error('El CSV debe tener exactamente las columnas `language,content`.');

            return null;
        }

        $known = array_map(fn (LanguagesEnum $language) => $language->shortName(), LanguagesEnum::cases());
        $rows = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (count($row) !== 2 || ! in_array($row[0], $known, true) || trim($row[1]) === '') {
                $this->warn("Linea {$line} ignorada: se esperaba un idioma ({$this->knownList($known)}) y un contenido.");

                continue;
            }

            $rows[$row[0]][] = $row[1];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Comprueba que Ollama responde y que el modelo de embeddings esta descargado.
     */
    protected function ollamaIsReady(): bool
    {
        $url = rtrim((string) config('ai.providers.ollama.url'), '/');
        $model = (string) config('ai.providers.ollama.models.embeddings.default');

        try {
            $models = Http::timeout(5)->get("{$url}/api/tags")->throw()->json('models', []);
        } catch (ConnectionException) {
            $this->error("Ollama no responde en {$url}. Inicialo con `ollama serve`.");

            return false;
        } catch (Throwable $exception) {
            $this->error("Ollama respondio con un error en {$url}: {$exception->getMessage()}");

            return false;
        }

        $installed = array_map(fn (array $entry) => explode(':', (string) $entry['name'])[0], $models);

        if (! in_array(explode(':', $model)[0], $installed, true)) {
            $this->error("Falta el modelo de embeddings. Ejecuta `ollama pull {$model}`.");

            return false;
        }

        return true;
    }

    protected function explain(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ConnectionException => 'se perdio la conexion con Ollama. Revisa que siga en ejecucion.',
            $exception instanceof AiException => 'fallo el proveedor de IA: '.$exception->getMessage(),
            default => $exception->getMessage(),
        };
    }

    /**
     * @param  list<string>  $known
     */
    private function knownList(array $known): string
    {
        return implode(', ', $known);
    }
}
