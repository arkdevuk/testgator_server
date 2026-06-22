# TestGator — Application Events

All events live in `App\Event\` and extend `Symfony\Contracts\EventDispatcher\Event`.
They are dispatched by the API Platform state processors **after** the entity has been persisted.

---

## Project

### `NewProjectAppEvent`

|             |                                                 |
|-------------|-------------------------------------------------|
| **Payload** | `$project: Project`                             |
| **Trigger** | `POST /api/projects` — a new project is created |

### `ProjectUpdatedAppEvent`

|             |                                                                                                      |
|-------------|------------------------------------------------------------------------------------------------------|
| **Payload** | `$project: Project`                                                                                  |
| **Trigger** | `PUT /api/projects/{id}` or `PATCH /api/projects/{id}` — any field on an existing project is updated |

---

## Test Plan

### `NewTestingPlanAppEvent`

|             |                                                     |
|-------------|-----------------------------------------------------|
| **Payload** | `$testPlan: TestPlan`                               |
| **Trigger** | `POST /api/test_plans` — a new test plan is created |

### `TestingPlanUpdatedAppEvent`

|             |                                                                                                            |
|-------------|------------------------------------------------------------------------------------------------------------|
| **Payload** | `$testPlan: TestPlan`                                                                                      |
| **Trigger** | `PUT /api/test_plans/{id}` or `PATCH /api/test_plans/{id}` — any field on an existing test plan is updated |

### `TestPlanPublishedAppEvent`

|             |                                                                                                                                                                           |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Payload** | `$testPlan: TestPlan`                                                                                                                                                     |
| **Trigger** | `PUT` or `PATCH` on a test plan where `state` transitions **to** `published` from any other state (`draft` or `archived`). Not fired if the plan was already `published`. |

### `TestPlanClosedAppEvent`

|             |                                                                                                                                                                          |
|-------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Payload** | `$testPlan: TestPlan`                                                                                                                                                    |
| **Trigger** | `PUT` or `PATCH` on a test plan where `state` transitions **to** `archived` from any other state (`draft` or `published`). Not fired if the plan was already `archived`. |

### `TesterAssignedAppEvent`

|             |                                                                                                                                                                                                                                                     |
|-------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Payload** | `$testPlan: TestPlan`, `$tester: User`                                                                                                                                                                                                              |
| **Trigger** | `PUT` or `PATCH` on a test plan that adds one or more testers to `testersEnrolled`, **and** the test plan `state` is `published` at the time of the request. One event is dispatched per newly added tester. Not fired for draft or archived plans. |

---

## Question

### `NewQuestionAppEvent`

|             |                                                   |
|-------------|---------------------------------------------------|
| **Payload** | `$question: Question`                             |
| **Trigger** | `POST /api/questions` — a new question is created |

### `QuestionUpdatedAppEvent`

|             |                                                                                                         |
|-------------|---------------------------------------------------------------------------------------------------------|
| **Payload** | `$question: Question`                                                                                   |
| **Trigger** | `PUT /api/questions/{id}` or `PATCH /api/questions/{id}` — any field on an existing question is updated |

---

## Answer

### `NewAnswerAppEvent`

|             |                                                                                                     |
|-------------|-----------------------------------------------------------------------------------------------------|
| **Payload** | `$answer: Answer`                                                                                   |
| **Trigger** | `POST /api/answers` — a new answer is created. Requires the related test plan to not be `archived`. |

### `AnswerUpdatedAppEvent`

|             |                                                                                                                                                         |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Payload** | `$answer: Answer`                                                                                                                                       |
| **Trigger** | `PUT /api/answers/{id}` or `PATCH /api/answers/{id}` — any field on an existing answer is updated. Requires the related test plan to not be `archived`. |

---

## User

### `UserPasswordChangedAppEvent`

|             |                                                                                                                                                                                                                           |
|-------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Payload** | `$user: User`                                                                                                                                                                                                             |
| **Trigger** | `POST /api/users/me/change-password` (authenticated team member changes own password) or `POST /api/users/{id}/change-password` (admin changes any user's password). Dispatched after the new password hash is persisted. |

> **Note:** `project` is empty (`{}`) for this event — team users do not belong to a single project.

---

## Dispatch order (per request)

When a `PUT`/`PATCH` on a test plan triggers multiple conditions simultaneously, events are dispatched in this order:

1. `TestingPlanUpdatedAppEvent`
2. `TestPlanPublishedAppEvent` *(if state → published)*
3. `TestPlanClosedAppEvent` *(if state → archived)*
4. `TesterAssignedAppEvent` × N *(one per new tester, only when published)*
