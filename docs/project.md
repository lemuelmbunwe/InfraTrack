InfraTrack 

Project Master Documentation 

Project Name: InfraTrack 
Project Type: Urban Infrastructure Management System 
Primary Focus: Road Infrastructure / Pothole Management 
Backend: Laravel 
Frontend: Vue.js 
Database: PostgreSQL 
API Style: REST 
Development Model: Feature-based incremental development 
Status: Development 

 

1. Project Overview 

InfraTrack is an urban infrastructure management system designed to help councils and municipal authorities manage reported road infrastructure problems, particularly potholes. 

The system provides a structured workflow for: 

reporting road issues; 

recording their locations and photographic evidence; 

reviewing reported issues; 

assigning maintenance work; 

tracking maintenance progress; 

managing field inspectors, administrators, and contractors; 

maintaining historical records; 

and eventually using Artificial Intelligence to automatically assess pothole severity. 

The initial implementation focuses specifically on pothole management. 

Other infrastructure categories such as road signs, street lights, and drainage may be introduced later. They are not part of the current MVP unless requirements are formally expanded. 

 

2. Project Goal 

The primary goal is to build a maintainable and extensible urban infrastructure management platform that can eventually incorporate AI-based road-condition analysis. 

The project is also intentionally structured to support future expansion without requiring a complete rewrite of the system. 

The long-term direction is: 

Manual Infrastructure Management 
              ↓ 
Structured Digital Management 
              ↓ 
Geospatial Management 
              ↓ 
AI-Assisted Assessment 
              ↓ 
AI-Enabled Infrastructure Intelligence 

 

3. Current Scope 

3.1 In Scope 

The current system focuses on pothole management. 

Core capabilities include: 

User authentication 

User and role management 

Field issue reporting 

Photograph upload 

Geographic location capture 

Issue review 

Severity management 

Issue status management 

Maintenance assignments 

Contractor management 

Assignment tracking 

Status history 

Map-based issue visualization 

Auditability of important actions 

Future AI severity analysis 

 

3.2 Currently Out of Scope 

The following are not part of the initial implementation: 

public user registration; 

public citizen accounts; 

road-sign management; 

street-light management; 

drainage management; 

payment processing; 

financial management; 

vehicle/fleet management; 

advanced predictive analytics; 

full autonomous maintenance planning. 

These may be considered in future versions if requirements justify them. 

 

4. System Users 

InfraTrack has three primary operational roles. 

4.1 Council Admin 

The Council Admin manages the system and its operational workflow. 

Stored in the roles table as admin. 

Responsibilities include: 

managing users; 

reviewing reported issues; 

managing severity; 

managing issue status; 

creating maintenance assignments; 

assigning contractors; 

reassigning work; 

monitoring maintenance activities. 

 

4.2 Field Inspector 

The Field Inspector works in the field and reports road issues. 

Stored in the roles table as inspector. 

The inspector can: 

create issue reports; 

upload photographs; 

provide geographic coordinates; 

provide the required report information; 

specify severity during the initial manual MVP workflow. 

The inspector does not control the final maintenance status. 

 

4.3 Council Contractor 

The Contractor is responsible for carrying out assigned maintenance work. 

Stored in the roles table as contractor. 

The contractor can: 

view assigned work; 

view relevant issue information; 

access issue locations; 

update permitted maintenance information; 

report progress according to the defined workflow. 

 

5. Authentication and User Account Model 

InfraTrack is an internal system. 

There is no public signup page. 

Users do not create their own accounts. 

Instead: 

Super Admin 
     ↓ 
Creates User 
     ↓ 
User receives/establishes credentials 
     ↓ 
User logs in 

The initial Super Admin is created through a controlled Laravel Artisan command. 

 

6. Super Administrator Bootstrap 

The first administrator is created outside the normal application UI through a controlled setup process. 

The command is: 

php artisan infratrack:create-super-admin 

The command creates an Admin user with: 

role = Admin 
super = true 
is_active = true 

Any administrator created through this Artisan command is automatically a Super Admin. 

Multiple Super Admins are permitted. 

The system does not assume that there can only be one Super Admin. 

