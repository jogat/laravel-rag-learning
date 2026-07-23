<?php

namespace App\Console\Commands;

use App\Enums\LanguagesEnum;
use App\Jobs\IndexDocument;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;

#[Signature('app:index-demo')]
#[Description('Command description')]
class IndexDemo extends Command
{
    /**
     * Execute the console command.
     */

    public function handle(): void
    {
        Document::truncate();

        foreach (Project::all() as $project) {
            // Toda la fuente de un idioma es UN documento: unimos las filas con
            // saltos de parrafo para que el ChunkingService la divida en fragmentos
            // con chunk_index secuencial (0..N) a lo largo de toda la fuente.
            $sourceText = $this->getDocsByLanguage($project->language)
                ->pluck('content')
                ->implode("\n\n");

            if ($sourceText === '') {
                continue;
            }

            IndexDocument::dispatch($project->id, $sourceText, 'docs.csv');
        }
    }

    protected function getDocsByLanguage(LanguagesEnum $language): LazyCollection
    {
        $filePath = storage_path('app/private/docs.csv');

        return LazyCollection::make(function () use ($filePath, $language) {
            $handle = fopen($filePath, 'r');
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                // Pair row data with header names if desired
                $row = array_combine($header, $row);

                if ($row['language'] === $language->shortName()) {
                    yield $row;
                }
            }

            fclose($handle);
        });
    }
}
