<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;

/**
 * Injects custom (non-ApiResource) routes into the OpenAPI spec:
 *   - POST /api/auth/login
 *   - POST /api/auth/login_tester
 *   - POST /api/auth/refresh
 *   - GET  /api/questions/{id}/stats
 *   - GET  /api/search/query
 *   - POST /public/apx/upload
 *
 * Also post-processes auto-generated ApiResource paths to inject role documentation:
 *
 * /api/users (User resource — team accounts, type=USER):
 *   GET / GetCollection : ROLE_ADMIN only
 *   POST / PATCH        : ROLE_ADMIN only
 *   Filters : ?email= (partial)  ?id= (partial)
 *
 * /api/settings (Settings resource):
 *   GET / GetCollection : ROLE_USER + ROLE_TESTER + ROLE_ADMIN
 *                         Non-admins restricted to public=true via TesterScopeExtension
 *   POST / PATCH / DELETE : ROLE_ADMIN only — PUT is disabled
 *   Filters : ?name= (ipartial)  ?section= (ipartial)
 *
 * /api/tester_annotations (TesterAnnotation resource):
 *   All operations : ROLE_USER (dev team only)
 *   Filters : ?relateTo= (exact IRI)
 *   Order   : ?order[created]=asc|desc  ?order[updated]=asc|desc
 */