The Super Admin is then able to create other users through the application. 

 

7. Roles and Super Privilege 

A role defines the user’s operational responsibilities. 

The current roles are: 

Admin 
Field Inspector 
Contractor 

Super privilege is separate from the role. 

A user can therefore be: 

Admin + super = true 

or: 

Admin + super = false 

The super attribute belongs to the user, not the role. 

This is intentional because not every Admin must necessarily be a Super Admin. 

 

8. User Account Status 

Users have an active/inactive state. 

is_active = true 

means the account can access the system. 

is_active = false 

means the account is disabled. 

Users should generally be deactivated rather than deleted when they leave the organization so that historical records remain associated with the original user. 

 

9. Authentication Rules 

The system will provide: 

login; 

logout; 

password management; 

password recovery where required. 

The system will not provide public registration. 

Authentication and authorization are enforced by the Laravel backend. 

Frontend restrictions are only for usability and must never be considered security controls. 

 

10. Issue Reporting 

The central entity in the current system is the road issue. 

The initial MVP focuses on potholes. 

A Field Inspector reports an issue by providing information such as: 

photograph; 

timestamp/report time; 

latitude; 

longitude; 

relevant location information. 

During the initial manual workflow, the Field Inspector specifies the severity. 

The issue is stored before any future AI processing takes place. 

 

11. Severity 

The initial MVP uses manual severity assignment. 

The current workflow is: 

Field Inspector 
       ↓ 
Creates Issue 
       ↓ 
Provides Severity 
       ↓ 
Issue Stored 

The future AI workflow will change this process to: 

Field Inspector 
       ↓ 
Uploads Photograph 
       ↓ 
Issue Stored Immediately 
       ↓ 
Background AI Analysis 
       ↓ 
AI Predicts Severity 
       ↓ 
Issue Updated 

The AI process must not unnecessarily block issue submission. 

This asynchronous design is important because image analysis may take longer than a normal API request. 

NB: the final AI workflow could be different to what is in the document as of this point. However, if a change is to be made, it will be updated here. 

 

12. Issue Status 

Issue status represents the operational state of an issue. 

The Admin controls the issue’s status according to the defined business workflow. 

Status changes should be auditable. 

The system therefore maintains status history rather than relying only on the current status value. 

 

13. Assignments 

An assignment represents a maintenance task given to a contractor. 

One assignment can contain multiple issues. 

Example: 

Assignment #15 
     │ 
     ├── Pothole #101 
     ├── Pothole #102 
     ├── Pothole #103 
     └── Pothole #104 

This supports real-world maintenance work where several potholes on the same road or area are handled as one maintenance operation. 

 

14. Issue Assignment Rule 

An issue can have only one current assignment at a time. 

However, an issue may be reassigned. 

Therefore, assignment history must be preserved. 

Example: 

Pothole #101 
      ↓ 
Assignment #15 
      ↓ 
Reassigned 
      ↓ 
Assignment #23 

The system must retain the history of these assignments. 

 

15. Responsibility Rules 

The following responsibilities are established: 

Action 

Field Inspector 

Admin 

Contractor 

Report issue 

Yes 

Yes, where permitted 

No 

Specify initial severity 

Yes 

Yes 

No 

Manage issue status 

No 

Yes 

Limited according to workflow 

Create assignment 

No 

Yes 

No 

Assign contractor 

No 

Yes 

No 

Reassign issue 

No 

Yes 

No 

View assigned work 

No 

Yes 

Yes 

Perform maintenance 

No 

No 

Yes 

Backend authorization must enforce these rules. 

 

16. Database Model 

The initial core database consists of the following entities. 

roles 

Defines available system roles. 

Key fields: 

id 
name 
description 
created_at 
updated_at 

 

users 

Stores system users. 

Key fields: 

id 
name 
email 
password 
role_id 
super 
is_active 
remember_token 
created_at 
updated_at 

Relationships: 

users.role_id → roles.id 

 

issues 

Stores reported road issues. 

Important information includes: 

id 
reported_by 
photo_path 
latitude 
longitude 
address 
severity 
status 
reported_at 
created_at 
updated_at 

 

