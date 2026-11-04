# InfraTrack --- API

## 1. API Style

InfraTrack uses REST APIs.

Base path:

``` text
/api/v1
```

The Vue frontend communicates with Laravel using HTTP and JSON.

## 2. Authentication

Protected endpoints require authentication.

Authorization is enforced by Laravel.

Frontend restrictions are not security controls.

## 3. Endpoint Structure

Use resource-oriented endpoints.

``` text
/api/v1/auth/...
/api/v1/users/...
/api/v1/issues/...
/api/v1/assignments/...
```

## 4. Validation

All external input must be validated by Laravel.

Client-side validation only improves user experience.

## 5. Authorization

Every protected operation must verify permissions.

Examples: - Only authorized admins can manage users and assignments. -
Contractors can view assignment details and the issue roster only for
assignments where they currently have at least one issue. - Contractors can
open issue details or photos only for issues currently allocated to them. -
A contractor must lose issue access when it is reassigned away from them.

## 6. Assignment Access

A contractor's current issues and assignment involvement are determined by
active `issue_assignments` records:

``` text
unassigned_at IS NULL
```

Historical allocations remain stored but do not grant current access. An
assignment summary may include every issue's identifier, status, and current
responsible contractor so involved contractors can see the full roster. It
must not expose other contractors' issue details or photos. Admins can view
all assignments, including assignments with no current issues.

## 7. Issue Status Updates

A status update may contain an optional message.

``` json
{
  "status": "in_progress",
  "message": "Repair work has started."
}
```

The message is stored with the issue history entry.

There is no chat system.

## 8. Issue Location

Issue creation accepts latitude and longitude.

The human-readable address is generated through reverse geocoding and is
supplementary to the coordinates.

## 9. Error Handling

Use appropriate HTTP status codes and clear JSON errors.

Common cases:

-   `400` --- Bad request
-   `401` --- Unauthenticated
-   `403` --- Unauthorized
-   `404` --- Resource not found
-   `422` --- Validation failure
-   `500` --- Unexpected server error

## 10. API Rules

-   Keep endpoints resource-oriented.
-   Validate all input on the backend.
-   Enforce authorization on the backend.
-   Do not expose credentials or internal errors.
-   Keep response structures consistent.
-   Do not allow the frontend to bypass business rules.

## 11. User Creation and Role Identity

Authenticated users are represented with safe identity fields:

``` json
{
  "id": 12,
  "name": "Jordan Lee",
  "email": "jordan@example.gov",
  "role": "inspector",
  "super": false,
  "is_active": true
}
```

The login and current-user endpoints include this identity as `user`.
Passwords, password confirmations, role IDs, and token hashes are never
included in user responses.

Only an active Admin with `super = true` may create an account:

``` text
POST /api/v1/users
Authorization: Bearer <token>
```

The request accepts `name`, `email`, `role`, `password`,
`password_confirmation`, and optional `super`. The role must be `admin`,
`inspector`, or `contractor`; new accounts are active. Super privilege may
only be assigned to an Admin. The creator supplies the initial password;
credential delivery is handled outside the application.

Successful creation returns `201` with the safe account identity. Requests
without authentication return `401`; authenticated users without active
super-admin privileges return `403`; invalid fields return `422`.

## 12. Issue Reporting

Inspectors create an issue with a multipart request:

