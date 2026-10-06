# InfraTrack --- Architecture

## 1. Overview

InfraTrack is a modular monolithic application:

``` text
Vue.js Frontend
       ↓
HTTP / JSON
       ↓
Laravel API
       ↓
PostgreSQL
```

The frontend never connects directly to PostgreSQL.

## 2. Frontend

**Technology:** Vue.js + Vite

Responsibilities: - User interface - Navigation - User interaction -
Displaying API data - Client-side usability validation - API
communication

The frontend does not enforce security or authoritative business rules.

## 3. Backend

**Technology:** Laravel + PHP

Responsibilities: - Authentication - Authorization - Validation -
Business logic - API endpoints - Database access - File handling -
Background jobs - AI integration

Laravel is the authoritative application layer.

## 4. Database

**Technology:** PostgreSQL\
**Database:** `infratrack`

Laravel migrations are the source of truth for database structure.

## 5. API

The frontend communicates with Laravel through REST APIs.

Base structure:

``` text
/api/v1/...
```

## 6. AI Integration

AI will be introduced later and must not block normal issue submission.

``` text
Create Issue
    ↓
Store Issue
    ↓
Queue AI Job
    ↓
AI Analysis
    ↓
Update Issue
```

The exact Python/AI service architecture will be decided when AI
integration begins.

## 7. Geolocation

Latitude and longitude are the authoritative location data.

A human-readable address is generated through reverse geocoding.

The address is supplementary and does not replace the coordinates.

## 8. Audit Logging

`audit_logs` stores important system-wide administrative actions.

Audit logs are internal records and are not part of the current normal
UI.

Issue-specific history remains separate.

## 9. Architectural Principle

Keep the system modular and simple.

Do not introduce microservices unless there is a clear technical reason.
