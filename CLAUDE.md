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

## Frontend: React chat with session auth

A React 19 + TypeScript single-page chat served by Laravel (same origin, so no CORS), styled with Tailwind v4. Open it at `http://localhost:8000`, not Vite's `:5173`.

**Serving.** `Route::view('/', 'app')` renders `resources/views/app.blade.php`, which mounts `<div id="root">` and loads `resources/react/src/main.tsx` via `@viteReactRefresh` + `@vite`. `vite.config.js` uses `laravel-vite-plugin` + `@vitejs/plugin-react-swc` + `@tailwindcss/vite`, with `@` aliased to `resources/react/src`. The plugin's single `input` is `main.tsx`, which imports `resources/css/app.css` itself, so `npm run build` and `npm run dev` both serve the app.

**Screens (no router).** `App.tsx` calls `fetchUser()` on mount, shows a loader until the session check finishes, then renders `components/Login.tsx` or `components/Chat.tsx` depending on whether a user is logged in. `Chat` keeps its messages in local state (cleared when the project changes), reads projects from the store, and posts to `/api/chat`.

**State (Zustand, the Vuex/Pinia equivalent).** `stores/authStore.ts` holds `user` and `checked` with the `fetchUser`/`login`/`logout` actions; logout also resets the project store. `stores/projectStore.ts` holds `projects`, `current` (selected slug), `loading` and `error`; `fetchProjects` ignores a call while one is already loading, which absorbs StrictMode's double effect. Components read single values through selectors, e.g. `useAuthStore((s) => s.user)`.

**API layer.** `lib/api.ts` exports `request<T>()`, which sends JSON with `Accept: application/json` and an `X-XSRF-TOKEN` header read from Laravel's `XSRF-TOKEN` cookie, and throws `ApiError(status, message)` on non-2xx responses. Endpoint functions live in `lib/api/{auth,project,chat}.api.ts`. Laravel 13's `PreventRequestForgery` also accepts same-origin requests via `Sec-Fetch-Site`, but the token header is what protects plain-HTTP non-localhost hosts.

**Backend routes (`routes/web.php`, all JSON under `/api`).** `POST /api/login` (`throttle:5,1`) is public. Behind `auth`: `GET /api/user`, `POST /api/logout`, `GET /api/projects`, and `POST /api/chat` (`throttle:20,1`). Auth is the hand-written `AuthController` using session auth (regenerates the session on login, invalidates it and rotates the token on logout), not Fortify or Sanctum. `ChatController` validates `project` (slug) and `message`, then calls `App\Services\BusinessAssistant::ask()` with `$request->user()`; `app:ask-agent` uses the same service. Guests get a 401 JSON response because `bootstrap/app.php` renders `api/*` exceptions as JSON. The seeded login is `test@example.com` / `password`.

**Not handled yet:** a session that expires mid-chat shows "Unauthenticated." instead of returning to `Login`; `Chat` doesn't display `projectStore.error`; projects aren't scoped per user (every logged-in user sees all of them); there are no frontend tests.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