``` text
POST /api/v1/issues
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

The request requires an image, latitude, longitude, and severity (`low`,
`medium`, or `high`). `address_text` and `description` are optional. The
geocoded `address` is nullable and is not supplied by the client. The
frontend currently generates editable sample coordinates around Yaounde;
they are not captured GPS locations.

The backend stores photos on its private local disk under a generated path.
Responses expose an authorized `photo_url`, never a filesystem path.
`reported_by`, `reported_at`, and initial status (`reported`) are set by the
server.

`GET /api/v1/issues` returns all issues to Admins, only the current
Inspector's issues to Inspectors, and only assigned issues to Contractors.
`GET /api/v1/issues/{issue}` follows the same visibility rule.
`GET /api/v1/issues/{issue}/photo` streams the private image only after the
same authorization check.

Inspectors may update their own issue fields except status while the issue is
in `reported` status and not assigned. Admins may update status, severity, and description.
Once an issue has been assigned, neither Admins nor Contractors may set its
status back to `reported`. Admins may move issues between other supported
statuses, including back to `assigned`.

## 13. Contractor Assignment and Issue Management

### 13.1 Contractor Directory
Active Administrators can retrieve the list of active contractors:

``` text
GET /api/v1/users/contractors
Authorization: Bearer <token>
```

Returns `200` with an array of active Contractor user identities ordered by name. Non-administrators receive `403`.

### 13.2 Assignment Management
Assignments group multiple issues under a title and optional instructions.
Only active Administrators can create, edit, and manage assignments.

Create an assignment:

``` text
POST /api/v1/assignments
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Main Street repairs",
  "instructions": "Repair the marked potholes.",
  "contractor_id": 14,
  "issue_ids": [101, 102, 103]
}
```

- `title` is required. `instructions` is optional and nullable.
- `contractor_id` must identify an active user with the `contractor` role.
- `issue_ids` must contain one or more distinct issues. Every selected issue must be in `reported` status and have no current allocation. If any issue is ineligible, the request is rejected without partially creating the assignment.
- On success, the API creates one assignment with `initial_contractor_id` set to the selected Contractor and one active `issue_assignments` row per selected issue, all linked to the same assignment and initial contractor. Each row records the assigning Admin and allocation time.
- Returns `201` with the created assignment resource.

Admins can list and view all assignments, including those with no currently allocated issues. Contractors can list and view only assignments in which they currently have at least one issue. The assignment resource includes its title, instructions, initial Contractor, currently involved contractors, and issue roster. The initial Contractor remains the one selected at creation; the current involved contractors are derived from active issue allocations and may change after reassignment. For roster issues, expose only the issue identifier, status, and current responsible contractor; issue detail and photo endpoints continue to enforce per-issue access.

Admins can edit an assignment's title and instructions:

``` text
PATCH /api/v1/assignments/{assignment}
```

Edits replace the current values. Assignment detail edit history is not part of this feature.

### 13.3 Issue Reassignment
Initial allocations are created through `POST /api/v1/assignments`. Active Administrators use this endpoint only to reassign an issue that already belongs to an assignment:

``` text
PATCH /api/v1/issues/{issue}/assignment
Authorization: Bearer <token>
Content-Type: application/json

{
  "contractor_id": 27
}
```

- Reassignment is allowed only when the issue is in `assigned` status and its current allocation has a non-null `assignment_id`.
- `contractor_id` must identify an active user with the `contractor` role and must differ from the current Contractor.
- The previous allocation receives an `unassigned_at` value. A new active allocation is created with the same `assignment_id` and the new Contractor. No new assignment is created, and the issue remains `assigned`.
- Reassignment is not allowed while the issue is `in_progress` or `resolved`; an Admin must first return it to `assigned`.
- Returns `200` with the updated issue resource, including the new current assignee. No records are written to `issue_status_history`.

### 13.4 Contractor Status Updates
An assigned Contractor can update the status of their issue:

``` text
PATCH /api/v1/issues/{issue}/status
Authorization: Bearer <token>
Content-Type: application/json

{
  "status": "in_progress"
}
```

- Allowed only for the active Contractor currently assigned to the issue (an `issue_assignments` row with `unassigned_at` null and `contractor_id` equal to the authenticated user).
- Accepts only `reported`, `assigned`, `in_progress`, or `resolved`. `reported` is rejected when the issue has an assignment history and is not already in `reported` status.
- Contractors cannot modify any other issue attributes.
- Resolved issues remain visible and accessible to the assigned Contractor.
- No records are written to `issue_status_history`.

