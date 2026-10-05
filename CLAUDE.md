# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

A personal, Spanish-language learning project that builds a RAG (Retrieval-Augmented Generation) pipeline in Laravel from scratch, incrementally, and then grows it into a multi-agent, multi-tenant assistant on the `laravel/ai` package. There is no HTTP UI yet — all work lives in **artisan console commands** under `app/Console/Commands/`, where each command is a stage in the learning arc. Comments and command output are mostly in Spanish; keep that convention when editing existing commands (agent instructions and tool descriptions are in English because they are prompts for the model).

## Learning arc (read the commands in this order)

**Phase 1 — hand-rolled HTTP against LM Studio (legacy, kept for reference):**

1. `app:lm-studio-conversation` — raw HTTP chat against a local LLM.
2. `app:embedding-experiment` — generate embeddings and rank documents by cosine similarity computed by hand.
3. `app:rag-completo {pregunta}` — full RAG entirely in memory: re-indexes the hard-coded `$baseConocimiento` array on every run, retrieves top-K, then generates.
4. `app:indexar-documentos` — persists that knowledge base with embeddings into Postgres.
5. `app:preguntar-rag {pregunta}` — retrieval done by pgvector (`<=>` cosine distance, ascending), generation by the chat model.

These commands post with `Illuminate\Support\Facades\Http` to LM Studio at `http://localhost:1234/v1/...` with hard-coded models (`qwen/qwen3-8b`, `text-embedding-bge-m3`). Note they were written against a `documentos` table with a `contenido` column and now import `App\Models\Document`; they predate `project_id`/`chunk_index` being required, so expect them to need adjusting if you run them.

**Phase 2 — `laravel/ai` package against Ollama (current work):**

6. `app:index-v2-command`, `app:scratch-command` — first use of `Laravel\Ai\Embeddings` and `agent()`. `index-v2` also predates the `project_id` requirement.
7. `app:index-demo` — the real indexer. Reads `storage/app/private/docs.csv` (columns `language,content`), concatenates all rows of a project's language into one source text, and dispatches an `IndexDocument` job per project.
8. `app:ask-agent {project_slug} {question} {--as=test@example.com}` — the capstone: a multi-agent, per-project assistant with persisted conversations.

When adding a new stage, follow the existing command shape: `#[Signature]`/`#[Description]` attributes on the class, `handle()` returning `self::SUCCESS`/`self::FAILURE`.

## Architecture of the current system (stages 7–8)

Read these together; the behavior only makes sense across them.

**Multi-tenancy by `Project`.** A `Project` is one "business" with a `slug` and a `language` (`App\Enums\LanguagesEnum`, backed by the display name e.g. `'Español'`, with `shortName()` giving `es`). Everything is scoped by `project_id`: documents, orders, and agent conversations. The seeders create one project per language (`demo-en`, `demo-es`, `demo-fr`) plus a test user `test@example.com` and two orders that share order number `12345` across two projects — this is deliberately there to prove isolation.

**Indexing pipeline.** `IndexDemo` → `App\Jobs\IndexDocument` (queued, `QUEUE_CONNECTION=database`, so a worker must be running) → `App\Services\ChunkingService` (paragraph-aware, 200-word chunks with 40-word overlap; constructor validates the ratio) → `Laravel\Ai\Embeddings::for($chunks)->generate()` → one `Document` row per chunk with sequential `chunk_index` and `source`. `IndexDemo` truncates `documents` first.

**Agent hierarchy (`app/Ai/`).** `BusinessAgent` is an orchestrator with no tools of its own; `AskAgent` injects two sub-agents via `setTools()`. Sub-agents implement `CanActAsTool` so the orchestrator calls them by `name()` (`product_specialist`, `order_specialist`):
- `ProductSpecialist` wraps `Laravel\Ai\Tools\SimilaritySearch::usingModel(Document::class, 'embedding', minSimilarity: 0.4, query: project filter)` — this replaces the hand-rolled pgvector query from stage 5.
- `OrderSpecialist` wraps `App\Ai\Tools\QueryOrder`, which filters by `project_id` **and** `user_id`, so a user can only see their own orders in that project.
- Instructions in every agent are deliberately strict about "never invent"; the orchestrator is told to relay specialist output verbatim. Keep that grounding discipline when editing prompts.

