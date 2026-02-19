# Mission Directive: F-122 -- Meal Component Requirement Rules

- **Spec Skill**: dancymeals-specs
- **Feature File**: references/F-122.md
- **Project Dir**: C:/Users/pc/Herd/dmc
- **App URL**: https://dmc.test
- **Test Mode**: full
- **Gate Scripts**:
  - gate_implement: C:/Users/pc/.claude/skills/project-executor/scripts/gate_implement.py
  - gate_review: C:/Users/pc/.claude/skills/project-executor/scripts/gate_review.py
  - gate_test: C:/Users/pc/.claude/skills/project-executor/scripts/gate_test.py
- **Available Plugins**: laravel-simplifier: true
- **Retry Context**: Previous agent was interrupted mid-IMPLEMENT (no report saved). Partial code exists on the branch:
  - NEW: ComponentRequirementRuleController, ComponentRequirementRule model, ComponentRequirementRuleService, factory, migration, _requirement-rules.blade.php
  - MODIFIED: MealController, MealComponent model, MealComponentService, lang/en.json, lang/fr.json, _components.blade.php, routes/web.php
  - Review ALL existing code for completeness and correctness before continuing. Complete any missing pieces, then proceed through all 3 phases.
