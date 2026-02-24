# PREAMBLE
You should assume my proposal is knowing only 10% of what I want to implement. The remaining 90% is for you to truly understand the need in every dimension with deep context and understanding and adequate modern claude code research. Start by supply unhinged feedback and complete system proposal (not plan yet) that will fully model our needs and we go from there! At the end, it is your responsibility to cater for every single aspect that makes this proposal the origin of a workld changing idea.

# CONTEXT
To deeply understand what we want to achieve, you need to deep dive into every aspect of how:
1) The spec-writer skill works to produce project specs (e.g dancymeals-specs for this project).
2) The project-executor skill fully implements project specs produced by spec-writer skill (e.g dancymeals-specs for this project) with parameters:
a) using .executor folder as workspace with SQLite database for robust state management
b) implementing gates for rigour
c) using state-manager local mcp for managing project progress and implementation
d) using feature-agent agent to implement each feature of the project specs
3) The project-finalizer skill works after the project-executor skill to polish, fix bugs, and finalize implementation for 100% production ready and enterprise-grade solution

# PROBLEM STATEMENT
Though robust, quite complete and most importantly yielding great results, the existing system has the following flaws:
## Tokens consumption and Speed 
Each feature agent consumes a considerable amount of tokens following all the implementation-review-test steps. It is also extemely slow! For a feature as simple as logout functionality, the feature may spend 10-15 minutes! That's outrageous, unacceptable and useless!
## Odd Feature Implementation Order
In general the feature implementation order is great! But there are cases where it truly doesn't make sense. Example on dancymeals-specs - Theme switcher + language switcher + pwa have been implemented before the Base Layout comes to play. To me it doesn't make sense because they're all elements of the base layout.
## Rigid
The process approach is not flexible/intelligent enough to adapt to features and do them straight to the point then test and move on
## Fixed stack
The tech and ai stacks are fixed and will be hard to change if this system were to be used with other technologies
## AI slop
With all the robust gates and checks, we still get ai slop at ui and implementation levels, though greatly mitigated. 
## I AM SURE YOU'LL FIND WAY MORE PROBLEMS THAN I DID

# SOLUTION (OUR TASK)
We are to implement one system called DancyDev that absorbs all of this existing system into ONE SKILL called dancydev and ONE LOCAL MCP SERVER called dd-manager (standing for DancyDev Manager). The goal is to kill the over-engineered existing system, learning from all what it does best, creatively add what is missing, and derive a new super simplified system that does just what it is supposed to do but does it well and perfectly tokens and time fully optimised. This system emulates a human developer working on a full project - I design my specs and implement each feature thereof one after the other in a logical manner in the perfect awareness of my tech stack and strict coding patterns. During implementation; I understand the task in the context of all that's to be done, write code for it, test that the code works, iteratively fix and retest bugs if it doesn't until all works well, the move to the next task till project completion with well targeted and logical iterative regression fix and tests on previous task that may be affected by current task.  

# GENERAL CONCEPT OF DANCYDEV SYSTEM
The DANCYDEV SYSTEM autonomously runs (without stop until all task is done) like a solo developer to fully implement a new app or add to / complete an existing app (FULL STACK)

## Laws
- The current directory in which claude is called is considered the project directory where dancydev is to work!
- Always ensure the .dancydev folder exists in the root of the current working folder
- Just like project-executor and project-finalizer skills, once /dancydev is called to run the dancydev skill, it should not stop until it has completed all its work! Only "/dancydev stop" or "/dancydev quit" should make hime smoothly complete what he's currently working on and stop at a nice continuable spot.
- Depending on the user's description in calling "/dancydev {description}", the skill should auto determine what phase to activate and actions to take without ever jeoperdizing the status quo of project progress.
- Everything must be put in place to ensure smooth continuation from abrupt stops or interuptions that happen quite often.
- Automated Tests with Codes should be only for non-ui related (backend only) features. THIS IS A NON NEGOCIABLE!! DO NOT MESS AROUND WITH THIS!! 
- The only allowed tool for testing ui-related features is Playwright MCP server! If it is not connected or available, everything should stop and the user should be asked to connect it. NO WORK AROUNDS! NON NEGOCIABLE!! ONLY THE PLAYWRIGHT MCP TOOL SHOULD BE USED FOR UI-RELATED FEATURES TESTS!!
- Let me repeat this: Just like the project-finalizer skill, using code automation tests (pest for example) is highly forbidden except for Backend only features! The only test solution allowed is Playwright MCP that should have all the rigour expressed in project-finalizer skill without verbose process. 
- Laravel simplifier is not a skill, it is a plugin agent
- use php.bat, herd.bat, pint.bat, and composer.bat in bash tools rather than php, herd, pint, and composer respectively

## Usage
The system is accessed via the dancydev skill:
- /dancydev: should read current dancydev state and smoothly continue form where it ended
- /dancydev continue: same as /dancydev
- /dancydev {description}: intelligently understands what the user is requesting for, and depending on the current dancydev state knows exactly what to do to proceed with user request
- /dancydev stop: When sent by the user while process is running, there is an understanding to stop at the next stoppable spot suitable for smooth progress or continuation
- /dancydev quit: same as /dancydev stop

