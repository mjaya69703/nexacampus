---
name: role-review
description: "Use when: review dari sudut dosen/mahasiswa/admin/dev or when asked to assess features from a role perspective. Trigger: review dari sudut {role}."
---

# Role Review Skill

When the user asks for a role-based review (e.g., "review dari sudut dosen"), respond with a checklist per role.

## Review Priority
When reviewing pages, menus, or role-specific screens, review the visible experience first:
1. UI layout, hierarchy, wording, density, navigation, scanability, and role fit.
2. Workflow logic that supports the visible UI.
3. Code structure and implementation details.

If the user asks to execute the review, prioritize edits in the Blade/HTML/CSS portion of the relevant views. Change backend logic only when the UI improvement requires it.

## Roles
- Dosen
- Mahasiswa
- Admin Akademik
- Developer/QA

## Output Format
For each role, provide a short checklist:
- [ ] Item ...
- [ ] Item ...

Include severity hints in parentheses when needed: (kritikal), (tinggi), (sedang), (rendah).

## Notes Logging
If a review is delivered, append a brief summary to .notes/review.md with:
- Date
- Role
- Top gaps or risks (1-3 bullets)
- Suggested next steps (1-2 bullets)
