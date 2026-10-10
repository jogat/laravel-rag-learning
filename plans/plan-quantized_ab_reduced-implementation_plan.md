## Summary

Reduce CPU latency while preserving grounded business and order answers. The initial isolated A/B experiment established the benefit of fewer generations; the approved follow-up hardens order references, contextual routing and evidence selection, then compares quantized models against live answer-quality checks. Keep all changes in the reduced worktree and preserve the original checkout and its uncommitted work.

## Context

- Existing plan read: plans/plan-plan_artifacts_adoption-implementation_plan.md.
- User authorized worktrees from dirty HEAD with all current tracked modifications copied to both arms.
- Laravel AI SDK 1.0.1 and Laravel 13; inspect installed APIs and official documentation because Boost tools are unavailable.
- Same quantized model, embeddings model (bge-m3), knowledge data, CPU, and inference options in both arms.
- Separate PostgreSQL database copies and conversation/storage directories. No writes to the original database during benchmarks.
- Existing flow: classifier, orchestrator tool request, specialist tool request, specialist response, orchestrator response (normally five generations plus embedding).

## Implementation

- Arm A preserves the existing nested generation architecture. The initial same-model A/B results below are historical; later hardening/model comparisons use Arm B only.
- Arm B classifies scope, resolves order references and executes project/user-scoped QueryOrder in PHP, then generates one tool-free answer. Accepted FAQs use project-scoped vector retrieval of two content-only excerpts. Normal answered turns require two text generations; refusals require one.
- Keep user/project conversation isolation, 12-hour expiry, classifier audit/bypass behavior and stored customer history. Add history to classification only for contextual follow-ups; give the responder only the latest topic when needed. Never override an out_of_scope classifier refusal.
- Prefer an explicit current order number, ask when references are missing/ambiguous/malformed, and preserve explicitly marked alphanumeric identifiers. Do not reuse an older reference when the customer asks about another order.
- Preserve relevant source sentences verbatim; distinguish store and support hours. Retain complete retrieved excerpts for compound questions and lexical misses. Evidence narrowing is a bounded heuristic, not a semantic completeness guarantee.
- Use compact, route-specific responder instructions with native Spanish for demo-es. Require explicit acknowledgement of unavailable facts/prices and exact order item names. Extend stored-response redaction to observed reference and thinking markers.
- Compare Qwen3 1.7B and LiquidAI LFM2.5 1.2B Q4_K_M. Because neither passes the full live quality gate, evaluate Qwen3-4B-Instruct-2507 through qwen3:4b-instruct Q4_K_M. The generic installed qwen3:4b is a different, thinking-only checkpoint and failed the latency requirement.
- Use temperature zero, think=false, a 32-token classifier limit and a 256-token answer limit for the final instruct matrix. Record actual request options and termination reasons. Earlier matrices predate these limits and are labelled separately.
- Use the isolated Artisan benchmark command to capture actual HTTP timings, tokens, model loads, replies and tool traces. Roll back all measured conversation changes, including provider failures. Disable SDK title generation only within this benchmark command.
- Run inference sequentially without tests competing for CPU. Report controlled cold loading separately from warm requests; retain failed runs, raw answers and source hashes. After a candidate passes the measured quality gate, set the reduced worktree's local model, example environment and Ollama text fallback to that explicit checkpoint. Do not merge or modify the original environment.

## Rejected alternatives

- Concurrent benchmarks: they compete for the same CPU and corrupt latency comparisons.
- Sharing the live conversation database: history and benchmark writes would contaminate results.
- Comparing different model sizes between arms: confounds generation-count effects with model choice.
- Removing classification or project/user filters: changes refusal and isolation behavior.
- Promising a seconds-level result before measurement: host performance and tool reliability must be observed.

## Verification

- Run focused Pest tests for assistant memory, retrieval scoping, refusal/leak guard, and order isolation.
- Run Pint --dirty --format agent and git diff --check in each worktree.
- Confirm identical baseline changes and model options; inspect Ollama quantization and CPU residency.
- Measure at least three paired runs of the exact hours query plus Spanish scenarios for missing facts, orders, out-of-scope requests, and a follow-up.
- Inspect answers against seeded knowledge/order records; report failures alongside latency, generation counts, and embedding timings.
- Preserve worktrees, plans and raw results for review; do not merge into original branch.

### Final verified result and model decision

