# Tech Stack Reference

This file is bundled into every project-specs skill at `references/tech-stack.md`.
It describes the mandatory technology stack that every project uses.

---

## Core Application Stack

| Technology | Role | Version |
|-----------|------|---------|
| Laravel | Backend framework, routing, ORM, queue, events | Latest stable |
| Gale | Server-driven reactive framework (includes Alpine.js) | Latest |
| Alpine.js | Client-side interactivity and DOM manipulation | v3 with plugins |
| PostgreSQL | Primary database (accessed through Laravel Eloquent) | Latest stable |
| Tailwind CSS | Utility-first styling framework | v4 |
| Flutterwave | Mobile money payment gateway | v3 |
| laravel-notification-channels/webpush | PWA push notifications | Latest |
| Spatie packages | Activity log, roles/permissions, honeypot, and more as needed | Latest |
| Laravel AI SDK | AI agent integration | Latest |
| Native PHP | Desktop and mobile native applications (when required) | Latest |
| Laravel Boost | Development acceleration framework | Latest |

---

## AI Tooling Stack

### MCP Servers

| Server | Purpose |
|--------|---------|
| Laravel Boost | Laravel development patterns and acceleration |
| Context7 | Stack documentation lookup for accurate syntax and API usage |
| Playwright | Browser automation for UI/UX and end-to-end testing |

### Plugins

| Plugin | Purpose |
|--------|---------|
| Laravel Simplifier | Code review and cleaning — run after all implementations, before testing |

### Skills

| Skill | Purpose |
|-------|---------|
| Gale | Server-driven reactivity patterns — consult for all controllers and blade files |
| UI Designer | UI/UX design standards — consult for all user-facing interface design |

---

## Stack Usage Rules

- The tech stack is non-negotiable. No alternative frameworks or libraries.
- Laravel Eloquent is the only database access method (no raw SQL unless absolutely unavoidable).
- Gale handles all server-driven reactivity; Alpine.js handles client-side DOM manipulation.
- Tailwind CSS is the only styling approach (no custom CSS unless unavoidable).
- Flutterwave is the only payment gateway (mobile money focused).
- Spatie packages are the standard for activity logging, role/permission management, and spam protection.
- Native PHP is the only option for desktop/mobile native apps (no Electron, no React Native).
- Laravel Boost and Context7 MCP servers are used extensively during implementation for code accuracy.
