<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `bun run build`, `bun run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `bun run build` or ask the user to run `bun run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>


---

# Git, Feature Branch, Pull Request & Implementation Progress Workflow

These Git workflow rules are mandatory for all implementation work unless the user explicitly overrides them.

## 1. Branch Hierarchy

The permanent branches are:

* `main` — production/release branch
* `dev` — development integration branch

All feature work MUST use `dev` as its base branch.

Codex must NEVER:

* create feature branches from `main`;
* open feature PRs against `main`;
* push implementation commits directly to `dev`;
* push implementation commits directly to `main`;
* merge `dev` into `main`;
* merge a feature branch into `main`;
* force-push `dev`;
* force-push `main`.

The user is solely responsible for eventually merging:

`dev` → `main`

Codex must not perform that operation.

---

## 2. Implementation Progress File

The repository contains:

`IMPLEMENTATION_PROGRESS.md`

This file is the durable implementation execution ledger.

Codex MUST read `IMPLEMENTATION_PROGRESS.md`:

* at the beginning of every implementation session;
* before selecting a new feature;
* after synchronizing `dev`;
* after a feature PR is merged;
* whenever implementation state appears inconsistent.

Conversation history is NOT the authoritative record of implementation progress.

`IMPLEMENTATION_PROGRESS.md` is authoritative only after its state has been verified against Git and GitHub.

Before starting new work, reconcile it with:

* the implementation plan;
* local branches;
* remote branches;
* merged/open GitHub PRs;
* current `origin/dev`.

Do not blindly trust stale progress values.

---

## 3. Progress Statuses

Use these feature statuses:

`PENDING`

`IN_PROGRESS`

`PR_OPEN`

`MERGED`

`BLOCKED`

### PENDING

Implementation has not started.

### IN_PROGRESS

The feature branch exists and implementation is underway.

### PR_OPEN

The feature PR exists and has not yet been verified as merged.

### MERGED

The PR has been verified as merged into `dev`.

### BLOCKED

The feature cannot safely continue because of an unresolved requirement, dependency, repository issue, failing required check, infrastructure failure, or required human action.

Do not mark a feature `MERGED` until GitHub confirms that the PR merged into `dev`.

---

## 4. Feature Branch Naming

Every independently testable and reviewable implementation feature must use its own branch.

Use:

`feat/<feature-name>`

Prefer ordered descriptive branch names such as:

`feat/001-project-baseline`

`feat/002-runtime-infrastructure`

`feat/003-api-auth`

`feat/004-shared-primitives`

`feat/005-accounts-categories`

`feat/006-currency-core`

`feat/007-double-entry-ledger`

`feat/008-budget-engine`

etc.

Use the actual approved implementation-plan order.

Do not:

* reuse an old branch name for unrelated work;
* combine unrelated features into one branch;
* create a separate branch for trivial internal implementation steps.

One branch should represent one coherent, independently reviewable feature.

---

## 5. Mandatory Startup Procedure

At the beginning of every implementation session:

1. Read this `AGENTS.md`.

2. Read:

   `IMPLEMENTATION_PROGRESS.md`

3. Read the relevant implementation plan sections.

4. Inspect repository state:

   `git status --short`

   `git branch --show-current`

   `git remote -v`

5. Fetch latest GitHub information:

   `git fetch origin`

6. Compare progress state against Git/GitHub.

7. Identify the first unfinished feature whose dependencies are complete.

Never select the next feature based solely on previous conversation context.

---

## 6. Mandatory Procedure Before Every Feature

Every feature MUST begin from a freshly synchronized `dev`.

### Step 1 — Verify the working tree

Run:

`git status --short`

If unexpected uncommitted changes exist:

STOP.

Do not automatically:

* discard them;
* stash them;
* reset them;
* overwrite them.

Report the unexpected changes instead.

---

### Step 2 — Switch to dev

Run:

`git switch dev`

---

### Step 3 — Fetch remote state

Run:

`git fetch origin`

---

### Step 4 — Update local dev

Run:

`git pull --ff-only origin dev`

Only fast-forward updates are allowed automatically.

If this fails because local `dev` has diverged:

STOP and report the divergence.

Do not automatically:

* merge;
* rebase;
* reset;
* force-update `dev`.

---

### Step 5 — Verify dev

Run:

