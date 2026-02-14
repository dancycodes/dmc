# General Implementation Concepts

This file is bundled into every project-specs skill at `references/general-concepts.md`.
These are non-negotiable standards that apply to every feature in every project.

Any AI agent implementing a feature should read this file alongside the feature's reference
file to ensure all standards are respected.

---

## 1. UI Design

All user interfaces are designed using the **UI Designer skill**. No exceptions.

Every screen, every component, every interaction must be:
- **100% responsive** across all device sizes (mobile-first approach)
- **Light and Dark mode** supported throughout the entire application
- **Theme switcher** accessible in the UI, allowing users to toggle between modes
- **Accessible** — WCAG 2.1 AA compliance as a baseline

All blade files must consult both the **UI Designer skill** and the **Gale skill** for
proper patterns and component architecture.

---

## 2. Code Review

All code must pass through the **Laravel Simplifier plugin** after implementation is
complete and before any testing begins.

This is a mandatory gate. No code enters the testing phase without passing code review.
Address all findings from Laravel Simplifier before proceeding.

---

## 3. Localization

**English is the default language** for every project.

Every piece of user-facing text — labels, messages, buttons, tooltips, error messages,
placeholders, everything a user can read — must be placed in localization helpers.
No hardcoded user-facing strings anywhere in blade files or controller responses.

**Convention**: Use the `__()` helper for all translatable strings. Maintain an `en.json`
file for all translations.

**Language support**: English and French. Implement a **language switcher** that allows
users to toggle between English and French from the UI.

---

## 4. Progressive Web App (PWA)

**Every web application is a PWA.** This is not optional.

Requirements:
- **Offline behavior**: When offline, display a dedicated offline page. No offline data
  functionality — just a clear, friendly "you're offline" page.
- **App installation**: Support the browser's native install prompt AND provide an in-app
  UI element (button or banner) that prompts users to install the app.
- **Push notifications**: Implement push notification support via the webpush package.
  Include a UI prompt asking users for notification permission. Provide a notification
  preferences section in user settings where users can manage what they receive.
- **Web app manifest**: Proper icons, theme colors, display mode, and app name.
- **Service worker**: Handle offline fallback page and push notification receipt.

---

## 5. Code Implementation Patterns

- **All blade files** must consult the **UI Designer skill** and the **Gale skill**
- **All controller files** must consult the **Gale skill**
- **Laravel Boost MCP** and **Context7 MCP** servers must be used extensively during
  implementation to ensure code accuracy for all technologies in the stack
- Follow Laravel conventions strictly: resource controllers, form requests, policies,
  events and listeners
- Use a service layer for complex business logic

---

## 6. Testing

Testing follows a strict two-phase approach. Both phases are mandatory.

### Phase 1: Playwright MCP Testing
- Cover every user-facing flow described in the feature specifications
- Test all UI/UX interactions: navigation, forms, buttons, modals, responsiveness
- Test complete functionality: happy paths, alternate paths, error paths
- **100% pass rate is required** before moving to Phase 2
- Failures must be fixed and retested until 100% is achieved

### Phase 2: Pest Feature Testing
- Feature tests for all endpoints
- Business logic validation against the business rules in the spec
- Edge case coverage for all documented edge cases
- Data integrity checks

---

## 7. Native Applications

When a project requires desktop or mobile applications:

- Use the **most recent version of Native PHP**
- Use **Context7 MCP** for accurate, up-to-date documentation reference
- Install **all dependencies** exactly as specified in the official documentation
- Follow platform-specific guidelines for each target platform
- Ensure feature parity with the web application unless explicitly scoped otherwise