**Qwen "thinking" control.** Each agent's `providerOptions()` returns `['think' => bool]` only when the provider is `ollama`. The orchestrator and `OrderSpecialist` run with `think: false` (routing only, fast); `ProductSpecialist` runs with `think: true` (needs reasoning over search results). In the legacy LM Studio commands the equivalent is the `/no_think` token in the prompt.

**Conversation memory.** `BusinessAgent` uses `RemembersConversations` (max 40 messages). `App\Services\ConversationManager` finds the user's most recent conversation **in that project** updated within the last 12 hours and resumes it via `$agent->continue($id, as: $user)`, otherwise starts fresh with `forUser($user)`. The `agent_conversations` table (from the `laravel/ai` migration) has a custom added `project_id` column; `tagProject()` stamps it after every turn because the SDK does not know about projects.

**Observability.** `App\Ai\Middleware\LogAgentActivity` (per-agent latency, token usage incl. reasoning tokens) and `App\Listeners\LogAgentToolCalls` (every `ToolInvoked`) write to the log, correlated by a `correlation_id` set in `Context` at the start of `AskAgent`. The listener is auto-discovered from `app/Listeners`; do **not** also register it in `AppServiceProvider` or each tool call is logged twice. Read logs with `php artisan pail`.

## LLM backends (both local, no cloud keys)

- **Ollama** at `http://localhost:11434` is the active backend. `config/ai.php` sets `default` and `default_for_embeddings` to `ollama`, chat model `qwen3:8b`, embeddings `bge-m3` with `dimensions => 1024`.
- **LM Studio** at port 1234 is only needed for the legacy phase-1 commands.

Both use **bge-m3 → 1024-dim vectors**, which must match the `vector(1024)` column. If you change the embedding model, change the dimension in the `documents` migration and in `config/ai.php` together.

## Data layer: Postgres + pgvector

- The app **requires Postgres with the pgvector extension** even though `.env.example` defaults to sqlite. The real `.env` uses `pgsql` / database `rag_laravel`. The `documents` migration runs raw `DB::statement`s for the `vector(1024)` column and the `hnsw (embedding vector_cosine_ops)` index, so it fails on sqlite — this also means the default test suite cannot use sqlite for anything touching `documents`.
- `App\Models\Document` stores the embedding via `App\Casts\VectorCast` (PHP `array<float>` ⇄ pgvector `[0.1,0.2,...]` text). Always assign/read `embedding` as a plain array.
- Order numbers are unique per `(project_id, order_number)`, not globally.
- Model PHPDoc blocks are generated by `barryvdh/laravel-ide-helper` (`_ide_helper.php` is checked in); regenerate with `php artisan ide-helper:models -N` after schema changes rather than hand-editing them.

## Prerequisites to run anything

1. Postgres with `pgvector` enabled; `php artisan migrate --seed` (seeding creates the projects, test user and sample orders that `ask-agent` depends on).
2. Ollama running with `qwen3:8b` and `bge-m3` pulled.
3. A queue worker (`php artisan queue:listen` or `composer run dev`) before `app:index-demo`, since indexing is a queued job.

## Common commands

```bash
php artisan app:index-demo                                   # chunk + embed docs.csv into documents (queued)
php artisan app:ask-agent demo-es "cual es el horario?"      # ask the Spanish business
php artisan app:ask-agent demo-es "donde esta mi pedido 12345?" --as=test@example.com
php artisan pail                                             # tail agent/tool logs
php artisan test --compact                                   # run tests (--filter=testName for one)
vendor/bin/pint --dirty --format agent                       # format changed PHP files
composer run dev                                             # serve + queue + pail + vite
```

Only the default Laravel example tests exist (`tests/Feature`, `tests/Unit`); nothing in `app/Ai`, `app/Services` or the commands is covered yet. `ChunkingService` and `ConversationManager` are pure enough to unit-test without a model server.

## Frontend (work in progress)

`vite.config.js` has been switched from the Laravel plugin to a plain React/SWC setup building `resources/react/src` into `public/build`, with `@` aliased to `resources/react/src`. `App.tsx` is still empty and `routes/web.php` only serves the stock `welcome` view, so there is no working UI yet; the commented-out Laravel/Tailwind Vite config is kept at the top of the file for reference.