`git branch --show-current`

Expected result:

`dev`

Run:

`git status`

The working tree must be clean before creating the next feature branch.

---

### Step 6 — Select the feature

Read `IMPLEMENTATION_PROGRESS.md`.

Select the first:

`PENDING`

feature whose dependencies are all:

`MERGED`.

Confirm its requirements against the approved implementation plan.

---

### Step 7 — Create the branch

Run:

`git switch -c feat/<feature-name>`

The new branch must originate from the freshly synchronized local `dev`.

NEVER create the next feature branch from the previously completed feature branch.

---

## 7. Starting Feature Progress

Immediately after creating a new feature branch, update:

`IMPLEMENTATION_PROGRESS.md`

Set the feature to:

`IN_PROGRESS`

Record at minimum:

* branch name;
* implementation-plan feature/phase;
* start information where applicable;
* blockers/notes where applicable.

Commit the progress-file update as part of the feature branch.

---

## 8. Feature Implementation Rules

Implement only:

* the selected feature;
* directly required dependencies that genuinely belong to that feature.

Do not introduce unrelated refactors.

Before implementation:

* read the complete relevant implementation-plan section;
* read relevant technical specification/PRD requirements;
* confirm previous dependencies are actually present in current `dev`;
* inspect existing sibling implementation patterns.

Complete all applicable implementation-plan requirements before considering the feature finished.

This includes where applicable:

* migrations;
* PostgreSQL constraints;
* indexes;
* models;
* factories;
* seeders;
* domain services;
* application services/actions;
* controllers;
* Form Requests;
* API Resources;
* policies;
* authorization;
* rate limiting;
* idempotency;
* optimistic concurrency;
* locking;
* queues/jobs;
* events/listeners;
* configuration;
* observability;
* documentation;
* tests.

---

## 9. Feature Quality Gate

Before opening a PR, all applicable requirements must be complete.

Verify:

* implementation-plan checklist items;
* implementation-plan exit criteria;
* authorization;
* validation;
* idempotency;
* concurrency;
* failure paths;
* boundary cases;
* database constraints;
* API behavior;
* observability.

Run all applicable tests and repository quality checks required by the existing Laravel Boost instructions.

Do not open a PR just because the happy path works.

---

## 10. Commit Rules

Use clear Conventional Commit-style messages where practical.

Examples:

`feat(currency): implement currency core`

`feat(ledger): add immutable journal posting`

`feat(budget): implement atomic next-month borrowing`

A feature may use multiple meaningful commits.

Do not commit unrelated changes.

Before pushing:

run:

`git status`

and inspect the complete diff.

---

## 11. Push Rules

Push ONLY the current feature branch.

Example:

`git push -u origin feat/006-currency-core`

Never push feature implementation commits directly to:

`dev`

or:

`main`.

---

## 12. Pull Request Rules

Every feature must have its own GitHub Pull Request.

The PR base MUST explicitly be:

`dev`

The PR head MUST be the current:

`feat/...`

branch.

Use GitHub CLI where available.

Example:

`gh pr create --base dev --head feat/006-currency-core`

Never rely on the repository default branch when creating a PR.

Always explicitly set:

`--base dev`

---

## 13. Required Pull Request Description

Every feature PR must contain a detailed description based on the ACTUAL final implementation.

Use the following structure.

### Summary

Explain:

* what was implemented;
* why it was required.

### Implementation

Describe the technical implementation.

Include relevant:

* domain services;
* application services/actions;
* models;
* jobs;
* events/listeners;
* configuration;
* significant implementation decisions.

### Architecture / DDD

Explain:

* domain boundaries used;
* application boundaries used;
* important architectural decisions.

### Database Changes

List:

* migrations;
* tables;
* columns;
* indexes;
* foreign keys;
* unique constraints;
* check constraints;
* data migrations.

If there are none, explicitly say:

`No database changes.`

### API Changes

Document:

* HTTP method;
* endpoint;
* request contract;
* response contract;
* error codes;
* idempotency behavior;
* optimistic concurrency/version behavior.

If there are none, explicitly say:

`No API changes.`

### Security and Authorization

Describe applicable:

* authentication;
* ownership policies;
* authorization;
* rate limiting;
* sensitive-data handling.

### Concurrency and Idempotency

Describe applicable:

