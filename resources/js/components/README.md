# Inertia component structure

- `Home/` — components for public-facing pages and the public academic hub.
- `Admin/` — administrator-only components.
- `Student/` — student portal components.
- `Lecturer/` — lecturer portal components.
- `Shared/` — components intentionally shared across application areas.

Keep role-specific components inside their domain. Move a component to `Shared/` only when it has no role-specific behavior.
