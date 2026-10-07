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
`issue_assignment` records:

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