Completed the approved hardening on 2026-10-09. Select **qwen3:4b-instruct Q4_K_M**, the Qwen3-4B-Instruct-2507 checkpoint, for the reduced worktree. Its local .env, .env.example and Ollama text-model fallback now agree. The original checkout still has its original seven tracked modifications and qwen3:8b environment setting; it was not merged or changed.

| Quantized model | Primary warm hours median | Controlled cold hours | Complete primary hours | Full live quality gate |
| --- | --- | --- | --- | --- |
| Qwen3 1.7B Q4_K_M | 3.73 s | 15.50 s | 0/3 | Failed: omitted closures, false refusal of manager-name question |
| LiquidAI LFM2.5 1.2B Q4_K_M | 3.02 s | 10.29 s | 3/3 | Failed: manager-name question classified as greeting, internal marker in answer |
| Qwen3-4B-Instruct-2507 Q4_K_M | **7.79 s** | **26.52 s** | **3/3** | **Passed all 19 matrix replies** |

Tiny-model figures come from the routed matrix before the final token limits, compound-question fallback and expanded leak guard. The final instruct matrix uses the same resolved routing and single-topic evidence rules, plus those safeguards. This table supports model selection, not an accuracy-equivalent speedup claim. The smaller models are faster but did not satisfy the measured accuracy requirements. The generic qwen3:4b thinking checkpoint is a separate failed experiment, described below.

The final instruct matrix contains 14 CLI runs / 19 turns: 17 warm, one controlled cold and one warm-up. Primary warm hours samples were 7.8013, 7.5496 and 7.7891 seconds. Across all warm scenarios, individual turns ranged from 2.1251 to 11.5950 seconds. Both Sunday follow-ups said only that Sundays are closed; both unavailable express-fee replies explicitly acknowledged the missing exact price; both order follow-ups copied the actual item names; the unknown manager name was treated as unavailable business information; the disclosure request was refused after business context; and the missing-order-number query requested clarification without claiming a lookup occurred. Every HTTP request returned 200, every run exited zero, and every text response ended with stop rather than the output limit. Actual request options were temperature=0, think=false and num_predict=32/256 for classifier/answer. Answered turns used two text generations, FAQ turns one embedding request, and the refusal one generation without retrieval.

The exact user command, with the selected local model and normal application behavior, returned complete correct hours in **10.01 seconds end to end**:

```sh
cd /private/tmp/rag-ab-20261009/reduced
php artisan app:ask-agent demo-es "cual es el horario?" --no-interaction
```

An additional transactional live clarification check asked for the missing order number in 5.96 seconds, then accepted the bare reply 12345 and returned the scoped status, correct delivery date and exact item names in 12.02 seconds. These two turns and the exact command are separate from the 19-turn matrix.

Verification: **98 Pest tests passed, 258 assertions, 79.8% coverage**, exceeding the 77% gate. Coverage includes order precedence/ambiguity/malformed references, tenant/user isolation, follow-up context, unavailable evidence, multilingual store/support hours, compound questions, expanded leak redaction, and benchmark refusal/rollback behavior. Existing tests were retained. Pint --dirty --format agent and git diff --check passed. The selected configuration was also exercised by the real CLI and clarification check.

Artifacts are retained under /private/tmp/rag-ab-20261009: instruct-summary.json, instruct-qwen4_instruct-*.json, instruct-code-sha256.json (matrix source snapshot), instruct-actual-command.txt/.time, instruct-order-clarification.json and final-regression.txt. Earlier routed-*, verified-*, final-*, quality-* and fallback-* artifacts preserve both failures and corrections. Reproduce the final matrix with `python3 /private/tmp/rag-ab-20261009/run-benchmark.py --quality-models --fallback`; this explicitly unloads the text and embedding models for its cold sample.

Limits: the live gate covers the seeded Spanish demo and the listed scenarios; it is not a production-wide accuracy guarantee. Cold loads remain slower than warm requests, and other questions can take longer than the primary hours median. Lexical evidence narrowing can miss semantically related source sentences; its fallback preserves full retrieved excerpts for compound questions and unmatched wording. The 256-token cap bounds customer output and may truncate unusually long answers. Before merging, run the complete suite on the intended target environment with `php artisan test --compact`.