assignments 

Stores maintenance assignments. 

Important information includes: 

id 
assigned_by 
contractor_id 
title 
description 
assigned_at 
created_at 
updated_at 

 

issue_assignment 

Maintains the relationship between issues and assignments and preserves reassignment history. 

Important information includes: 

id 
issue_id 
assignment_id 
assigned_at 
unassigned_at 
created_at 
updated_at 

 

issue_status_history 

Maintains the history of status changes. 

Important information includes: 

id 
issue_id 
changed_by 
old_status 
new_status 
changed_at 

 

ai_analyses 

Reserved for the future AI integration. 

Important information includes: 

id 
issue_id 
predicted_severity 
confidence 
model_version 
status 
analyzed_at 
created_at 
updated_at 

 

17. Database Relationship Summary 

roles 
   │ 
   │ 1 
   │ 
   │ * 
   ▼ 
users 
   │ 
   ├───────────────┐ 
   │               │ 
   │               │ 
   ▼               ▼ 
issues        assignments 
   │               │ 
   │               │ 
   ▼               ▼ 
issue_assignment 
   │ 
   │ 
   └───────────────┐ 
                   │ 
                   ▼ 
             assignments 
 
issues 
   │ 
   ├── issue_status_history 
   │ 
   └── ai_analyses 

More specifically: 

roles 1 ─── * users 
 
users 1 ─── * issues 
users 1 ─── * assignments 
users 1 ─── * issue_status_history 
 
issues 1 ─── * issue_assignment 
assignments 1 ─── * issue_assignment 
 
issues 1 ─── * issue_status_history 
issues 1 ─── * ai_analyses 

 

18. Backend Architecture 

The backend is Laravel and acts as the application’s API server. 

The backend is responsible for: 

authentication; 

authorization; 

validation; 

business rules; 

database operations; 

file management; 

API responses; 

background jobs; 

AI integration; 

error handling. 

The frontend must not contain authoritative business rules. 

The backend is the source of truth for application behavior. 

 

19. Frontend Architecture 

The frontend is a separate Vue.js application. 

It communicates with Laravel through HTTP requests. 

The architecture is: 

Vue 
  ↓ 
HTTP / JSON 
  ↓ 
Laravel API 
  ↓ 
PostgreSQL 

The Vue application is responsible primarily for: 

user interface; 

navigation; 

user interaction; 

displaying API data; 

client-side usability validation; 

communicating with the API. 

 

20. API Architecture 

The system uses REST APIs. 

The frontend and backend are separate applications. 

The frontend must not access the PostgreSQL database directly. 

All data operations go through Laravel. 

The intended API structure uses versioning: 

/api/v1/... 

Examples: 

/api/v1/auth/... 
/api/v1/users/... 
/api/v1/issues/... 
/api/v1/assignments/... 

API contracts are documented separately in api.md. 

 

21. Technology Stack 

Layer 

Technology 

Frontend 

Vue.js 

Frontend tooling 

Vite 

Backend 

Laravel 

Backend language 

PHP 

Database 

PostgreSQL 

API 

REST 

Version control 

Git 

Future AI 

Python-based AI services/models 

The AI layer will be introduced later rather than forcing AI infrastructure into the initial MVP. 

 

22. AI Integration Direction 

AI is a planned major component of InfraTrack. 

The initial AI objective is automated pothole severity assessment from photographs. 

The intended architecture is: 

Vue 
 ↓ 
Laravel API 
 ↓ 
Create Issue 
 ↓ 
Database 
 ↓ 
Queue 
 ↓ 
AI Analysis 
 ↓ 
Predicted Severity 
 ↓ 
Database 

The AI system should be separated from the main Laravel request lifecycle. 

This prevents a slow AI operation from unnecessarily delaying the user’s issue submission. 

The exact AI model and Python service architecture will be determined during the AI implementation phase. 

 

23. Geolocation 

Issue reports contain geographic coordinates. 

At minimum: 

latitude 
longitude 

The system may also store a human-readable address. 

The source of the address may be determined during implementation based on the selected geolocation/reverse-geocoding approach. 

Coordinates remain the authoritative location data. 

 

