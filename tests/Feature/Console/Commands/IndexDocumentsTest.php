<?php

use App\Enums\LanguagesEnum;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\EmbeddingsResponse;

use function Pest\Laravel\artisan;

function fakeOllamaWithModels(array $names = ['bge-m3:latest', 'qwen3:8b']): void
{
    Http::preventStrayRequests();
    Http::fake(['*/api/tags' => Http::response(['models' => array_map(fn (string $name) => ['name' => $name], $names)])]);
}

it('indexes each project with only the rows of its language', function () {
    fakeOllamaWithModels();
    Embeddings::fake();
    $english = Project::factory()->create(['language' => LanguagesEnum::English]);
    $spanish = Project::factory()->create(['language' => LanguagesEnum::Spanish]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])->assertSuccessful();

    expect(Document::where('project_id', $english->id)->sole()->content)
        ->toBe('Open Monday to Friday. Free shipping over 50 dollars.')
        ->and(Document::where('project_id', $spanish->id)->sole())
        ->content->toBe('Abrimos de lunes a viernes.')
        ->source->toBe('docs.csv')
        ->chunk_index->toBe(0);
});

it('produces the same documents when run twice', function () {
    fakeOllamaWithModels();
    Embeddings::fake();
    Project::factory()->create(['language' => LanguagesEnum::English]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])->assertSuccessful();
    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])->assertSuccessful();

    expect(Document::count())->toBe(1);
});

it('reindexes only the requested project', function () {
    fakeOllamaWithModels();
    Embeddings::fake();
    $english = Project::factory()->create(['language' => LanguagesEnum::English, 'slug' => 'en-shop']);
    $other = Project::factory()->create(['language' => LanguagesEnum::Spanish, 'slug' => 'es-shop']);
    Document::create(['project_id' => $other->id, 'content' => 'old', 'chunk_index' => 0, 'embedding' => array_fill(0, 1024, 0.1)]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv', '--project' => 'en-shop'])->assertSuccessful();

    expect(Document::where('project_id', $english->id)->count())->toBe(1)
        ->and(Document::where('project_id', $other->id)->sole()->content)->toBe('old');
});

it('removes stale documents of a project whose language has no rows in the csv', function () {
    fakeOllamaWithModels();
    Embeddings::fake();
    $french = Project::factory()->create(['language' => LanguagesEnum::French]);
    Document::create(['project_id' => $french->id, 'content' => 'old', 'chunk_index' => 0, 'embedding' => array_fill(0, 1024, 0.1)]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain("sin filas 'fr'")
        ->assertSuccessful();

    expect(Document::count())->toBe(0);
});

it('keeps the previous index when embedding fails', function () {
    fakeOllamaWithModels();
    Embeddings::fake(fn () => throw new ConnectionException('refused'));
    $english = Project::factory()->create(['language' => LanguagesEnum::English]);
    Document::create(['project_id' => $english->id, 'content' => 'old', 'chunk_index' => 0, 'embedding' => array_fill(0, 1024, 0.1)]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain('se perdio la conexion con Ollama')
        ->assertFailed();

    expect(Document::sole()->content)->toBe('old');
});

it('skips malformed csv rows and reports their line numbers', function () {
    fakeOllamaWithModels();
    Embeddings::fake();
    $english = Project::factory()->create(['language' => LanguagesEnum::English]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs-with-bad-rows.csv'])
        ->expectsOutputToContain('Linea 3 ignorada')
        ->expectsOutputToContain('Linea 4 ignorada')
        ->assertSuccessful();

    expect(Document::where('project_id', $english->id)->sole()->content)->toBe('ok');
});

it('fails when the csv is missing', function () {
    artisan('app:index-documents', ['--path' => 'tests/Fixtures/nope.csv'])
        ->expectsOutputToContain('No se puede leer el CSV')
        ->assertFailed();
});

it('fails when the csv header is wrong', function () {
    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs-wrong-header.csv'])
        ->expectsOutputToContain('language,content')
        ->assertFailed();
});

it('fails when there are no projects', function () {
    fakeOllamaWithModels();

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain('No hay proyectos')
        ->assertFailed();
});

it('fails with a hint when Ollama is not reachable', function () {
    Http::preventStrayRequests();
    Http::fake(['*/api/tags' => fn () => throw new ConnectionException('refused')]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain('Ollama no responde')
        ->assertFailed();
});

it('fails with the pull command when the embeddings model is missing', function () {
    fakeOllamaWithModels(['qwen3:8b']);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain('ollama pull bge-m3')
        ->assertFailed();
});

it('rejects vectors whose dimension does not match the column', function () {
    fakeOllamaWithModels();
    Embeddings::fake(fn ($prompt) => new EmbeddingsResponse(
        array_map(fn () => array_fill(0, 3, 0.1), $prompt->inputs),
        new Usage(0),
        new Meta('ollama', 'bge-m3'),
    ));
    Project::factory()->create(['language' => LanguagesEnum::English]);

    artisan('app:index-documents', ['--path' => 'tests/Fixtures/docs.csv'])
        ->expectsOutputToContain('devolvio 3 dimensiones')
        ->assertFailed();

    expect(Document::count())->toBe(0);
});