final readonly class OpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $inner,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->inner)($context);
        $paths = $openApi->getPaths();

        // ── Auth: POST /api/auth/login ────────────────────────────────────
        $paths->addPath('/api/auth/login', new PathItem(
            post: new Operation(
                operationId: 'postAuthLogin',
                tags: ['Auth'],
                responses: [
                    '200' => new Response(
                        description: 'Authenticated',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'logged' => ['type' => 'boolean'],
                                    'jwt' => ['type' => 'string'],
                                    'refreshToken' => ['type' => 'string'],
                                    'authMode' => ['type' => 'string'],
                                ],
                            ])),
                        ])
                    ),
                    '403' => new Response(description: 'Invalid credentials'),
                    '400' => new Response(description: 'Bad request'),
                ],
                summary: 'Authenticate a team member or tester',
                requestBody: new RequestBody(
                    description: 'Login credentials',
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['username', 'password'],
                            'properties' => [
                                'username' => ['type' => 'string', 'example' => 'admin@testgator.test'],
                                'password' => ['type' => 'string', 'example' => 'Password1!'],
                                'authMode' => ['type' => 'string', 'enum' => ['app', 'ldap', 'code'], 'default' => 'app'],
                                'mode' => ['type' => 'string', 'enum' => ['team', 'tester'], 'default' => 'team'],
                            ],
                        ])),
                    ]),
                    required: true
                ),
                security: [],
            ),
        ));

        // ── Auth: POST /api/auth/login_tester ─────────────────────────────
        $paths->addPath('/api/auth/login_tester', new PathItem(
            post: new Operation(
                operationId: 'postAuthLoginTester',
                tags: ['Auth'],
                responses: [
                    '200' => new Response(
                        description: 'Authenticated',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'logged' => ['type' => 'boolean'],
                                    'jwt' => ['type' => 'string'],
                                    'refreshToken' => ['type' => 'string'],
                                    'authMode' => ['type' => 'string', 'example' => 'Tester'],
                                ],
                            ])),
                        ])
                    ),
                    '401' => new Response(description: 'Invalid hash'),
                    '404' => new Response(description: 'TestPlan not found'),
                    '400' => new Response(description: 'Bad request'),
                ],
                summary: 'Authenticate a guest tester via HMAC challenge',
                requestBody: new RequestBody(
                    description: 'HMAC challenge payload',
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['challenge', 'hash', 'tp'],
                            'properties' => [
                                'challenge' => ['type' => 'string'],
                                'hash' => ['type' => 'string'],
                                'tp' => ['type' => 'integer', 'description' => 'TestPlan ID'],
                            ],
                        ])),
                    ]),
                    required: true
                ),
                security: [],
            ),
        ));

        // ── Auth: POST /api/auth/refresh ──────────────────────────────────
        $paths->addPath('/api/auth/refresh', new PathItem(
            post: new Operation(
                operationId: 'postAuthRefresh',
                tags: ['Auth'],
                responses: [
                    '200' => new Response(
                        description: 'New JWT issued',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'jwt' => ['type' => 'string'],
                                    'refreshToken' => ['type' => 'string', 'description' => 'Rotated token'],
                                ],
                            ])),
                        ])
                    ),
                    '401' => new Response(description: 'Invalid or expired refresh token'),
                    '400' => new Response(description: 'Missing refreshToken'),
                ],
                summary: 'Rotate a refresh token and obtain a new JWT',
                requestBody: new RequestBody(
                    description: 'Refresh token',
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['refreshToken'],
                            'properties' => [
                                'refreshToken' => ['type' => 'string'],
                            ],
                        ])),
                    ]),
                    required: true
                ),
                security: [],
            ),
        ));

        // ── POST /api/demo/add_demo_answer ────────────────────────────────
        $paths->addPath('/api/demo/add_demo_answer', new PathItem(
            post: new Operation(
                operationId: 'postDemoAddDemoAnswer',
                tags: ['Demo'],
                responses: [
                    '201' => new Response(
                        description: 'Answers created',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'success' => ['type' => 'boolean'],
                                    'created' => ['type' => 'integer'],
                                    'testPlan' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ])),
                        ])
                    ),
                    '400' => new Response(description: 'Missing or invalid parameters'),
                    '404' => new Response(description: 'TestPlan not found'),
                    '422' => new Response(description: 'TestPlan has no questions'),
                ],
                summary: 'Seed random demo answers into a testing plan',
                description: 'Creates `number` answers (default 10, max 100) distributed randomly across all questions in the given testing plan. Each answer gets a random state (pass / pass_with_bugs / failed / blocked / pending) and a pre-written canned comment. Useful for populating a demo environment with realistic data.',
                requestBody: new RequestBody(
                    description: 'Demo seed parameters',
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['testingPlanIri'],
                            'properties' => [
                                'testingPlanIri' => [
                                    'type' => 'string',
                                    'example' => '/api/test_plans/12',
                                    'description' => 'IRI of the TestPlan to populate',
                                ],
                                'number' => [
                                    'type' => 'integer',
                                    'minimum' => 0,
                                    'maximum' => 100,
                                    'default' => 10,
                                    'description' => 'Number of answers to create',
                                ],
                            ],
                        ])),
                    ]),
                    required: true
                ),
            ),
        ));

        // ── GET /api/questions/{id}/stats ─────────────────────────────────
        $paths->addPath('/api/questions/{id}/stats', new PathItem(
            get: new Operation(
                operationId: 'getQuestionStats',
                tags: ['Question'],
                responses: [
                    '200' => new Response(
                        description: 'Statistics',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'test_pass' => ['type' => 'integer'],
                                    'test_pass_with_bugs' => ['type' => 'integer'],
                                    'test_failed' => ['type' => 'integer'],
                                    'test_blocked' => ['type' => 'integer'],
                                    'test_pending' => ['type' => 'integer'],
                                    'test_all_count' => ['type' => 'integer'],
                                    'answer_rate' => ['type' => 'number', 'format' => 'float', 'description' => 'Percentage of non-pending answers'],
                                    'answers' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'answerId' => ['type' => 'integer'],
                                                'date' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                                                'state' => ['type' => 'string', 'enum' => ['pass', 'pass_with_bugs', 'failed', 'blocked', 'pending']],
                                            ],
                                        ],
                                    ],
                                ],
                            ])),
                        ])
                    ),
                    '404' => new Response(description: 'Question not found'),
                    '401' => new Response(description: 'Unauthorized'),
                ],
                summary: 'Answer statistics for a question',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'integer'],
                    ),
                ],
            ),
        ));

        // ── POST /public/apx/upload ───────────────────────────────────────
        $paths->addPath('/public/apx/upload', new PathItem(
            post: new Operation(
                operationId: 'uploadFile',
                tags: ['File'],
                responses: [
                    '200' => new Response(
                        description: 'File uploaded successfully',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string', 'format' => 'uuid', 'description' => 'File UUID'],
                                    'url' => ['type' => 'string', 'format' => 'uri', 'description' => 'Signed S3 URL (valid 24 h)'],
                                    'filename' => ['type' => 'string', 'example' => 'abc123.png'],
                                    '@id' => ['type' => 'string', 'example' => '/api/files/019e...', 'description' => 'API Platform IRI — use for GET /api/files/{id}'],
                                ],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'No file provided, file too large, or extension/MIME type not allowed.'),
                    '403' => new Response(description: 'Uploads are disabled via the `general.allow_upload` setting.'),
                ],
                summary: 'Upload a file to S3-compatible storage',
                description: <<<'MD'
Uploads a single file via `multipart/form-data` and stores it in the configured S3 bucket.

**Returns** a signed URL (valid 24 h) alongside the new file IRI which can be attached to questions or answers.

**Authentication** — this endpoint uses a dedicated firewall with a scoped JWT. The `Authorization: Bearer <token>` JWT must contain `"web/api/upload"` in its `scope` array. A standard API JWT without this scope will be rejected with `401`. Both testers and dev team members are supported provided their token carries the upload scope.

**Blocked** with `403` when the setting `general.allow_upload` exists and its value is `"false"`.

**Allowed extensions:** jpeg, jpg, png, gif, pdf, txt, mov, mp4, avi, doc, docx, xls, xlsx, csv

**Max size:** controlled by the `FILE_MAX_SIZE_MB` environment variable (default 10 MB).
MD,
                requestBody: new RequestBody(
                    description: 'File to upload',
                    content: new ArrayObject([
                        'multipart/form-data' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['file'],
                            'properties' => [
                                'file' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                    'description' => 'The file to upload',
                                ],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
                security: [['bearerAuth' => []]],
            ),
        ));

        // ── GET /api/search/query ─────────────────────────────────────────
        $openApi->getComponents()->getSchemas()['SearchResult'] = new ArrayObject([
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['projects', 'testers', 'test_plan', 'questions', 'answers'],
                    'description' => 'Entity type of the result',
                ],
                'iri' => [
                    'type' => 'string',
                    'example' => '/api/questions/3',
                    'description' => 'API path — use to fetch the full object',
                ],
                'score' => [
                    'type' => 'number',
                    'format' => 'float',
                    'example' => 2.1972,
                    'description' => 'BM25 relevance score — higher means more relevant',
                ],
                'name' => [
                    'type' => 'string',
                    'example' => 'Does the login work?',
                    'description' => 'Display label (title, email, or truncated comment for answers)',
                ],
                'extracts' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Short snippets showing where the match occurred, padded with …',
                    'example' => ['…Navigate to /login and verify credentials are accepted…'],
                ],
            ],
        ]);

        $paths->addPath('/api/search/query', new PathItem(
            get: new Operation(
                operationId: 'searchQuery',
                tags: ['Search'],
                responses: [
                    '200' => new Response(
                        description: 'Results sorted by BM25 score descending. Empty array when nothing matches.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/SearchResult'],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing or empty `query` parameter, or unknown `scope` value.'),
                    '401' => new Response(description: 'Unauthorized — valid Bearer token required.'),
                ],
                summary: 'Full-text BM25 search across entity types',
                description: <<<'MD'
Search across projects, testers, test plans, questions and answers using BM25 relevance scoring.

**Scoring** — matches in the name/title are weighted ×3 over matches in description/content/comment. Term frequency is saturated (100 occurrences is not 100× more relevant than 1) and scores are normalised by field length.

**Testers** are automatically restricted: regardless of the requested scope they can only search `test_plan`, `questions` and `answers`, scoped to plans they are enrolled in and answers they authored.

**`scope`** accepts:
- A single value: `?scope=questions`
- Comma-separated values: `?scope=questions,answers`
- Array style: `?scope[]=questions&scope[]=answers`
- Omitted — searches everything the caller has access to
MD,
                parameters: [
                    new Parameter(
                        name: 'query',
                        in: 'query',
                        description: 'Search string. Multiple words are split into individual terms and scored independently.',
                        required: true,
                        schema: ['type' => 'string', 'example' => 'login page'],
                    ),
                    new Parameter(
                        name: 'scope',
                        in: 'query',
                        description: 'Limit to one or more entity types. Comma-separated or repeated as scope[]. Omit to search all accessible types.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['projects', 'testers', 'test_plan', 'questions', 'answers'],
                            'example' => 'questions,answers',
                        ],
                    ),
                    new Parameter(
                        name: 'projectId',
                        in: 'query',
                        description: 'Optional. Restrict results to items belonging to this project (the project itself, its testers, test plans, questions and answers).',
                        required: false,
                        schema: ['type' => 'integer', 'example' => 42],
                    ),
                ],
            ),
        ));

        // ── POST /api/testers/{id}/profile-picture ───────────────────────────────
        $paths->addPath('/api/testers/{id}/profile-picture', new PathItem(
            post: new Operation(
                operationId: 'setTesterProfilePictureUrl',
                tags: ['Tester'],
                responses: [
                    '200' => new Response(
                        description: 'URL updated.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['profilePictureUrl' => ['type' => 'string', 'format' => 'uri']],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing `url`.'),
                    '404' => new Response(description: 'Tester not found.'),
                    '422' => new Response(description: 'Invalid URL format.'),
                    '403' => new Response(description: 'Not the tester themselves and not ROLE_ADMIN.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Set profile picture URL for a tester',
                description: <<<'MD'
> 🔒 **Required role:** tester themselves (`IS_AUTHENTICATED_FULLY`) or `ROLE_ADMIN`

Sets `profilePictureUrl` from a URL payload. The URL must be a valid HTTP/HTTPS URL. No image processing is performed — the value is stored as-is.
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['url'],
                            'properties' => [
                                'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://cdn.example.com/avatars/user.png'],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
            ),
        ));

        // ── POST /api/testers/{id}/nickname ───────────────────────────────────────
        $paths->addPath('/api/testers/{id}/nickname', new PathItem(
            post: new Operation(
                operationId: 'setTesterNickname',
                tags: ['Tester'],
                responses: [
                    '200' => new Response(
                        description: 'Nickname updated.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['nickname' => ['type' => 'string']],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing or empty `nickname`.'),
                    '422' => new Response(description: '`nickname` exceeds 128 characters.'),
                    '403' => new Response(description: 'Not the tester themselves.'),
                    '404' => new Response(description: 'Tester not found.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Update own nickname (tester only)',
                description: <<<'MD'
> 🔒 **Required role:** tester themselves only (`IS_AUTHENTICATED_FULLY` + same UUID)

Allows a tester to update their own nickname. Max 128 characters. Admins cannot use this endpoint on behalf of a tester — use `PATCH /api/testers/{id}` for admin edits.
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['nickname'],
                            'properties' => [
                                'nickname' => ['type' => 'string', 'maxLength' => 128, 'example' => 'CoolTester42'],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
            ),
        ));

        // ── POST /api/users/{id}/profile-picture ──────────────────────────────────
        $paths->addPath('/api/users/{id}/profile-picture', new PathItem(
            post: new Operation(
                operationId: 'uploadUserProfilePicture',
                tags: ['User'],
                responses: [
                    '200' => new Response(
                        description: 'Image uploaded and URL stored.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['profilePictureUrl' => ['type' => 'string', 'format' => 'uri']],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing `file` field.'),
                    '404' => new Response(description: 'User not found.'),
                    '422' => new Response(description: 'Image validation failed (size / dimensions / mime).'),
                    '403' => new Response(description: 'Not the user themselves and not ROLE_ADMIN.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Upload a profile picture for a team user',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER` (own account) or `ROLE_ADMIN` (any account)

Accepts `multipart/form-data` with a `file` field. The image is validated, stored in S3 with public-read ACL, and the resulting URL is persisted on the user.

**Validation rules:**
- ≤ 500 KB
- ≤ 800 × 800 px
- Must be square (width === height)
- Must be `image/png` or `image/jpeg`
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['file'],
                            'properties' => [
                                'file' => ['type' => 'string', 'format' => 'binary'],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
            ),
        ));

        // ── GET /api/public-profiles/{id} ────────────────────────────────────────
        $paths->addPath('/api/public-profiles/{id}', new PathItem(
            get: new Operation(
                operationId: 'getPublicProfile',
                tags: ['PublicProfile'],
                responses: [
                    '200' => new Response(
                        description: 'Public profile',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string', 'format' => 'uuid'],
                                    'type' => ['type' => 'string', 'enum' => ['USER', 'TESTER']],
                                    'nickname' => ['type' => 'string'],
                                    'profilePictureUrl' => ['type' => 'string', 'format' => 'uri'],
                                    'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Custom tags set by the dev team'],
                                ],
                            ])),
                        ]),
                    ),
                    '404' => new Response(description: 'User not found.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Get the public profile of any user or tester',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER` or `ROLE_TESTER`

Returns the public profile of any user (team member or tester) by UUID. Exposes only non-sensitive fields: `id`, `type`, `nickname`, `profilePictureUrl`, and `roles`.
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
            ),
        ));

        // ── DELETE /api/users/{id}/profile-picture ────────────────────────────────
        $paths->addPath('/api/users/{id}/profile-picture', new PathItem(
            delete: new Operation(
                operationId: 'deleteUserProfilePicture',
                tags: ['User'],
                responses: [
                    '200' => new Response(
                        description: 'Profile picture reset to default.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['profilePictureUrl' => ['type' => 'string', 'example' => '/assets/gator_avatar.png']],
                            ])),
                        ]),
                    ),
                    '404' => new Response(description: 'User not found.'),
                    '403' => new Response(description: 'Not the user themselves and not ROLE_ADMIN.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Reset profile picture to default avatar',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER` (own account) or `ROLE_ADMIN` (any account)

Resets `profilePictureUrl` back to `/assets/gator_avatar.png`. Does not delete any file from S3.
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
            ),
        ));

        // ── POST /api/projects/{id}/project-picture ──────────────────────────────
        $paths->addPath('/api/projects/{id}/project-picture', new PathItem(
            post: new Operation(
                operationId: 'uploadProjectPicture',
                tags: ['Project'],
                responses: [
                    '200' => new Response(
                        description: 'Picture uploaded.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['projectPictureUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true]],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing `file` field.'),
                    '404' => new Response(description: 'Project not found.'),
                    '422' => new Response(description: 'Image validation failed.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                    '403' => new Response(description: 'Insufficient role.'),
                ],
                summary: 'Upload a project picture',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER`

Accepts `multipart/form-data` with a `file` field. Validates, stores in the public S3 bucket, and persists the URL on the project.

**Validation rules:**
- ≤ 500 KB
- ≤ 800 × 800 px
- Must be square (width === height)
- Must be `image/png` or `image/jpeg`
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['file'],
                            'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                        ])),
                    ]),
                    required: true,
                ),
            ),
            delete: new Operation(
                operationId: 'deleteProjectPicture',
                tags: ['Project'],
                responses: [
                    '200' => new Response(
                        description: 'Picture removed.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['projectPictureUrl' => ['type' => 'string', 'nullable' => true, 'example' => null]],
                            ])),
                        ]),
                    ),
                    '404' => new Response(description: 'Project not found.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                    '403' => new Response(description: 'Insufficient role.'),
                ],
                summary: 'Remove project picture (reset to null)',
                description: '> 🔒 **Required role:** `ROLE_USER`',
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
                ],
            ),
        ));

        // ── POST /api/projects/{id}/project-banner ────────────────────────────────
        $paths->addPath('/api/projects/{id}/project-banner', new PathItem(
            post: new Operation(
                operationId: 'uploadProjectBanner',
                tags: ['Project'],
                responses: [
                    '200' => new Response(
                        description: 'Banner uploaded.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['projectBannerUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true]],
                            ])),
                        ]),
                    ),
                    '400' => new Response(description: 'Missing `file` field.'),
                    '404' => new Response(description: 'Project not found.'),
                    '422' => new Response(description: 'Image validation failed (size / dimensions / mime).'),
                    '401' => new Response(description: 'Unauthenticated.'),
                    '403' => new Response(description: 'Insufficient role.'),
                ],
                summary: 'Upload a project banner',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER`

Accepts `multipart/form-data` with a `file` field. Validates, stores in the public S3 bucket, and persists the URL on the project.

**Validation rules:**
- < 1 MB
- ≤ 1024 px on either side (non-square allowed)
- Must be `image/png`
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['file'],
                            'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                        ])),
                    ]),
                    required: true,
                ),
            ),
            delete: new Operation(
                operationId: 'deleteProjectBanner',
                tags: ['Project'],
                responses: [
                    '200' => new Response(
                        description: 'Banner removed.',
                        content: new ArrayObject([
                            'application/json' => new MediaType(schema: new ArrayObject([
                                'type' => 'object',
                                'properties' => ['projectBannerUrl' => ['type' => 'string', 'nullable' => true, 'example' => null]],
                            ])),
                        ]),
                    ),
                    '404' => new Response(description: 'Project not found.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                    '403' => new Response(description: 'Insufficient role.'),
                ],
                summary: 'Remove project banner (reset to null)',
                description: '> 🔒 **Required role:** `ROLE_USER`',
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'integer']),
                ],
            ),
        ));

        // ── POST /api/auth/me/change-password ────────────────────────────────
        $paths->addPath('/api/auth/me/change-password', new PathItem(
            post: new Operation(
                operationId: 'changeOwnPassword',
                tags: ['Auth'],
                responses: [
                    '200' => new Response(description: 'Password updated successfully.'),
                    '400' => new Response(description: 'Missing `newPassword` field.'),
                    '422' => new Response(description: 'Password does not meet policy requirements.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                ],
                summary: 'Change own password',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_USER` (team members only — testers are excluded)