24. Project Development Strategy 

InfraTrack will be developed using vertical feature slices. 

The system will not be built by completing the entire backend first and the entire frontend afterward. 

Instead, each feature should move through its complete lifecycle. 

Requirement 
    ↓ 
Database 
    ↓ 
API 
    ↓ 
Backend 
    ↓ 
Tests 
    ↓ 
Frontend 
    ↓ 
Integration 
    ↓ 
Verification 
    ↓ 
Commit 

This allows each feature to become functional before moving to the next. 

 

25. Development Order 

The current development sequence is: 

1. Development Foundation 
        ↓ 
2. Authentication & User Management 
        ↓ 
3. Super Admin Bootstrap 
        ↓ 
4. Issue Reporting 
        ↓ 
5. Issue Management 
        ↓ 
6. Assignment Management 
        ↓ 
7. Status Management 
        ↓ 
8. Contractor Workflow 
        ↓ 
9. Maps / Geospatial Features 
        ↓ 
10. AI Severity Analysis 
        ↓ 
11. Background Processing 
        ↓ 
12. Testing & Hardening 
        ↓ 
13. Deployment 

The exact ordering may change when requirements reveal a dependency, but major changes must be documented. 

 

26. Current Development Feature 

The first feature being implemented is: 

Super Admin Bootstrap 

The immediate implementation consists of: 

roles migration; 

update existing users migration; 

Role model; 

User-role relationship; 

role seeding; 

Super Admin Artisan command; 

command validation; 

automated tests; 

creation of the initial InfraTrack Super Admin. 

The initial users table already exists in the Laravel project and will therefore be modified rather than recreated unnecessarily. 

 

27. User Creation Rule 

The system does not have public registration. 

The first Super Admin is created using: 

php artisan infratrack:create-super-admin 

Any user created through the dedicated Super Admin Artisan command is: 

role = Admin 
super = true 
is_active = true 

Subsequent user accounts will be created through the application’s user-management functionality. 

The user-management feature will determine which roles and privileges the creating administrator is permitted to assign. 

 

28. Security Principles 

Security is primarily enforced by the backend. 

The system must: 

hash passwords; 

validate all external input; 

enforce authorization server-side; 

protect authenticated routes; 

prevent unauthorized role/privilege escalation; 

protect sensitive configuration; 

avoid exposing internal errors; 

maintain appropriate audit information; 

avoid storing secrets in source control. 

A frontend restriction is never considered sufficient authorization. 

 

29. Auditability 

Important operational actions should be traceable. 

The system must preserve relevant history for: 

issue status changes; 

issue assignment/reassignment; 

important administrative operations where required. 

Historical records should not depend on the continued existence of an active user account. 

 

30. Non-Functional Direction 

The system should prioritize: 

Maintainability 

The architecture must allow new infrastructure features and AI functionality to be added without unnecessary rewrites. 

Security 

Authentication, authorization, validation, and data protection are mandatory. 

Performance 

The system should remain responsive during normal operational use. 

The established project requirement targets approximately: 

100 concurrent users 

with approximately: 

95% of requests completing within the defined response-time target 

The exact performance threshold should remain aligned with the approved requirements documentation. 

Scalability 

The architecture should support growth in: 

users; 

issue reports; 

images; 

assignments; 

geographical coverage; 

AI processing workloads. 

Reliability 

Background processing and AI failures should not prevent the basic issue-reporting workflow from functioning. 

 

31. File and Image Handling 

Issue photographs are part of the issue record. 

The system should store the file reference/path rather than unnecessarily storing large image binaries directly inside PostgreSQL. 

The exact storage provider may initially be local storage during development and can be changed for production deployment. 

 

32. Project Naming 

The official project name is: 

InfraTrack 

The project repository uses the InfraTrack name. 

The primary PostgreSQL database is: 

infratrack 

All future documentation, code examples, commands, and infrastructure references should use InfraTrack unless a technical naming convention requires lowercase. 

 

33. Documentation Structure 

The project documentation should be maintained under: 

docs/ 

The intended documentation set includes: 

