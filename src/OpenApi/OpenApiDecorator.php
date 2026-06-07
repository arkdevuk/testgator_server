<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Injects custom (non-ApiResource) routes into the OpenAPI spec:
 *   - POST /api/auth/login
 *   - POST /api/auth/login_tester
 *   - POST /api/auth/refresh
 *   - GET  /api/questions/{id}/stats
 */
final class OpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $inner,
    )
    {
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
                summary: 'Authenticate a team member or tester',
                requestBody: new RequestBody(
                    description: 'Login credentials',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'required' => ['username', 'password'],
                            'properties' => [
                                'username' => ['type' => 'string', 'example' => 'admin@testgator.test'],
                                'password' => ['type' => 'string', 'example' => 'Password1!'],
                                'authMode' => ['type' => 'string', 'enum' => ['app', 'ldap', 'code'], 'default' => 'app'],
                                'mode' => ['type' => 'string', 'enum' => ['team', 'tester'], 'default' => 'team'],
                            ],
                        ])),
                    ])
                ),
                responses: [
                    '200' => new Response(
                        description: 'Authenticated',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject([
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
                security: [],
            ),
        ));

        // ── Auth: POST /api/auth/login_tester ─────────────────────────────
        $paths->addPath('/api/auth/login_tester', new PathItem(
            post: new Operation(
                operationId: 'postAuthLoginTester',
                tags: ['Auth'],
                summary: 'Authenticate a guest tester via HMAC challenge',
                requestBody: new RequestBody(
                    description: 'HMAC challenge payload',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'required' => ['challenge', 'hash', 'tp'],
                            'properties' => [
                                'challenge' => ['type' => 'string'],
                                'hash' => ['type' => 'string'],
                                'tp' => ['type' => 'integer', 'description' => 'TestPlan ID'],
                            ],
                        ])),
                    ])
                ),
                responses: [
                    '200' => new Response(
                        description: 'Authenticated',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject([
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
                security: [],
            ),
        ));

        // ── Auth: POST /api/auth/refresh ──────────────────────────────────
        $paths->addPath('/api/auth/refresh', new PathItem(
            post: new Operation(
                operationId: 'postAuthRefresh',
                tags: ['Auth'],
                summary: 'Rotate a refresh token and obtain a new JWT',
                requestBody: new RequestBody(
                    description: 'Refresh token',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'required' => ['refreshToken'],
                            'properties' => [
                                'refreshToken' => ['type' => 'string'],
                            ],
                        ])),
                    ])
                ),
                responses: [
                    '200' => new Response(
                        description: 'New JWT issued',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject([
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
                security: [],
            ),
        ));

        // ── POST /api/demo/add_demo_answer ────────────────────────────────
        $paths->addPath('/api/demo/add_demo_answer', new PathItem(
            post: new Operation(
                operationId: 'postDemoAddDemoAnswer',
                tags: ['Demo'],
                summary: 'Seed random demo answers into a testing plan',
                description: 'Creates `number` answers (default 10, max 100) distributed randomly across all questions in the given testing plan. Each answer gets a random state (pass / pass_with_bugs / failed / blocked / pending) and a pre-written canned comment. Useful for populating a demo environment with realistic data.',
                requestBody: new RequestBody(
                    description: 'Demo seed parameters',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
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
                    ])
                ),
                responses: [
                    '201' => new Response(
                        description: 'Answers created',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject([
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
            ),
        ));

        // ── GET /api/questions/{id}/stats ─────────────────────────────────
        $paths->addPath('/api/questions/{id}/stats', new PathItem(
            get: new Operation(
                operationId: 'getQuestionStats',
                tags: ['Question'],
                summary: 'Answer statistics for a question',
                parameters: [
                    new Parameter(
                        name: 'id',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'integer'],
                    ),
                ],
                responses: [
                    '200' => new Response(
                        description: 'Statistics',
                        content: new \ArrayObject([
                            'application/json' => new MediaType(schema: new \ArrayObject([
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
            ),
        ));

        return $openApi;
    }
}