* locks;
* idempotency keys;
* version checks;
* uniqueness protection;
* retry behavior.

### Tests

List:

* tests added;
* tests modified;
* commands executed;
* actual test results.

Do not claim tests that were not executed.

### Failure and Edge Cases

Describe significant failure cases and boundaries covered.

### Observability

Describe applicable:

* logs;
* metrics;
* correlation IDs;
* job monitoring;
* health checks;
* alerts.

### Documentation

Describe documentation updates.

### Risks / Compatibility

Describe:

* migrations;
* deployment considerations;
* compatibility concerns;
* known limitations;
* breaking changes if any.

### Implementation Plan Coverage

Identify the exact:

* implementation-plan phase;
* feature;
* steps;
* checklist items;
* exit criteria

satisfied by the PR.

---

## 14. After Creating the PR

After GitHub creates the PR:

1. Record:

   * PR number;
   * PR URL where useful.

2. Update:

   `IMPLEMENTATION_PROGRESS.md`

3. Set feature status to:

   `PR_OPEN`

4. Record:

   * PR number;
   * branch;
   * tests completed;
   * implementation-plan coverage.

5. Commit the progress update to the same feature branch.

6. Push it so it becomes part of the existing PR.

Do NOT mark the feature:

`MERGED`

yet.

---

## 15. PR Verification

Before merging:

1. Verify the PR base is:

   `dev`

2. Verify the PR head is the expected:

   `feat/...`

3. Inspect the PR diff.

4. Check required GitHub status checks.

5. Check CI.

6. If implementation-related checks fail:

   * fix the feature branch;
   * run affected tests locally;
   * commit fixes;
   * push;
   * verify again.

Do not merge a PR with failing required checks.

Do not bypass:

* branch protection;
* required CI;
* required reviews;
* merge queues;
* security checks.

Do not use administrator bypasses.

If repository policy requires a human review before merge:

STOP and report the blocker.

---

## 16. Merge Rules

When:

* feature implementation is complete;
* required local tests pass;
* required GitHub checks pass;
* the PR is mergeable;
* repository rules permit the merge;

merge the PR into:

`dev`

using a normal merge commit.

Example:

`gh pr merge <PR_NUMBER> --merge`

Do NOT merge into:

`main`.

Do NOT use:

`gh pr merge --delete-branch`

---

## 17. Branch Retention

Codex MUST NOT delete completed feature branches.

This applies to both:

* local feature branches;
* remote GitHub feature branches.

Never run:

`git push origin --delete feat/<feature-name>`

Never run:

`git branch -d feat/<feature-name>`

Never run:

`git branch -D feat/<feature-name>`

Do not use automatic branch deletion when merging a PR.

Branch cleanup is exclusively the user's responsibility.

After a feature is merged, the intended state is:

Remote:

`origin/feat/<feature-name>` → retained

Local:

`feat/<feature-name>` → retained

PR:

merged into `dev`

---

## 18. Mandatory Procedure After Every Merge

After GitHub confirms the feature PR is merged into `dev`:

### Step 1 — Record merge information

Record:

* PR number;
* merge commit SHA;
* confirmed merge state.

Do not add new feature implementation to the already completed branch.

---

### Step 2 — Switch to dev

Run:

`git switch dev`

---

### Step 3 — Fetch latest state

Run:

`git fetch origin`

---

### Step 4 — Pull merged dev

Run:

`git pull --ff-only origin dev`

---

### Step 5 — Verify

Run:

`git branch --show-current`

Expected:

`dev`

Verify:

* the merged feature exists in local `dev`;
* local `dev` matches `origin/dev`;
* working tree is clean.

---

### Step 6 — Reconcile progress

Read:

`IMPLEMENTATION_PROGRESS.md`

The previous feature's progress record may still say:

`PR_OPEN`

because that record was committed before GitHub performed the merge.

On the NEXT feature branch, update the previous feature to:

`MERGED`

and record:

* merge commit;
* final status;
* completion information.

---

### Step 7 — Start next feature

Select the next dependency-complete:

`PENDING`

feature.

Create its branch ONLY after local `dev` has been updated from the merged remote state:

`git switch -c feat/<next-feature>`

The next feature branch must NEVER originate from the previous feature branch.

---

## 19. Final Progress Reconciliation

The final implementation feature creates a special situation:

