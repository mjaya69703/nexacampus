# Controller structure

- `Home/` — public homepage and public-facing pages.
- `Admin/` — administrator workflows and exports.
- `Student/` — student-facing workflows.
- `Lecturer/` — lecturer-facing workflows.
- `Academic/` — academic controllers shared by operational modules.
- `Shared/` — controllers with no single role or area ownership.

`Controller.php` remains the base framework controller. Keep role-specific controllers inside their domain namespace.
