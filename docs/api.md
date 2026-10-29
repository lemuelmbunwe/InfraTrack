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

Examples: - Only authorized admins can manage users. - Only authorized
admins can manage assignments. - Contractors can only access their
current assignments. - A contractor must lose access when an issue is
reassigned away from them.

## 6. Assignment Access

A contractor's current issues are determined by active
`issue_assignments` records:

``` text
unassigned_at IS NULL
```

Historical assignments remain stored but do not grant current access.

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

### 13.2 Issue Assignment
Active Administrators can assign an issue to a contractor:

``` text
PATCH /api/v1/issues/{issue}/assignment
Authorization: Bearer <token>
Content-Type: application/json

{
  "contractor_id": 14
}
```

- Initial assignment is allowed only when the issue is in `reported` status and has no current assignee.
- Reassignment is allowed only when the issue is in `assigned` status and has a current assignee. The previous assignment receives `unassigned_at`; a new active assignment is created. The issue remains `assigned`.
- Reassignment does not require a reason. Reassignment is not allowed while status is `in_progress` or `resolved`; an Admin may move an issue back to `assigned` before reassigning it.
- `contractor_id` must belong to an active user with the `contractor` role.
- Upon success, creates an `issue_assignments` row (`contractor_id`, `assigned_by`, `assigned_at`, `unassigned_at` null). The issue resource exposes the current assignee as `assigned_contractor` and `assigned_at`.
- Returns `200` with the updated issue resource.
- No records are written to `issue_status_history`.

### 13.3 Contractor Status Updates
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

