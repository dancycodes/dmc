# Project-Specs SKILL.md Template Guide

This guide defines the structure and content of the SKILL.md file generated inside
each project-specs skill. This is the "brain" of the project — the file that's always
in context when the skill is triggered.

It must stay under 500 lines to respect the progressive disclosure principle. All deep
detail lives in the feature reference files. SKILL.md is the map, not the territory.

---

## Template

```markdown
---
name: {project-name}-specs
description: >
  Functional specification skill for {Project Name}. This skill is the authoritative reference
  for all functional requirements of {Project Name}. Consult this skill whenever implementing,
  reviewing, testing, or discussing any feature of {Project Name}. It knows exactly how every
  feature should work and what the business rules are. Use it for: implementing features (read
  the feature file before coding), checking requirements (verify behavior against the spec),
  and understanding project scope (read the overview and feature catalog).
---

# {Project Name} — Functional Specification

> Version: 1.0
> Date: {date}
> Status: {Draft | Approved}
> Project Type: {e.g., School Management System}

## Project Overview

### Purpose & Vision
{One clear paragraph: what this software exists to do, the core problem it solves.}

### Target Users
{Who uses it, who benefits, who pays for it. Brief description of each audience.}

### Success Criteria
{How we know this project succeeded. Measurable outcomes.}

### Scope Boundaries
{What is in scope. What is explicitly out of scope. Why.}

---

## Tech Stack

The mandatory technology stack for this project. See `references/tech-stack.md` for
full details, versions, and configuration notes.

| Technology | Role |
|-----------|------|
| Laravel (latest) | Backend framework |
| Gale | Server-driven reactivity |
| Alpine.js v3 | Client-side interactivity |
| PostgreSQL | Database (via Eloquent) |
| Tailwind CSS v4 | Styling |
| Flutterwave v3 | Payment gateway (mobile money) |
| Webpush | Push notifications |
| Spatie packages | Activity log, roles/permissions, honeypot |
| Laravel AI SDK | AI integration |
| Native PHP | Desktop/mobile (if applicable) |
| Laravel Boost | Development acceleration |

AI Tooling: Laravel Boost MCP, Context7 MCP, Playwright MCP, Laravel Simplifier plugin,
Gale skill, UI Designer skill.

For complete tech stack details → `references/tech-stack.md`

---

## General Concepts

Non-negotiable standards that apply across all features. See `references/general-concepts.md`
for full details.

- **UI**: 100% responsive, light/dark mode + theme switcher, UI Designer skill for all designs
- **Localization**: English default, French, all text in translation helpers, language switcher
- **PWA**: All web apps, offline page only, install prompt, push notifications
- **Testing**: Playwright MCP first (100% pass), then Pest feature tests
- **Code Review**: Laravel Simplifier before testing
- **Native Apps**: Latest Native PHP + Context7 MCP (when applicable)

For complete implementation standards → `references/general-concepts.md`

---

## Feature Catalog

This table is the heart of the specification. Every feature has a unique code that is
consistent across all references, discussions, and implementation tasks.

**How to use this table:**
- To implement a feature → read its reference file first
- To check precedence → ensure all listed features are `done` before starting
- To track progress → update the Status column as work proceeds

| Code | Feature | Module | Priority | Precedence | Reference |
|------|---------|--------|----------|-----------|-----------|
| F-001 | {Feature name} | {Module} | {Must/Should/Could/Won't} | — | `references/F-001.md` |
| F-002 | {Feature name} | {Module} | {Priority} | F-001 | `references/F-002.md` |
| F-003 | {Feature name} | {Module} | {Priority} | F-001, F-002 | `references/F-003.md` |
| ... | ... | ... | ... | ... | ... |

**Priority (MoSCoW):**
- **Must-have** — Project cannot launch without this
- **Should-have** — Important, expected by users, included if timeline allows
- **Could-have** — Enhancement, can be deferred
- **Won't-have** — Acknowledged but not in this version

**Precedence** — Comma-separated feature codes that must be `done` before this feature
can begin. This creates an automatic implementation order.

---

## Notification Catalog

All system notifications across features. Individual feature files reference these codes.

| Code | Trigger Event | Channel(s) | Recipient(s) | Content Summary |
|------|--------------|------------|--------------|-----------------|
| N-001 | {Event} | {Push/Email/In-app} | {Role(s)} | {Brief description} |
| ... | ... | ... | ... | ... |

---

## User Roles Summary

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| {Role} | {Who they are} | {What they can do — brief} |
| ... | ... | ... |

For detailed permission matrix → see individual feature files (each specifies which roles
interact with it and how).

---

## How to Use This Skill

### Implementing a Feature
1. Check the Feature Catalog for the feature you're working on
2. Verify all features in the Precedence column are `done`
3. Read the feature's reference file (e.g., `references/F-007.md`)
4. Read `references/general-concepts.md` for implementation standards
5. Read `references/tech-stack.md` for technology details
6. Implement according to the spec

### Checking Requirements
Read the specific feature file. It contains user scenarios, business rules,
acceptance criteria, and edge cases — everything needed to verify correctness.

### Generating a Client Document
Read `references/client-doc-guide.md` for instructions on curating this skill's
content into a professional, client-facing markdown document.

### Understanding Project History
Read `references/spec-state.json` for the full history of interview rounds,
research performed, user decisions, and scope confirmations.
```

---

## project-claude-md.md Template

This file is produced alongside the spec skill and contains content that the project-executor
copies into the project's CLAUDE.md during initialization. It ensures every agent session
automatically has tech stack and implementation standards loaded without any per-feature cost.

This is the key to token-efficient execution: instead of embedding these standards into every
Mission Directive (per-feature cost), they live in CLAUDE.md (one-time cost, auto-loaded by
Claude Code for every session).

### Template

```markdown
## {Project Name} — Implementation Reference

### Tech Stack

{Concise adaptation of tech-stack.md content. Same information, formatted for always-loaded
CLAUDE.md context. Include the technology table, exclusions table, AI tooling, and usage rules.
Target: ~40-50 lines max.}

### Implementation Standards

{Concise adaptation of general-concepts.md content. Same information, formatted for always-loaded
CLAUDE.md context. Focus on non-negotiable rules: UI/UX standards, reactivity framework, data
architecture, testing standards, code standards. Target: ~50-60 lines max.}

### Spec Skill Reference

This project's functional specification lives in the `{project-name}-specs` skill.
- To see all features: invoke the spec skill (reads SKILL.md with feature catalog)
- To read a specific feature: read `references/F-xxx.md` from the spec skill
- Tech stack details: `references/tech-stack.md`
- Implementation standards: `references/general-concepts.md`

### Available Skills

| Skill | When to Use |
|-------|-------------|
| gale | Every controller method and blade file (reactive framework) |
| ui-designer | Every user-facing interface (design system) |
| laravel-simplifier | Code review phase (Laravel standards) |
| {project-name}-specs | Feature requirements lookup |
```

### Writing Notes for project-claude-md.md

- Keep the total under 120 lines — this loads on EVERY agent session
- Adapt content from tech-stack.md and general-concepts.md — don't just copy verbatim
- Focus on rules and constraints, not explanations
- Include the spec skill reference so agents know where to find feature details
- List all available skills with their trigger conditions

---

## Writing Notes

- Keep SKILL.md under 500 lines. The feature table can be long for large projects —
  that's fine, it's a table not prose.
- All deep detail lives in feature files. SKILL.md is the index.
- The description in the YAML frontmatter is critical — it's what triggers the skill.
  Make it specific to the project and include concrete trigger phrases.