Sources for checkpoint identity and non-thinking behavior: [Qwen model card](https://huggingface.co/Qwen/Qwen3-4B-Instruct-2507), [Ollama instruct tag](https://ollama.com/library/qwen3:4b-instruct). Ollama reports 100% CPU residency, a 4096-token runtime context and approximately 2.9 GB memory for this model. The installed generic architecture metadata still advertises thinking, so capability flags alone do not establish checkpoint behavior; the instruct template does not append a forced thinking block, and no reasoning block appeared in the measured replies.

### Commit and push handoff

On 2026-10-10 the user requested a commit and push. Ship the reduced implementation, selected model configuration, regression tests, transactional benchmark command and this decision record on feature/quantized-ab-reduced. Include the original tracked changes copied into this worktree, which formed the tested baseline. Preserve the local phpunit.xml experiment-database override as an uncommitted worktree setting; the pushed branch retains rag_laravel_testing, matching the existing CI service. Keep local environment files, database copies, downloaded model files and external raw benchmark artifacts out of the commit. Recheck formatting, regression coverage, the staged file list and whitespace before committing, then push the feature branch without merging it into the original branch.

### Experiment setup adjustment

The SDK generates a title on first conversation creation using a separate cheapest model (qwen3.5:0.8b, absent on this host). Disable ai.conversations.generate_title only in the benchmark command for both arms to isolate the customer-answer flow and avoid a second model. First observation A-first includes the failed title request and is excluded from paired measurements. It also showed a tool-delegation failure caught by the leak guard.

### Follow-up correction after first paired run

Both original arms refused "¿Y los domingos?" because the shared classifier saw no prior customer context. Arm B now passes project/user-scoped earlier customer messages to the existing classifier, with an explicit current-message label. This adds no generation. Independent first-turn measurements are unaffected. Retest follow-ups, refusal after an in-scope question, and an unknown express-shipping price. The staff-name question was also refused by the classifier in both arms and does not establish missing-evidence answer quality.

## Initial experiment results (before approved hardening)

Completed on the Intel i9-9880H host with 16 GiB RAM, Ollama 0.32.6, Laravel 13.34.0 and laravel/ai 1.0.1. `ollama show qwen3:1.7b` confirms Q4_K_M, approximately 2.0B total parameters, tools/thinking capabilities. Ollama reports 100% CPU residency, a 4096-token context and approximately 1.7 GB runtime memory. Installed model file size: 1.4 GB. Embeddings remain bge-m3 (1024 dimensions); no re-indexing or model download was needed.

### Primary A/B comparison

Same exact question: `cual es el horario?`. Three paired runs, alternating A/B and B/A; both use the same model and provider defaults with `think=false`. There were no simultaneous inference requests. Each independent sample starts with empty conversation history within a database transaction, which is rolled back. Automatic title generation is disabled in the benchmark command for both arms to exclude a second, missing model. Cache store is array for both; requests run in new CLI processes, preventing persistent application embedding-cache hits. Ollama model/prompt caching remains enabled and is part of these warmed measurements.

| Arm | Warm samples (seconds) | Median | Actual text generations | Correct complete store-hours answers |
| --- | --- | --- | --- | --- |
| A: current nested-agent flow | 24.55, 22.21, 3.31 | 22.21 s | 5, 5, 2 | 0/3 |
| B: direct routing/retrieval/answer | 4.95, 4.81, 4.79 | 4.81 s | 2 each | 3/3 |

B's median is 4.62x faster (78.4% lower latency). This is not an accuracy-equivalent speed comparison: A's two full flows confused support hours (8am–8pm) with store hours (9am–6pm), and its fast third response leaked a specialist name and was replaced with a refusal. The intended five-call architecture does not guarantee five actual calls because the small model sometimes omits required tools.

The first B observation was 19.96 s, including a 3.41 s embedding-model load and a large uncached answer prompt. Its Qwen text model was already loaded by A. Thus it is a first-use observation, not a controlled fully cold A/B comparison. Do not claim all queries complete in five seconds. Warmed prompt reuse is important.

The exact user command also succeeded in B: `php artisan app:ask-agent demo-es "cual es el horario?" --no-interaction`, measured with `/usr/bin/time -p`, returned the correct full store-hours answer in 6.58 s end to end. This sanity check includes CLI startup/debug output and leaves a conversation only in the B database copy. It is separate from the paired statistics.

### Quality and broader latency observations

- Cash-payment question: B 2.76 s, correct "No aceptamos pagos en efectivo". A 2.01 s, merely repeated the customer's question and performed no retrieval.
- Existing order 12345: B 11.37 s / 3 text calls, correct scoped status/date/items. A 5.94 s but malformed specialist arguments (`order_number` instead of required `task`) produced a failed tool invocation, and no order lookup succeeded. A's faster response did not answer the question.
- Prompt disclosure/refusal: both refused in a fresh conversation, and both refused after a business question. B's contextual refusal took 1.86 s.
- Staff-name unknown fact: both classifiers refused as out-of-scope. This is a false rejection of an otherwise plausible business question; it does not prove missing-evidence grounding.
- Unknown express-shipping price: B 2.83 s / 2 calls, returned "El envío exprés cuesta un costo adicional" without inventing an amount, but should state that the exact price is unavailable. A 9.39 s / 4 calls, correctly said the price was unavailable but skipped its required knowledge search.
- Initial Sunday follow-up: both refused because the classifier lacked previous-turn context. B's scoped-context correction keeps the two-call architecture and avoids that refusal. The corrected follow-up took 16.95 s and correctly said Sunday is closed, but also introduced incorrect/unnecessary support/Saturday hours. Treat the whole answer as a quality failure.
- Order follow-up ("¿Qué artículos tiene ese pedido?"): even with context, B's small classifier selected `product` rather than `order`, so the reply failed to retrieve the order. A invented a different order number on its follow-up.

### Initial decision

Prefer direct retrieval and a single grounded answer over nested delegation for small CPU models. Qwen3 1.7B Q4_K_M is a useful candidate for short, warmed, first-turn FAQs on this host. It is not ready as a drop-in production model for the full conversational/order assistant: classification, tool arguments and follow-up grounding remain unreliable. Retain this as an experiment; do not merge or change the original environment on the basis of three successful FAQ samples alone.

### Verification and artifacts

- A: 22 focused tests passed, 63 assertions. B final: 28 focused tests passed, 85 assertions. The original suite was not deleted; B updates embedding fakes to cover deterministic retrieval.
- Tests cover user/project conversation isolation, 12-hour expiry, classifier bypass/refusal, leak redaction, retrieval scoping and missing results, scoped order lookup, greeting behavior and classifier/retrieval follow-up context. Live results are still necessary because fakes cannot establish model accuracy.
- Pint `--dirty --format agent` and `git diff --check` passed in both worktrees.
- 24 measured CLI runs / 32 turns, with no uncaught run errors or non-200 inference HTTP responses. All measured text requests used qwen3:1.7b; all embedding requests used bge-m3. Logical quality failures are listed above. A-first/B-first contain an additional failed title request and are excluded from this count/statistics.
- Full regression suite remains to run before merging.
- Worktrees: `/private/tmp/rag-ab-20261009/current` (feature/quantized-ab-current), `/private/tmp/rag-ab-20261009/reduced` (feature/quantized-ab-reduced). Both are uncommitted experiment branches based on bbe646e with the original seven tracked modifications copied in. The original branch and .env remain unchanged.
- Dedicated live databases: `rag_ab_20261009_current`, `rag_ab_20261009_reduced`. Dedicated regression databases have `_tests` suffixes.
- Raw measured JSON files: `/private/tmp/rag-ab-20261009/{current,reduced}-*.json`; each stores answers, tool traces, per-request token counts/load/prefill/decode durations, service latency, and total CLI latency. `summary.json` is the compact index. First observations: `A-first.json`, `B-first.json`. Exact command check: `reduced-actual-command.txt` / `.time`.
- Reproduce: `python3 /private/tmp/rag-ab-20261009/run-benchmark.py` and, for follow-up/unknown-fact checks, append `--supplemental`. The identical benchmark command guards its database name, clears prior copied history inside its transaction, and rolls back all benchmark conversation changes.
- The first round precedes B's classifier-context correction; only supplemental follow-up results exercise that correction. Independent hours-query behavior was unchanged.

### All measured turns

| Label | Turn | Seconds | Text calls | Embedding calls | Reply |
| --- | --- | --- | --- | --- | --- |
| reduced-followup_context-1 | 1 | 5.17 | 2 | 1 | El horario de nuestro negocio es de lunes a viernes de 9 am a 6 pm, y de sábados de 10 am a 2 pm. Cerramos los domingos y días festivos. |
| reduced-followup_context-1 | 2 | 16.95 | 2 | 1 | El horario de atención al cliente es de lunes a viernes de 8am a 8pm y sábados de 10am a 2pm. Los domingos y días festivos cerramos. |
| reduced-hours-2 | 1 | 4.81 | 2 | 1 | El horario de nuestra tienda es de lunes a viernes de 9am a 6pm, y de sábados de 10am a 2pm. Cerramos los domingos y días festivos. |
| current-order-1 | 1 | 5.94 | 3 | 0 | La pregunta se relaciona con el estado de un pedido. Por favor, déjame ver el estado del pedido 12345. |
| reduced-hours-3 | 1 | 4.79 | 2 | 1 | El horario de nuestra tienda es de lunes a viernes de 9am a 6pm, y de sábados de 10am a 2pm. Cerramos los domingos y días festivos. |
| reduced-order_context-1 | 1 | 11.44 | 3 | 0 | Tu pedido 12345 está en camino, con fecha estimada de entrega el 12 de octubre de 2026. Los items incluidos son "Auriculares inalámbricos" y "Cargador USB-C". |
| reduced-order_context-1 | 2 | 7.30 | 2 | 1 | La información sobre el pedido 12345 no está disponible en los datos proporcionados. Por favor, indique el número de pedido o proporcionélo para que pueda buscar la información correspondiente. |
| current-missing_shipping-1 | 1 | 9.39 | 4 | 0 | La información sobre el costo del envío exprés no está disponible. |
| current-payments-1 | 1 | 2.01 | 2 | 0 | ¿Aceptan pagos en efectivo? |
| reduced-unknown-1 | 1 | 1.00 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-hours-2 | 1 | 22.21 | 5 | 1 | El horario de atención al cliente es de lunes a viernes, de 8:00 AM a 8:00 PM. |
| current-unknown-1 | 1 | 1.40 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-hours-3 | 1 | 3.31 | 2 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| reduced-order-1 | 1 | 11.37 | 3 | 0 | Tu pedido 12345 está en el camino. La fecha estimada de entrega es el 12 de octubre de 2026. Los items incluyen "Auriculares inalámbricos" y "Cargador USB-C". |
| reduced-payments-1 | 1 | 2.76 | 2 | 1 | No aceptamos pagos en efectivo. |
| reduced-scope_context-1 | 1 | 5.10 | 2 | 1 | El horario de nuestra tienda es de lunes a viernes de 9am a 6pm, y los sábados de 10am a 2pm. Cerramos los domingos y días festivos. |
| reduced-scope_context-1 | 2 | 1.86 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-scope_context-1 | 1 | 7.96 | 4 | 0 | El horario de atención al cliente es de lunes a viernes de 9:00 AM a 8:00 PM. |
| current-scope_context-1 | 2 | 1.10 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| reduced-followup-1 | 1 | 5.28 | 2 | 1 | El horario de nuestra tienda es de lunes a viernes de 9am a 6pm, y de sábados de 10am a 2pm. Cerramos los domingos y días festivos. |
| reduced-followup-1 | 2 | 0.96 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-refusal-1 | 1 | 1.22 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-hours-1 | 1 | 24.55 | 5 | 1 | El horario de atención al cliente es de lunes a viernes de 8:00 a.m. a 8:00 p.m. |
| reduced-refusal-1 | 1 | 0.86 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-followup-1 | 1 | 29.37 | 5 | 1 | El horario de atención al cliente es:  - **Lunes a Viernes:** 8:00 AM a 8:00 PM  - **Sábado:** 10:00 AM a 2:00 PM  - **Sábado y Días Festivos:** Cerrado   Estos horarios se aplican al establecimiento, tienda o negocio mencionado en los resultados. |
| current-followup-1 | 2 | 0.99 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-order_context-1 | 1 | 3.26 | 2 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |
| current-order_context-1 | 2 | 13.82 | 5 | 0 | El pedido `123456789` no fue encontrado. ¿Podrías proporcionar el número de pedido nuevamente o verificar si hay algún error? |
| reduced-missing_shipping-1 | 1 | 2.83 | 2 | 1 | El envío exprés cuesta un costo adicional. |
| reduced-hours-1 | 1 | 4.95 | 2 | 1 | El horario de nuestra tienda es de lunes a viernes de 9am a 6pm, y de sábados de 10am a 2pm. Cerramos los domingos y días festivos. |
| current-followup_context-1 | 1 | 2.25 | 2 | 0 | ¿Cuál es el horario de atención? |
| current-followup_context-1 | 2 | 1.01 | 1 | 0 | Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos. |

### Full-suite setup follow-up

User reported the feature example failed with a 500. Reproduced the failure: the isolated worktree lacks public/build/manifest.json. The test is already Pest syntax and uniquely covers GET / rendering; keep it unchanged. The true-is-true unit example has no application coverage, but neither test is removed. Restore ignored compiled frontend assets from the original checkout after verifying identical frontend sources/config/package lock. Run the isolated example and complete non-browser suite. This is worktree environment setup, not an agent-code change.

Full-suite follow-up outcome: restored ignored frontend build assets after confirming identical frontend sources/config/package lock. The unchanged feature example passes. `php artisan test --compact` now passes all 63 tests with 170 assertions (non-browser suite). No test files or application source were changed for this fix. This supersedes the earlier note that full-suite validation remained pending.


## Follow-up implementation (authorized)

### Summary

Harden the reduced flow's observed live failures, then compare Qwen3 1.7B Q4_K_M with LiquidAI LFM2.5 1.2B Instruct Q4_K_M on identical code and data. Keep the original checkout and A arm unchanged.

### Context

The full Pest suite passes, but live Sunday follow-ups mix unrelated hours and order-item follow-ups are classified as business questions. Existing plans and worktree instructions were read; .ai/rules is absent. Installed Laravel is 13.34.0, AI SDK 1.0.1 and Pest 4.7.5. Official AI SDK documentation and installed implementation are the API references because Boost tools are unavailable.

### Implementation

- Keep the scope classifier as the refusal boundary. For accepted messages, resolve explicit existing-order references and follow-ups deterministically using only this user's active, project-scoped customer history. Never override an out-of-scope refusal.
- Extract order numbers in PHP, prefer an explicit current number, and ask when missing/ambiguous. Execute the existing project/user-scoped QueryOrder in PHP and give its result to a tool-free responder. This removes order tool-request generation and model-invented arguments.
- Limit retrieval to a small number of content-only excerpts. Use the most recent relevant customer question for short follow-ups; omit instruction-heavy folded history from embedding queries. Shorten responder instructions and require answers to the current question only, distinguishing store and support hours and explicitly acknowledging unavailable values.
- Add meaningful Pest cases for order follow-ups, current-number precedence, absent/ambiguous references, refusal preservation, tenant/user isolation and retrieval context. Keep existing tests and adapt coverage to the changed contract.
- Download the vendor's LFM2.5 1.2B Q4_K_M GGUF through Ollama. Do not change Composer/JS dependencies or the original environment. Compare two models sequentially on the same reduced implementation.

### Rejected alternatives

- More model-generated routing/tool calls: reintroduces latency and unreliable order arguments.
- Skipping scope classification on order keywords: a mixed prompt-disclosure request must still be refused.
- Hardcoding seeded business answers or order numbers: would hide the model's grounding defects and fail for other projects.
- Concurrent inference or merging before live verification: invalidates CPU timing or promotes known quality failures.

### Verification

Run focused Pest tests, Pint and diff checks. Use the existing isolated, transactional benchmark command for hours, Sunday, missing shipping price, order/items follow-up and refusal after business context, including repeated samples. Explicitly unload text/embedding models for cold samples; report warm and cold results separately. Inspect every live reply against actual knowledge/order data and record errors as failures. Finish with the full non-browser suite and update this plan with measured findings and the model decision.


### First hardening comparison and final adjustments

The first hardening matrix is retained in quality-*.json. At temperature zero, Qwen resolves Sunday and order-item follow-ups correctly, but omits closures from the general hours summary and does not explicitly acknowledge a missing exact fee. LiquidAI gives complete hours faster, but falsely refuses Sunday without topic context, translates on_its_way as processing and misspells an item name. Neither is accepted based on latency alone.

For the final rerun, provide only the latest customer topic to the responder for ambiguous follow-ups, keep full prior history out of ordinary classifier requests, request complete stated opening/closed days, and add a general missing-price example and exact-name rule. Keep the grounded answer at temperature zero. Preserve arbitrary accepted alphanumeric identifiers when explicitly marked as order numbers; do not fall back to an older number on a malformed current reference. Source sentence extraction is a bounded lexical heuristic, not a proof of semantic completeness. Preserve original excerpts when no useful lexical match exists. No model-selected claims are promoted to verified business data.

The first cold Qwen sample overlapped a short Pest run and is excluded from controlled cold reporting; the first warm Qwen request loaded the model after the LiquidAI cold sample and is also identified as a load/uncached sample. Final measurements run after tests with no other task-owned CPU workload.


### Live regression corrections

The second matrix (final-*.json; code hashes in final-code-sha256.json) exposed a regression: moving the current classifier message ahead of its history changed Sunday into out_of_scope/greeting. Restore the previously working background-then-labelled-current format only for contextual follow-ups; self-contained messages still omit history. Generic hours wording has no lexical overlap with "opens/closed", so add multilingual hours-term matching that separates store from support sentences without hardcoding hours. The unconditional on_its_way explanation also caused LiquidAI to invent a status on a missing-number query; include that explanation only when an actual scoped lookup returned that status. Retain these failed runs and rerun the identical matrix under verified-* filenames after tests.


### Compact responder prompt

The verified-* matrix confirmed deterministic order follow-ups and Sunday routing, but LiquidAI copied the refusal template for a valid hours question and Qwen omitted closures/failed to acknowledge an absent fee. Both are rejected for those cases. The scope classifier remains the enforced refusal boundary. For accepted intents use a compact evidence-only responder prompt, with native Spanish instructions for demo-es; keep the exact refusal template for bypassed out_of_scope requests. Internal disclosure remains prohibited and the leak guard remains active. Preserve all failed matrices. Run a compact-prompt probe across the same cases before repeating the full model comparison; if the small candidates still fail, evaluate the already installed quantized Qwen3 4B on this same reduced architecture.


### Route-specific responder instructions

The compact probe fixed missing-price replies in both models and produced correct LiquidAI hours/Sunday/order-item answers, but LiquidAI hallucinated store hours for a missing-order-number question. Scope responder instructions to the already resolved intent: order answers receive order/clarification rules only; business answers receive business/price/hours rules only; greetings get greeting rules. Preserve the compact probe, then repeat the probe and full matrix with distinct routed-* artifacts. This specializes prompts without adding generations or changing authorization/data scoping.


### Additional unavailable-fact check

The routed probe passed all nine checked LiquidAI replies; Qwen omitted closures in one hours reply. The earlier manager-name question was falsely rejected rather than grounded. Include staff/manager questions explicitly in the business classifier description and add that unavailable-name case to the repeated matrix. This checks a completely missing business fact as well as the missing express-shipping amount.


### Quantized 4B fallback and final regression safeguards

The completed routed matrix contains 38 turns (34 warm, 2 cold, 2 warm-up). LiquidAI gives complete hours in all three primary warm samples, but classifies the manager-name question as greeting and emits an internal REFERENCE_DATA marker. Qwen 1.7B rejects that business question and omits closures from primary warm hours. Neither tiny model passes the complete gate. Test the installed Qwen3 4B Q4_K_M on the same reduced flow and inference settings; preserve both tiny-model results.

Extend the existing leak guard/redaction dataset to cover the observed reference marker. Before handoff, preserve complete retrieved excerpts for compound questions so lexical narrowing cannot discard a second requested topic; cover this real information-loss case with a focused unit test. Those safeguards do not change valid single-topic answers already measured. Run formatting, full tests and the coverage gate before the 4B live matrix.


### Thinking-only tag diagnosis

The installed qwen3:4b digest 359d7dd4bcda is the thinking variant. Its template always appends <think> despite think=false. The isolated cold question took 167.80 seconds: the classifier returned 10 tokens, but the answer generated 1,026 tokens of visible reasoning before its reply. Stopped only the identified fallback runner and its PHP child; the connection's open transaction rolls back. Preserve completed fallback-* files; the matrix is incomplete and is not a valid steady-state speed comparison.

Official Ollama metadata identifies qwen3:4b-instruct as Qwen3-4B-Instruct-2507, 4.02B, Q4_K_M, approximately 2.5GB. The Hugging Face model card describes its non-thinking behavior. Download this distinct tag without replacing the existing thinking tag. Bound classifier output to 32 tokens and customer responses to 256 tokens, and redact any exposed thinking markers. Record the model-specific checkpoint and actual request options rather than assuming the generic 4b tag honors think=false.

Sources: https://ollama.com/library/qwen3:4b-instruct and https://huggingface.co/Qwen/Qwen3-4B-Instruct-2507.