docs/ 
├── project.md 
├── architecture.md 
├── database.md 
├── api.md 
├── coding-standards.md 
├── development-plan.md 
└── ai-agent-guide.md 

project.md 

Defines what InfraTrack is, its scope, business rules, roles, major decisions, and overall direction. 

architecture.md 

Defines the technical architecture and boundaries between system components. 

database.md 

Defines the detailed database schema and relationships. 

api.md 

Defines API endpoints, requests, responses, authentication, and API conventions. 

coding-standards.md 

Defines implementation and code-quality standards. 

development-plan.md 

Defines the development sequence and feature implementation process. 

ai-agent-guide.md 

Defines how AI coding agents must work within the project. 

 

34. AI Agent Development Policy 

AI coding agents may be used to accelerate development. 

However, AI agents do not define the architecture or business requirements. 

All agents must use the project documentation as their source of context. 

The priority is: 

Approved Requirements 
        ↓ 
project.md 
        ↓ 
architecture.md 
        ↓ 
database.md 
        ↓ 
api.md 
        ↓ 
coding-standards.md 
        ↓ 
development-plan.md 
        ↓ 
ai-agent-guide.md 
        ↓ 
Existing Code 

Agents must not silently change established decisions. 

If an implementation requires a major architectural or business-rule change, the change must be identified and reviewed before implementation. 

 

35. Multiple AI Agents 

Different free AI agents may be used for different implementation tasks. 

For example: 

Agent A → task A 
Agent B → task B 
Agent C → task C 
Agent D → task D 

All agents must work against the same repository and documentation. 

Agents must not independently create competing architectures. 

Every completed task should leave the repository in a state that another developer or agent can understand and continue. 

 

36. Feature Completion Standard 

A feature is considered complete only when: 

the requirement is implemented; 

the database is correct; 

the API is correct; 

authorization is enforced; 

validation is implemented; 

relevant tests exist; 

tests pass; 

frontend integration works where applicable; 

existing functionality still works; 

documentation is updated where necessary; 

the implementation follows project coding standards. 

An AI agent claiming that a task is “done” does not constitute acceptance. 

 

37. Change Management 

Established project decisions must not be changed casually. 

If a significant change is required: 

Identify change 
      ↓ 
Explain reason 
      ↓ 
Evaluate impact 
      ↓ 
Approve change 
      ↓ 
Update documentation 
      ↓ 
Implement 
      ↓ 
Test 

This prevents documentation and implementation from drifting apart. 

 

38. Current Architectural Principle 

InfraTrack is intentionally being developed as a modular monolithic application. 

The core application remains: 

Vue Frontend 
      + 
Laravel API 
      + 
PostgreSQL 

Additional services, particularly AI processing, may be introduced when their complexity justifies separation. 

The project should not adopt microservices merely for architectural fashion. 

 

39. Current Source-of-Truth Rule 

This document represents the authoritative project-level decisions for InfraTrack. 

When a lower-level implementation document conflicts with this document, the conflict must be resolved rather than silently choosing one. 

When implementation reveals that a decision must change, this document must be updated. 

The objective is to maintain one coherent project direction. 

 

40. Current Project State 

At the beginning of implementation: 

Requirements analysis has been completed. 

Core business rules have been established. 

UI/UX design work has been completed. 

Laravel backend has been created. 

Vue/Vite frontend has been created. 

PostgreSQL has been selected. 

The project is named InfraTrack. 

The PostgreSQL database is named infratrack. 

API-based communication between Vue and Laravel has been established as the architecture. 

The first implementation feature is Super Admin Bootstrap. 

The roles migration is being created. 

The existing Laravel users migration is being modified. 

The initial Super Admin will be created through an Artisan command. 

 

41. Project Guiding Principle 

InfraTrack should be built as a real software system, not merely as a collection of generated features. 

Every implementation decision should favor: 

Correctness 
    ↓ 
Understanding 
    ↓ 
Maintainability 
    ↓ 
Security 
    ↓ 
Testability 
    ↓ 
Simplicity 
    ↓ 
Performance 

AI is an implementation aid, not a replacement for engineering judgment. 

The final system must remain understandable to a developer who did not write the original code. 
