# Feature File Guide (F-xxx.md)

Each feature in the project-specs skill gets its own markdown file in the `references/` folder,
named by its feature code: `F-001.md`, `F-002.md`, etc.

This is where the deep detail lives. An AI agent implementing F-007 reads ONLY `F-007.md` —
it doesn't need to load the entire project spec. This surgical loading is the whole point of
the skill architecture.

---

## File Template

```markdown
# F-{NNN} — {Feature Name}

> Module: {Module name}
> Priority: {Must-have | Should-have | Could-have}
> Precedence: {Comma-separated feature codes, or "None"}

---

## What It Does

{Clear, complete description of this feature from the user's perspective. What problem
does it solve? What capability does it provide? Written so someone with zero context about
the project can understand this feature in isolation.}

---

## Who Uses It

| Role | Interaction |
|------|------------|
| {Role} | {What this role does with this feature — view, create, edit, approve, etc.} |
| {Role} | {Interaction description} |

---

## User Scenarios

### Scenario 1: {Descriptive name — e.g., "Teacher creates a new class"}

- **Actor**: {Role}
- **Starting point**: {Where the user is when this begins}
- **Trigger**: {What initiates this flow}
- **Steps**:
  1. {User action} → {System response / what user sees}
  2. {Next action} → {Response}
  3. ...
- **Expected outcome**: {Final system state}
- **User feedback**: {What the user sees confirming success}

### Scenario 2: {Alternate path — e.g., "Teacher creates a class that conflicts with schedule"}

- **Actor**: {Role}
- **Starting point**: {Context}
- **Trigger**: {What initiates}
- **Steps**:
  1. ...
- **Expected outcome**: {What happens differently}
- **User feedback**: {Error message, warning, or alternative offered}

### Scenario 3: {Error/failure path — e.g., "Connection lost during class creation"}

- **Actor**: {Role}
- **Trigger**: {Failure condition}
- **System behavior**: {What the system does to protect data and inform the user}
- **Recovery**: {How the user gets back to a good state}

{Add as many scenarios as needed. Every feature should have at minimum:
one happy path, one alternate path, and one error/failure path.}

---

## Business Rules

| Code | Rule |
|------|------|
| BR-{NNN} | {Rule description — precise, unambiguous, complete} |
| BR-{NNN} | {Next rule} |

Business rules are the laws of the system. When two people disagree about behavior,
the business rule is the judge.

---

## Data Involved

{Describe what information this feature creates, reads, updates, or deletes.
Use plain language and relationships — no database columns, no schemas.}

**Creates:**
- {Entity/record description}: "{What it is, what it tracks, what it links to}"

**Reads:**
- {Entity/record}: "{What it needs and why}"

**Updates:**
- {Entity/record}: "{What changes and under what conditions}"

**Relationships:**
- "{Entity A} relates to {Entity B} in this way: {description}"

---

## Acceptance Criteria

{Conditions that must be true for this feature to be considered complete.
Given/When/Then format makes these directly translatable to tests.}

- **Given** {precondition}, **when** {action}, **then** {expected result}
- **Given** {precondition}, **when** {action}, **then** {expected result}
- ...

---

## Verification Steps

{Ordered list of concrete, browser-testable actions that an automated tester
will execute to verify this feature works. Each step is a single action paired
with its expected visual result.

Format: "Action description → Expected visual result"

These steps are the primary input for the feature-tester agent. Write them as
if instructing someone who has never seen the app and doesn't know the codebase.
Every acceptance criterion must be covered by at least one verification step.

Target: 3-7 steps per feature. If you need more than 7, the feature should
be split into smaller features.}

1. {Action} → {Expected visible result}
2. {Action} → {Expected visible result}
3. {Action} → {Expected visible result}

---

## Edge Cases & Exceptions

| Condition | Expected Behavior |
|-----------|------------------|
| {What if X happens?} | {System response} |
| {What if Y fails?} | {Fallback behavior} |
| {Unusual condition} | {How the system handles it} |

---

## Notifications Triggered

| Code | Event | Channel | Recipient | Content |
|------|-------|---------|-----------|---------|
| N-{NNN} | {What triggers it} | {Push/Email/In-app} | {Who receives} | {Brief message} |

(Reference the notification catalog in SKILL.md. Only include notifications relevant
to this feature.)

---

## UI/UX Notes

{Describe what the user interface looks like and how the user interacts with it.
Narrative descriptions, not wireframes or component specs.}

- Screen layout and key elements
- Interactive behaviors (what happens on click, hover, submit)
- Responsive considerations
- Theme (light/dark) considerations if relevant

---

## Related Features

| Code | Feature | Relationship |
|------|---------|-------------|
| F-{NNN} | {Name} | {How it relates — depends on, feeds into, shares data with} |
```

---

## Writing Principles

1. **Self-contained**: Someone reading F-007.md should understand this feature completely
   without reading any other feature file. Context from SKILL.md (project overview) is
   assumed, but no other feature file should be required reading.

2. **No code. Ever.** Describe behavior, not implementation. "The system validates that the
   email is unique and properly formatted" — not `required|email|unique:users,email`.

3. **Scenarios are the core value.** The happy path is obvious. The alternate paths and error
   paths are where bugs and misunderstandings live. If you only write the happy path, the
   implementer will guess on everything else — and guess wrong.

4. **Business rules are numbered.** BR-001, BR-002... These are traceable across the project.
   When a question arises about "should the system allow X?", someone points to BR-042 and
   the answer is there.

5. **Data as relationships.** "A Student has many Enrollments, each linking to one Course"
   tells the implementer everything they need to design the schema. Dictating column types
   in a spec creates false precision.

6. **Acceptance criteria are testable promises.** Given/When/Then translates directly to
   automated tests. They're the contract between spec and implementation.

7. **Keep each file under 200 lines.** If a feature file exceeds 200 lines, it MUST be split
   into sub-features with their own codes. The tighter limit forces surgical focus.

8. **Keep features surgically focused.** If a feature description starts requiring "and" or
   lists multiple distinct behaviors, split it into separate features. Each F-xxx should
   describe ONE testable unit of functionality. "User Registration & Authentication" is two
   features, not one. CRUD operations are always separate features.

9. **Verification steps are the testing contract.** The Verification Steps section is what
   the automated tester executes. Each step must be concrete enough that someone with zero
   context can perform it in a browser. "User can create a task" is too vague. "Type 'Buy
   groceries' in the quick-add field and press Enter → New task 'Buy groceries' appears at
   the top of the task list" is testable. Every acceptance criterion must map to at least
   one verification step.

10. **Feature type is mandatory.** Every feature must be classified as one of: `foundation`
    (infrastructure setup), `functional` (core business logic), `edge-case` (error handling
    and validation), or `polish` (responsive, dark mode, accessibility, animation). Add the
    type to the feature header metadata after Precedence:
    `> Type: functional`
