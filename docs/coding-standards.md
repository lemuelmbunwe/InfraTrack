# InfraTrack --- Coding Standards

## 1. General Principles

Code should be: - Clear - Simple - Maintainable - Testable - Consistent

Prefer understandable code over clever code.

Do not add abstractions without a reason.

## 2. Backend

Follow Laravel conventions.

-   Keep controllers focused.
-   Keep complex business logic out of controllers.
-   Use Form Requests for substantial validation.
-   Use policies/middleware for authorization.
-   Use Eloquent relationships.
-   Use migrations for schema changes.
-   Hash passwords using Laravel-supported mechanisms.
-   Keep API responses consistent.

## 3. Frontend

Follow Vue conventions.

-   Keep components focused.
-   Avoid unrelated responsibilities in one component.
-   Organize API communication clearly.
-   Do not duplicate authoritative business rules in Vue.
-   Handle loading, success, and error states.

## 4. Naming

PHP/Laravel: - Classes: `PascalCase` - Methods/variables: `camelCase` -
Database fields: `snake_case`

Vue: - Components: `PascalCase` - Variables/functions: `camelCase`

## 5. Comments

Use comments only when they explain something that is not obvious from
the code.

Do not comment every line.

## 6. Testing

New backend functionality should have appropriate tests covering: -
expected behavior; - validation; - authorization; - important business
rules; - important failure cases.

## 7. Security

Never: - commit passwords or secrets; - trust frontend authorization; -
store plain-text passwords; - expose sensitive internal errors; - bypass
validation.

## 8. AI Coding Standards

AI agents must:

1.  Read `project.md` before working.
2.  Read the relevant supporting document before changing that area.
3.  Inspect existing code before writing new code.
4.  Follow the established architecture and conventions.
5.  Never silently change an established project decision.
6.  Avoid modifying unrelated files.
7.  Check the Git working tree before starting.
8.  Never overwrite uncommitted work that may belong to another task.
9.  Explain required architectural or business-rule changes before
    implementing them.
10. Run relevant tests after implementation.
11. Report what changed and what was verified.
12. Update documentation when an approved decision changes.

An AI agent's claim that a task is complete does not mean the task is
accepted.

## 9. Agent Handover

When work is incomplete, clearly identify: - what was completed; - what
remains; - known problems; - tests run; - files changed.

Leave the repository understandable to the next developer or agent.