Allows any authenticated team member to change their own password.

**Password policy:** minimum 10 characters, at least one uppercase letter, one number, and one symbol.

Dispatches `UserPasswordChangedAppEvent` on success.
MD,
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['newPassword'],
                            'properties' => [
                                'newPassword' => ['type' => 'string', 'example' => 'MyN3wP@ssword'],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
            ),
        ));

        // ── POST /api/users/{id}/change-password ──────────────────────────────
        $paths->addPath('/api/users/{id}/change-password', new PathItem(
            post: new Operation(
                operationId: 'changeUserPasswordByAdmin',
                tags: ['User'],
                responses: [
                    '200' => new Response(description: 'Password updated successfully.'),
                    '400' => new Response(description: 'Missing `newPassword` field.'),
                    '404' => new Response(description: 'User not found.'),
                    '422' => new Response(description: 'Password does not meet policy requirements.'),
                    '401' => new Response(description: 'Unauthenticated.'),
                    '403' => new Response(description: 'Insufficient role.'),
                ],
                summary: 'Change password for another user (admin)',
                description: <<<'MD'
> 🔒 **Required role:** `ROLE_ADMIN`

Allows an admin to set the password of any team user account.

**Password policy:** minimum 10 characters, at least one uppercase letter, one number, and one symbol.

Dispatches `UserPasswordChangedAppEvent` on success.
MD,
                parameters: [
                    new Parameter(name: 'id', in: 'path', required: true, schema: ['type' => 'string', 'format' => 'uuid']),
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => new MediaType(schema: new ArrayObject([
                            'type' => 'object',
                            'required' => ['newPassword'],
                            'properties' => [
                                'newPassword' => ['type' => 'string', 'example' => 'MyN3wP@ssword'],
                            ],
                        ])),
                    ]),
                    required: true,
                ),
            ),
        ));

        // ── Annotate auto-generated /api/users paths ─────────────────────────
        $this->annotatePathsWithRole($paths, '/api/users', 'ROLE_ADMIN', [
            'GET' => 'List or retrieve team user accounts.',
            'POST' => 'Create a new team user account. `plainPassword` and `nickname` (max 128 chars) are required. `tags` is an optional array of strings.',
            'PATCH' => 'Partially update a team user account. `plainPassword` is optional. `nickname` max 128 chars. `tags` is an optional array of strings (dev team only).',
        ]);

        // ── Annotate auto-generated /api/tester_annotations paths ────────────────
        $this->annotatePathsWithRole($paths, '/api/tester_annotations', 'ROLE_USER', [
            'GET' => 'List or retrieve tester annotations. Filter by `?relateTo=/api/testers/{uuid}`. Order by `?order[created]=asc|desc` or `?order[updated]=asc|desc`.',
            'POST' => 'Create an annotation for a tester. `relateTo` (tester IRI) and `content` are required. `createdBy` is auto-populated from the authenticated user.',
            'PATCH' => 'Partially update an annotation. PATCH/DELETE restricted to the annotation author or ROLE_ADMIN.',
            'DELETE' => 'Delete an annotation. Restricted to the annotation author or ROLE_ADMIN.',
        ]);

        // ── Annotate auto-generated /api/tester_tags paths ───────────────────────
        $this->annotatePathsWithRole($paths, '/api/tester_tags', 'ROLE_USER or ROLE_TESTER (read) / ROLE_USER (write)', [
            'GET' => 'List or retrieve tester tags. Filter by `?label=` (partial). Includes soft-deleted tags (`deleted=true`).',
            'POST' => 'Create a new tag. `label` is required (max 128 chars). `id` (slug) is auto-derived from `label` but may be supplied. `createdBy` is auto-populated.',
            'DELETE' => 'Soft-delete the tag (`deleted` is set to `true`; row is kept).',
        ], [
            'POST' => 'ROLE_USER',
            'DELETE' => 'ROLE_USER',
        ]);

        // ── Annotate auto-generated /api/testers paths ────────────────────────
        $this->annotatePathsWithRole($paths, '/api/testers', 'ROLE_USER', [
            'GET' => 'List or retrieve tester accounts. Response includes `tags` (string array set by dev team).',
            'POST' => 'Create a new tester account.',
            'PATCH' => 'Partially update a tester account. `tags` is writable — dev team only (testers cannot call this endpoint).',
            'PUT' => 'Replace a tester account.',
            'DELETE' => 'Delete a tester account.',
        ]);

        // ── Annotate auto-generated /api/settings paths ───────────────────────
        $this->annotatePathsWithRole(
            $paths,
            '/api/settings',
            'ROLE_USER, ROLE_TESTER, or ROLE_ADMIN',
            [
                'GET' => 'Non-admin users (ROLE_USER, ROLE_TESTER) only see settings where `public = true`. ROLE_ADMIN sees all settings.',
                'POST' => 'Create a new setting. `id` is auto-computed as `{section}.{name}`.',
                'PATCH' => 'Partially update an existing setting.',
                'DELETE' => 'Delete a setting.',
            ],
            [
                'POST' => 'ROLE_ADMIN',
                'PATCH' => 'ROLE_ADMIN',
                'DELETE' => 'ROLE_ADMIN',
            ],
        );

        return $openApi;
    }

    /**
     * Prepends a role badge and description note to every operation on paths
     * whose URI starts with $prefix.
     *
     * @param array<string, string> $methodDescriptions HTTP method (uppercase) → extra description line
     * @param array<string, string> $methodRoles        HTTP method (uppercase) → role override (falls back to $defaultRole)
     */
    private function annotatePathsWithRole(
        Paths $paths,
        string $prefix,
        string $defaultRole,
        array $methodDescriptions = [],
        array $methodRoles = [],
    ): void {
        foreach ($paths->getPaths() as $path => $pathItem) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            // Process each HTTP method, updating $pathItem sequentially.
            // Closures must NOT be used — arrow functions capture $pathItem by value
            // at construction time, so each withXxx() call would silently revert
            // the previous method's update.
            foreach (['get', 'post', 'patch', 'put', 'delete'] as $method) {
                $operation = match ($method) {
                    'get' => $pathItem->getGet(),
                    'post' => $pathItem->getPost(),
                    'patch' => $pathItem->getPatch(),
                    'put' => $pathItem->getPut(),
                    'delete' => $pathItem->getDelete(),
                };

                if ($operation === null) {
                    continue;
                }

                $role = $methodRoles[strtoupper($method)] ?? $defaultRole;
                $roleBlock = $role !== '' ? sprintf('> 🔒 **Required role:** `%s`', $role) : '';
                $extra = $methodDescriptions[strtoupper($method)] ?? '';
                $current = $operation->getDescription() ?? '';
                $parts = array_filter([$roleBlock, $extra, $current]);
                $updated = $operation->withDescription(implode("\n\n", $parts));

                $pathItem = match ($method) {
                    'get' => $pathItem->withGet($updated),
                    'post' => $pathItem->withPost($updated),
                    'patch' => $pathItem->withPatch($updated),
                    'put' => $pathItem->withPut($updated),
                    'delete' => $pathItem->withDelete($updated),
                };
            }

            $paths->addPath($path, $pathItem);
        }
    }
}
