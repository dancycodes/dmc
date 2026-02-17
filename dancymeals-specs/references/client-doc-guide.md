# Client Document Generation Guide

Generate a client document when the user asks for a document to discuss with clients,
stakeholders, investors, or a non-technical audience.

## How to Generate

1. Read SKILL.md for project overview, feature table, roles, and notification catalog
2. Read each feature file referenced in the table
3. Curate the content into the structure below
4. Save as `func-spec-client.md`

## Document Structure

```markdown
# DancyMeals — Functional Specification

> Version: 1.0
> Date: {date}
> Prepared for: {Client Name}
> Prepared by: {Author}

## 1. Executive Summary
## 2. Project Objectives (Problem, Solution, Benefits, Success Metrics)
## 3. User Roles (plain language, what they can/cannot do)
## 4. System Features (overview table + narrative per feature)
## 5. Platform & Accessibility (Web, Mobile PWA, Language Support)
## 6. Design & User Experience (Responsive, Themes, Dark Mode)
## 7. Notifications (when users receive alerts, described as benefits)
## 8. Payment & Billing (Flutterwave, mobile money, commission model)
## 9. Security & Privacy
## 10. Feature Priority Matrix
## 11. Project Phases & Milestones
## 12. Assumptions & Dependencies
## 13. Out of Scope
## 14. Glossary
## 15. Sign-Off
```

## Writing Rules

1. Same feature codes as the skill (F-001 = F-001 everywhere)
2. No technical vocabulary ("push notification" = "alert on your phone")
3. Narrative user flows, not scenarios
4. No acceptance criteria or Given/When/Then
5. Priority matrix and phases are essential
6. Out of Scope prevents scope creep
7. Professional but warm tone
8. Never mention skills, MCP servers, plugins, or AI tooling
9. Shorter than combined feature files — cut depth, not breadth
