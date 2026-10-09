# Laravel RAG assistant

A multi-tenant, multi-agent customer assistant built from scratch with Laravel, the
[`laravel/ai`](https://github.com/laravel/ai) SDK, Postgres + pgvector and local LLMs (Ollama). A React
chat (session auth) talks to an orchestrator agent that delegates to a product specialist
(vector search over the business knowledge base) and an order specialist (per-user order lookups).

Each **project** is one business with its own language, documents, orders and conversations; nothing
leaks across projects or users.

## Prerequisites

- PHP 8.3+ and Composer, Node 22+
- Postgres with the **pgvector ≥ 0.5** extension (`brew install postgresql pgvector`, or any managed
  Postgres that offers pgvector)
- [Ollama](https://ollama.com) running locally

```bash
ollama pull qwen3:8b     # chat model
ollama pull bge-m3       # embeddings model (1024 dimensions)
```

## Quick start

```bash
createdb rag_laravel
createdb rag_laravel_testing        # used by the test suite

composer setup                      # install, .env, key, npm build, migrate --seed
# edit DB_USERNAME / DB_PASSWORD in .env if your Postgres needs them, then re-run migrate --seed

composer run dev                    # app server + log tail + vite
```

Open <http://localhost:8000> (not Vite's `:5173`) and sign in with `test@example.com` / `password`.

`migrate --seed` creates three demo projects (`demo-en`, `demo-es`, `demo-fr`), a test user, two sample
orders and indexes the knowledge base. If Ollama wasn't ready, the seed still succeeds and tells you to
run the indexer once it is:

```bash
php artisan app:index-documents
```

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
