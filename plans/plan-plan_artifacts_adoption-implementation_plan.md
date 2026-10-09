# Adopción de plan artifacts en el repo RAG

## Summary

Adoptar la metodología de *plan artifacts* de taskrunner: una guideline (`.ai/guidelines/plan-artifacts.md`) que Boost
compone en `CLAUDE.md`/`AGENTS.md`, más un directorio `plans/` versionado donde los agentes dejan el plan antes de
implementar trabajo no trivial. Se porta solo la metodología, no las herramientas de GovTribe.

## Context

- Taskrunner: guideline de ~45 líneas, `plans/` con 296 planes commiteados, scaffold `worktree_ops.py plan`, reglas de
  board y story points. Solo la guideline es la metodología; el resto depende de GovTribe/`gh`/Sail.
- Este repo: sin `.ai/guidelines` ni `plans/`; `boost.json` con `guidelines: true`; `composer.json:65` ejecuta
  `boost:update` tras cada `composer update`, por lo que el bloque de Boost nunca se edita a mano.
- Boost incluye "solo crear archivos de documentación si se piden" y "no crear carpetas base nuevas sin aprobación";
  la guideline los exceptúa solo para `plans/plan-*.md`.
- El plugin superpowers guarda planes en `docs/superpowers/plans/`; la guideline declara que `plans/` tiene precedencia.
- Revisado de forma adversarial en 3 iteraciones (Opus, Fable, Opus); hallazgos F1–F7 resueltos (F7 incorporado tras el gate).

## Implementation

0. Rama `feature/plan-artifacts-adoption` desde master limpio; todos los commits en la rama.
1. Crear `.ai/guidelines/plan-artifacts.md`: nombre `plans/plan-{topic_slug}-{plan_name}.md` (slug = rama sin `feature/`,
   guiones a guiones bajos, sin números de PR), búsqueda sin rama (`ls -t plans/ | head`, `grep -i`), excepción a las
   dos reglas de Boost, precedencia sobre skills de planificación, plantilla de cinco secciones.
2. Crear este archivo como primer plan.
3. `php artisan boost:update --no-interaction` como único escritor del bloque. Esperado: nuevo bloque
   `=== .ai/plan-artifacts rules ===` en ambos archivos. Si no recoge `.ai/guidelines/` o toca la sección escrita a
   mano, detenerse y reportar. El drift ajeno va en un commit aparte o se restaura completo.
4. Una línea de referencia a `plans/` en la sección escrita a mano de `CLAUDE.md`.
5. Commit en la rama; PR solo con aprobación.

## Rejected alternatives

- Portar `worktree_ops.py plan`: acoplado a GovTribe, `gh` y Sail.
- Reglas de board/story points: aquí no hay board.
- Números de PR/issue en el nombre: no existen antes del plan, no sirven como clave de búsqueda estable.
- Pegar el bloque a mano en `CLAUDE.md`/`AGENTS.md` si Boost falla: `composer update` lo borraría.
- Guardar planes en `docs/`: choca con superpowers y con la regla de no crear documentación sin pedirla.

## Verification

- `git status` lista solo la guideline, este plan, `CLAUDE.md` y `AGENTS.md`; cambios en `.agents/skills`,
  `.claude/skills`, `.ai/mcp` o `boost.json` se marcan y se tratan según el paso 3.
- `grep -c 'plan-artifacts rules' CLAUDE.md AGENTS.md` → 1 en cada uno; este plan contiene las cinco secciones.
- Idempotencia: un segundo `boost:update` deja `git diff --exit-code` limpio.
- `php artisan test --compact` en verde; Pint sin cambios.
- Comportamiento en worktree desechable desde el HEAD de la rama, después del commit: Claude Code con y sin
  superpowers, y Codex. (a) tarea no trivial → plan en `plans/`; (b) edición trivial → cita el motivo de omisión, sin
  plan; (c) pregunta de solo lectura → sin plan. Cualquier fallo, en especial un plan en `docs/superpowers/`, bloquea el
  PR hasta revisar la redacción y repetir, o hasta que el usuario acepte la limitación por escrito.