## Progress Management
At first launch of the dancydev system, a .dancydev folder has to be created at the root of the current project! Within it just one file!! You got me right!! Just one file! That one file manages everything that has to do with the DancyDev System. That one file is an SQLite Database file that is accessed for CRUD operations only through the dd-manager mcp server. This is the only file the DancyDev System relies on for progress management throughout it's life span. The database must be correctly structured to cater for all the needs of complete and methodic project implementation and feature updates in future. The mcp server must rightly implement all the methods necessary to manipulate and effectively obtain all that's required from this database with clean validation that protects any form of AI corruption/slop. Avoid methods with responses that eat up tokens uselessly. This file is the sole source of truth for everything!

## Phase I - Specs
With no .dancydev folder and corresponding db file (or depending on state of existing db file), we are compulsorily in a new project. The user must there either use "/dancydev {description}" so the description of what he's trying to achieve is clear, or be prompted to give clear description of what he wants to achieve if he simply runs "/dancydev".
Obviously Phase I is all about interactive discussion with the user to capture the essence of what they're trying to achieve. The spec-writer skill does such an excellent job at that (especially the user knowing only 10% concept), it is mind blowing! We need to emulate that in the dancydev skill. The issues to correct at this level however are:
1) The outcome will no longer be a skill, but rather the updating of the db file (in consciousness of the existing) in .dancydev folder. I'll allow you to figure out the right structure for the outcome in the db file, but can only further give you functionalities
2) The existing general concepts and tech stack that are indicated in the spec-writer skill should be maintained as defaults. This should however be applied only in the case where the user did not explicitly change them. The dd-manager mcp server should have methods to extract these data from the database.
3) During the discussion, if the find-skills skill is available, use it to further enhance the ai stack of the project! Be very careful however never to stray from the existing ai and tech stack!! Or at least ask the user! Do not also over saturate to eat up tokens / confuse
4) The CLAUDE.md file content should be updated! But ensure best practices for it! It does no have to be too large
5) The slop at the level of reactivity (frontend and Backend alike) is too high! There needs to be a clear definition of what reactivity TRULY is and enforce true reactivity for all our apps irrespective of the technology used (gale, livewire, HTMX, react, svelte, vuejs). The choice of the technology (default gale via the gale skill) should then be used to strictly implement reactive rules app wide.
6) We need to implement the best algorithme for determining feature dependency and next feature to implement. This is crucial for a successful implementation
7) The work done by spec-writer skill for each spec file is mind blowing, as you can tell from an example like dancymeals-specs. Now however we are in the db file! The structure needs to be up to standard. It'll no longer bé subagents implementing this, but the main Claude agent. So he always need to to know what is his current project and what is it all about in great details. This has to be well-done to survive several compactions and avoid hallucination/slop. For features testable by Playwright MCP, we need a way to ensure that playwright Mcp have effectively tested each scénario, business rule, and edge case after implementation, ensuring all passes and bugs fixed before Moving to the next step. The structure and process needs to be on point with unnecessarily killing speed and tokens. I count on you for clean structure.
8) Features generated have to cater for project-finalizer skill type phase inherently! We will be having just one implementation phase to cover the whole app and the rigour has to cater for what project-finalizer skill does inherently.
9) every feature should have constraints field that must be read. This will contain instructions like always use ui-designer and gale skills to implement blade files (in addition to other dependencies that will be determined during interaction with user), always use gale skill when implementing controllers to ensure reactivity
10) At the end of spec generation, there needs to be a critical reevaluation of what has been produced to iteratively confirm completeness! During this phase existing specs can be modified and others added
11) logo found in public images/logo.png must be a norm

This phase is the most important phase! Do not hallucinate or slop this, otherwise the whole project is doomed.

## Phase II - Implementation
1) In the DANCYDEV system, the orchestrator is the solo developer that progressively implements his project from beginning to end. He has access to all tools, all skills, all plugins, all mcp to diligently fulfill all tasks. You must do deep research to make this a reality! Plan modes work because files are written that survive context compaction or context clearing! The same notion should work for us.
2) Due to this, things must be set up to survive compaction! Strong anchors (without token waste) must be implemented to get the orchestrator up and running in compaction or after interruption
3) The orchestrator should handle one feature at a time!! and ensure to always adequately update db state via dd-manager before proceeding. If at all the orchestrator implements several features simultaneously, it must ensure to test each feature's business rules, scenarios, and edge cases and ensure they're all working fine, then update their status before proceeding! I trust you for this 
4) A common errors encountered table should be available in the db with methods to add, update, and read from it in the dd-manager mcp. This is a global hub preventing same occurring errors to waste time and tokens in debugging
5) each feature should store an implementation summary before being completed
6) Only iterative playwright mcp testing is allowed especially for ui-related features!! DO NOT WRITE CODES FOR TESTS except for backend only features. 
7) No skips as to the scrupoulous respect of tests imposed by the specs on each feature!
8) During implementation, if useful feature is lacking from the db, it should be smoothly addable as per existing standards

# OVERALL EXPERIENCE
Listen we've said a lot of things above to express the accuracy we want in outcome and ease to adapt to any project. The existing system + extended research you have to do gives you great context. The summary of it all:
We simply want to automate the repeatitive process of asking ai to implement x, then testing to tell him what is wrong, then reminding him what stack/skills to use, then... We however don't want to overengineer it and burn outrageous time and tokens! We want the same straight forward speedy execution (understand, code, test, fix and retest, check if potential previous is broken, and proceed to next) without hallucination and slop with robust testing.

# OUTCOME
Production Ready and Enterprise Grade App that respects tech stack norms.


