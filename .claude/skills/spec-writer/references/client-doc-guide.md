# Client Document Generation Guide

This file is bundled into every project-specs skill at `references/client-doc-guide.md`.
It enables any AI agent to generate a professional, client-facing document on demand by
curating content from the skill.

---

## When to Generate

Generate a client document when the user asks for:
- A document to discuss with clients or stakeholders
- A proposal or scope document
- A non-technical summary of the project
- Something to present to a board, investor, or end user

The client document is an **on-demand export** — a snapshot of the skill's knowledge
curated for a non-technical audience. The skill remains the source of truth.

---

## How to Generate

1. Read SKILL.md for project overview, feature table, roles, and notification catalog
2. Read each feature file referenced in the table
3. Curate the content into the structure below
4. Save as `func-spec-client.md`

---

## Document Structure

```markdown
# {Project Name} — Functional Specification

> Version: 1.0
> Date: {date}
> Prepared for: {Client Name / Organization}
> Prepared by: {Author / Company}

---

## 1. Executive Summary
  One-paragraph project overview. The elevator pitch.
  Three to five key objectives.
  Who this is for.

## 2. Project Objectives
### 2.1 Problem Statement
### 2.2 Proposed Solution
### 2.3 Key Benefits
### 2.4 Success Metrics

## 3. User Roles
### 3.N {Role Name}
  - Who they are (plain language)
  - What they can do
  - What they cannot do

## 4. System Features

### Feature Overview

| Code | Feature | Module | Priority | Description |
|------|---------|--------|----------|-------------|
(Same codes as the skill — traceability across all project artifacts)

### 4.N {Feature Name}

#### What It Does
  User-perspective description.

#### How It Works (User Perspective)
  Narrative walk-through. "The teacher opens the gradebook, selects a student..."

#### Key Capabilities
  Benefits, not technical functionality.

#### Business Rules (Plain Language)
  Same rules as the feature file, without codes or technical precision.

## 5. Platform & Accessibility
### 5.1 Web Application
### 5.2 Mobile Experience (installable app, push notifications)
### 5.3 Language Support (English and French)

## 6. Design & User Experience
### 6.1 Design Approach
### 6.2 Works on All Devices
### 6.3 Light and Dark Themes

## 7. Notifications
  When users receive emails, push alerts, in-app messages — described as benefits.

## 8. Payment & Billing (if applicable)

## 9. Security & Privacy

## 10. Feature Priority Matrix
  (Pulled directly from SKILL.md feature table, simplified)

## 11. Project Phases & Milestones
  | Phase | Description | Key Deliverables | Timeline |

## 12. Assumptions & Dependencies

## 13. Out of Scope

## 14. Glossary

## 15. Sign-Off
  | Role | Name | Date | Signature |
```

---

## Writing Rules

1. **Same feature codes as the skill.** F-001 here = F-001 everywhere.

2. **No technical vocabulary:**
   - "Database" → "your data"
   - "Push notification" → "alert on your phone"
   - "Authentication" → "logging in"
   - "API integration" → "connected to [service]"

3. **Narrative user flows.** Clients think in stories, not scenarios.

4. **No acceptance criteria, no Given/When/Then.** Those are in the feature files.

5. **Priority matrix and phases are essential.** Clients need to see what comes when.

6. **Out of Scope is non-negotiable.** Prevents scope creep.

7. **Professional but warm.** May be shown to a board or investors.

8. **Never mention skills, MCP servers, plugins, or AI tooling.**

9. **Shorter than the combined feature files.** Cut depth, not breadth — every feature
   appears, but with less detail.
