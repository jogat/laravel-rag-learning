# Laravel RAG assistant

A multi-tenant, multi-agent customer assistant built from scratch with Laravel, the
[`laravel/ai`](https://github.com/laravel/ai) SDK, Postgres + pgvector and local LLMs (Ollama). A React
chat (session auth) talks to an orchestrator agent that delegates to a product specialist
(vector search over the business knowledge base) and an order specialist (per-user order lookups).

Each **project** is one business with its own language, documents, orders and conversations; nothing
leaks across projects or users.

## Prerequisites

| Tool | Version | Used for |
|---|---|---|
| PHP + Composer | 8.3+ | Laravel backend |
| Node + npm | 22+ | React frontend (Vite, TypeScript, Tailwind) |
| Postgres + **pgvector** | pgvector ≥ 0.5 | data and vector search (`brew install postgresql pgvector`, or a managed Postgres that offers pgvector) |
| [Ollama](https://ollama.com) | recent | local chat and embeddings models |

```bash
ollama pull qwen3:8b     # chat model
ollama pull bge-m3       # embeddings model (1024 dimensions)
```

## Setup

The fastest path is `composer setup`, which runs the backend and frontend steps below in order. Create
the databases first (Postgres must be running):

```bash
createdb rag_laravel
createdb rag_laravel_testing        # used by the test suite
```

### One command

```bash
composer setup    # composer install, .env, key, npm install, npm run build, migrate --seed
```

If `migrate` fails on credentials, set `DB_USERNAME` / `DB_PASSWORD` in `.env` (Homebrew and Postgres.app
use your OS user and no password) and run `php artisan migrate --seed`.

### Backend, step by step

```bash
composer install
cp .env.example .env                # then check DB_USERNAME / DB_PASSWORD and OLLAMA_URL
php artisan key:generate
php artisan migrate --seed          # schema + demo data + knowledge-base indexing
```

`migrate --seed` enables pgvector, creates three demo projects (`demo-en`, `demo-es`, `demo-fr`), a test
user and two sample orders, and indexes the knowledge base. If Ollama wasn't ready, the seed still
succeeds and tells you to run the indexer once it is:

```bash
php artisan app:index-documents
```

### Frontend, step by step

The React app lives in `resources/react/src` and is built by Vite through `laravel-vite-plugin`. It is
served by Laravel on the same origin, so there is no separate frontend server or CORS setup.

```bash
npm install                         # dependencies
npm run build                       # one-off production build into public/build
# or, while developing the UI:
npm run dev                         # Vite with hot reload (creates public/hot)
```

Always open the app through Laravel at <http://localhost:8000>, never Vite's `:5173`. If the page fails
with "Unable to locate file in Vite manifest", run `npm run build` or start `npm run dev`.

### Run it

```bash
composer run dev                    # php artisan serve + log tail (pail) + vite dev server
```

or, in separate terminals: `php artisan serve`, then `npm run dev` (skip it if you ran `npm run build`
and aren't editing the UI).

Open <http://localhost:8000> and sign in with `test@example.com` / `password`, pick a project and ask
something such as "¿cuál es el horario?" (`demo-es`) or "¿dónde está mi pedido 12345?" (`demo-es`,
orders are only visible to their owner).

### Troubleshooting

| Symptom | Fix |
|---|---|
| `No se pudo habilitar pgvector` during `migrate` | Install pgvector, then run `CREATE EXTENSION vector;` as a superuser in the target database |
| Chat answers without knowledge, or the seed warned about Ollama | Start Ollama, `ollama pull bge-m3`, then `php artisan app:index-documents` |
| `Unable to locate file in Vite manifest` | `npm run build` or `npm run dev` |
| Blank page or stale UI after pulling | `npm install && npm run build` |
| Login works but chat returns 401 | The session expired; reload and sign in again |

To start over: `php artisan migrate:fresh --seed`.

## The knowledge base

`database/seeders/data/docs.csv` (`language,content`) is the single source of truth. Edit it and run
`php artisan app:index-documents` (add `--project=demo-es` to reindex one project, `--path=` for another
file). Indexing is idempotent: each project's documents are replaced in one transaction, after the
embeddings are generated, so a failure leaves the previous index intact.

Pipeline: CSV rows of a project's language → `ChunkingService` (200-word chunks, 40-word overlap) →
embeddings (`bge-m3`) → one `documents` row per chunk with a `vector(1024)` column and an HNSW index.

## Try it from the terminal

```bash
php artisan app:ask-agent demo-es "cual es el horario?"
php artisan app:ask-agent demo-es "donde esta mi pedido 12345?" --as=test@example.com
php artisan pail                    # tail agent and tool-call logs
```

## Tests

```bash
php artisan test --compact          # feature + unit; uses rag_laravel_testing, AI calls are faked
```

Browser tests (Dusk) drive a real server and truncate their database, so give them their own env file:

```bash
cp .env.dusk.local.example .env.dusk.local     # paste your APP_KEY into it
php artisan dusk:chrome-driver
npm run build
(cd public && APP_ENV=dusk.local php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php) &
php artisan dusk
```

## Learning path

The project grew in stages. The first phase (hand-rolled HTTP against LM Studio, cosine similarity by
hand, RAG in memory) was removed from the tree but is preserved at the git tag `phase-1-lm-studio`:

```bash
git checkout phase-1-lm-studio
```

See `CLAUDE.md` for the architecture in detail.
