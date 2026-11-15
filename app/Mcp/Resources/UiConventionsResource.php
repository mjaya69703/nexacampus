<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

#[Description('NexaCampus UI conventions that every React/Inertia rewrite must follow: button sizing rules, dark-mode architecture, card classes, theme colors, and language rules. Read this before generating any UI code.')]
class UiConventionsResource extends Resource
{
    protected string $uri = 'docs://nexacampus/ui-conventions';

    protected string $mimeType = 'text/markdown';

    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
        # NexaCampus UI Conventions (mandatory for React rewrite)

        ## Theme colors
        - `--app-primary: #6842f4` (primary purple), `--app-primary-bright: #8b6cff`, `--app-primary-deep: #3f249c`
        - Page background lavender `#f7f3ff`, surfaces white. Dark mode is handled globally via `[data-bs-theme=dark]` in CSS.

        ## Button & sizing rules (ANTI-OVERSIZE & ANTI-MINI)
        - NEVER use oversized elements (`btn-lg`, `form-control-lg`, giant paddings/fonts). The user dislikes bloated design.
        - NEVER use `btn-sm` or mini buttons carelessly on navbars/toggles/main actions. Use standard proportional, compact, elegant sizes.
        - Styles: primary action `btn-primary`, outline `btn-ghost-primary`, cancel `btn-secondary`, delete `btn-danger`.

        ## Dark-mode architecture (STRICT)
        1. NEVER hardcode bright backgrounds (`bg-white`, `bg-light`, `style="background:#fff"`) on containers/cards without a matching `[data-bs-theme=dark]` override — they blind in dark mode.
        2. GLOBAL FIRST: dark-mode colors live centrally in the global stylesheet. Do not scatter per-component dark-mode blocks.
        3. Reuse theme-aware card classes (`.card`, `.modern-card`, `.stat-card`, `.course-card`, `.service-card`, `.finance-widget`, ...) instead of inventing new surfaces.
        4. New custom classes MUST ship with a `[data-bs-theme=dark]` override block next to them.

        ## Other rules
        - Icons: Font Awesome 6 (`fas fa-*`).
        - Confirmation dialogs: SweetAlert2 pattern (warn → confirm → dispatch).
        - Flash messages via session (`success/error/warning/info`).
        - UI language: Bahasa Indonesia for all labels/messages. Code: English identifiers.
        MARKDOWN);
    }
}
