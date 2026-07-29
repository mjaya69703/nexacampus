You are an AI developer assistant for the NexaCampus project — a Laravel 12 + Livewire 4 university academic information system.

## FIRST STEP (MANDATORY)

1. **Read `AI_CONTEXT.md`**: At the start of every session, read `AI_CONTEXT.md` in the project root. This file contains the complete architecture, patterns, conventions, and code structure reference. DO NOT ask the user to re-explain the project structure.

2. **ALWAYS CONSULT GRAPHIFY FIRST**:
   - Check if `graphify-out/graph.json` or `graphify-out/GRAPH_REPORT.md` exists in the project root.
   - For any question, refactoring, feature implementation, or codebase exploration, ALWAYS read/query graphify first (`/graphify query "<question>"` or read `graphify-out/GRAPH_REPORT.md` / `graphify-out/graph.json`) to understand file relationships, dependencies, and architectural context before reading/editing code.

## KEY RULES

1. **Follow existing patterns exactly.** This project has established patterns for CRUD, PowerGrid tables, models, views, routes, and authorization. Always mirror existing code.

2. **Domain-based organization.** Code is organized by business domain (Academic, Financial, Admission, etc.), NOT by file type.

3. **Anonymous Blade Livewire components.** Views use `⚡` prefix files with inline PHP class + Blade template. Do NOT create separate Livewire component classes for CRUD pages.

4. **Session-based active role.** Use `ActivePermission::check()` for permission checks, NOT `auth()->user()->can()`. Use `@activecan` blade directives, NOT `@can`.

5. **UI Language: Indonesian.** All user-facing text (labels, flash messages, menu titles) must be in Bahasa Indonesia. Code (class names, variables, methods) stays in English.

6. **Config-driven CRUD.** New admin resources are registered in `config/resources.php` and auto-routed via `Route::crudLivewire()` macro.

7. **PowerGrid tables extend `BasePowerGridTable`.** Always use the base class and follow the established pattern (bulk actions, SweetAlert confirmations, permission checks).

8. **Activity logging.** All major models use `Spatie\Activitylog\Traits\LogsActivity` with `getActivitylogOptions()`.

9. **SoftDeletes.** All major models use `SoftDeletes`.

10. **Audit fields.** Include `created_by`, `updated_by`, `deleted_by` in all new tables and models.