after its PR is merged, there may be no next feature branch available to record its final:

`MERGED`

status.

In that situation:

1. checkout and synchronize `dev`;

2. create:

   `feat/999-progress-finalization`

3. this branch may ONLY update/reconcile:

   `IMPLEMENTATION_PROGRESS.md`

and final implementation verification metadata;

4. do not implement application functionality on this branch;

5. open a PR explicitly targeting:

   `dev`;

6. merge it normally after verification;

7. retain the branch locally and remotely;

8. checkout:

   `dev`

9. run:

   `git fetch origin`

   `git pull --ff-only origin dev`

The final progress file should then accurately reflect all completed implementation work.

---

## 20. Implementation Progress Reconciliation Rules

Always verify progress state against GitHub/Git.

Examples:

### Progress says PR_OPEN but GitHub says merged

Update to:

`MERGED`

and record the merge commit.

### Progress says MERGED but feature is not in origin/dev

STOP and investigate.

### Progress says IN_PROGRESS but expected feature branch does not exist

STOP and investigate before creating new work.

### Implementation plan changed

Reconcile:

`IMPLEMENTATION_PROGRESS.md`

with the approved final implementation plan before proceeding.

Never reimplement an already verified merged feature.

---

## 21. Stop Conditions

STOP and report instead of improvising when any of these occurs:

* unexpected uncommitted local changes;
* local `dev` divergence;
* unresolved merge conflict;
* push rejected;
* GitHub authentication failure;
* PR targets a branch other than `dev`;
* required CI failure that cannot safely be fixed;
* branch protection prevents merge;
* required human review is missing;
* migration failure with unclear recovery;
* implementation requirement is materially ambiguous;
* approved product documents contradict each other;
* required product/operations decision is unresolved;
* continuing risks losing user work.

When blocked, report:

* feature name;
* branch;
* PR number if available;
* exact blocker;
* relevant tests/check status;
* recommended next action.

---

## 22. Forbidden Git Operations

Unless the user explicitly instructs otherwise, Codex must NEVER use:

`git reset --hard`

`git push --force`

`git push --force-with-lease`

destructive automatic branch deletion

history rewriting on shared branches

administrator PR merge bypasses

Do not automatically stash or discard unknown user changes.

---

## 23. Main Branch Prohibition

Codex must NEVER:

* merge `dev` into `main`;
* merge feature branches into `main`;
* push implementation commits directly to `main`;
* rebase `main`;
* perform release promotion to `main`.

The user manages `main` manually.

---

## 24. Required Feature Lifecycle

The required lifecycle for every feature is:

`latest origin/dev`

→ synchronize local `dev`

→ create `feat/<feature>`

→ mark feature `IN_PROGRESS`

→ implement

→ test

→ commit

→ push feature branch

→ create PR explicitly targeting `dev`

→ update progress to `PR_OPEN`

→ verify PR/CI/checks

→ merge PR into `dev`

→ retain feature branch locally

→ retain feature branch remotely

→ checkout `dev`

→ fetch

→ pull latest `origin/dev`

→ verify merge

→ reconcile previous feature as `MERGED`

→ create next `feat/<feature>` from latest `dev`

→ repeat

---

## 25. Final Repository State

When all approved backend MVP implementation work is complete:

* every approved implementation feature is complete;
* every feature has its own PR;
* every feature PR is merged into `dev`;
* all local `feat/...` branches remain;
* all remote `feat/...` branches remain;
* `IMPLEMENTATION_PROGRESS.md` is fully reconciled;
* local current branch is `dev`;
* local `dev` matches `origin/dev`;
* all required release gates pass;
* `main` has not been modified or merged by Codex.

The user is responsible for:

* feature branch cleanup;
* merging `dev` into `main`.

---

## 26. Completion Reporting

At the end of each feature, report:

* feature name;
* feature branch;
* implementation summary;
* migrations;
* API changes;
* tests executed;
* PR number;
* PR status;
* merge commit if merged;
* next planned feature.

At final backend MVP completion, report:

* all implemented features;
* all feature branches;
* all PR numbers;
* all merge commits;
* migrations;
* major API additions;
* release/test results;
* unresolved post-MVP work;
* confirmation that `main` was untouched;
* confirmation that the repository is currently checked out on latest `dev`.
