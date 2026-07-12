<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Enum\UserType;
use App\Services\Authentification\JWTService;
use App\Tests\DataFixtures\TestFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected static KernelBrowser $client;
    protected static EntityManagerInterface $em;
    protected static ContainerInterface $container;

    protected static bool $fixturesLoaded = false;

    /** Error handler on top of the stack before the test body runs. */
    private mixed $baselineErrorHandler = null;

    /** Exception handler on top of the stack before the test body runs. */
    private mixed $baselineExceptionHandler = null;

    protected function setUp(): void
    {
        // Snapshot the current (PHPUnit-owned) error/exception handlers so
        // tearDown can pop anything the request lifecycle leaves behind.
        $this->baselineErrorHandler = self::peekErrorHandler();
        $this->baselineExceptionHandler = self::peekExceptionHandler();

        parent::setUp();

        static::$client = static::createClient();
        static::$container = static::getContainer();
        static::$em = static::$container->get('doctrine.orm.entity_manager');

        // Login rate-limit counters live in the default cache pool and persist
        // in the filesystem between runs. Resetting a fixed list of emails misses
        // ad-hoc usernames used by negative-path tests (nobody@, ghost@, generated
        // ones), letting their counters accumulate across runs until they trip a
        // 429. Clear the whole pool so every test starts with a clean window.
        static::$container->get('cache.app')->clear();

        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(
            static::$container->get(TestFixtures::class)
        );

        $executor = new ORMExecutor(
            static::$em,
            new ORMPurger(static::$em),
        );
        $executor->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Some request code paths (e.g. Sentry's init(), certain vendor helpers)
        // leave an error/exception handler registered. PHPUnit 11+ flags such a
        // test as risky ("did not remove its own error/exception handlers").
        // Pop anything added since setUp, down to PHPUnit's baseline. This is a
        // no-op when nothing leaked and never touches PHPUnit's own handlers.
        self::popErrorHandlersDownTo($this->baselineErrorHandler);
        self::popExceptionHandlersDownTo($this->baselineExceptionHandler);
    }

    /** Returns the current top error handler without altering the stack. */
    private static function peekErrorHandler(): mixed
    {
        $handler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        return $handler;
    }

    /** Returns the current top exception handler without altering the stack. */
    private static function peekExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    private static function popErrorHandlersDownTo(mixed $baseline): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $top = self::peekErrorHandler();
            if ($top === $baseline || $top === null) {
                return;
            }
            restore_error_handler();
        }
    }

    private static function popExceptionHandlersDownTo(mixed $baseline): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $top = self::peekExceptionHandler();
            if ($top === $baseline || $top === null) {
                return;
            }
            restore_exception_handler();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auth helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns a JWT for the default team user (ROLE_USER).
     */
    protected function getTeamUserToken(): string
    {
        $user = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'Test user not found — fixtures may not have loaded.');

        /** @var JWTService $jwt */
        $jwt = static::$container->get(JWTService::class);

        return $jwt->getJWT($user, false, ['mode' => 'team', 'authMode' => 'app']);
    }

    /**
     * Returns a JWT for the admin user (ROLE_USER + ROLE_ADMIN).
     */
    protected function getAdminToken(): string
    {
        $admin = static::$em->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin user not found — fixtures may not have loaded.');

        /** @var JWTService $jwt */
        $jwt = static::$container->get(JWTService::class);

        return $jwt->getJWT($admin, false, ['mode' => 'team', 'authMode' => 'app']);
    }

    /**
     * Returns a JWT for a tester (ROLE_TESTER).
     * Defaults to the enrolled tester; pass TestFixtures::TESTER_EMAIL_2
     * for the tester with no test plan assignment.
     */
    protected function getTesterToken(string $email = TestFixtures::TESTER_EMAIL): string
    {
        $tester = static::$em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::TESTER]);

        self::assertNotNull($tester, 'Test tester not found — fixtures may not have loaded.');

        /** @var JWTService $jwt */
        $jwt = static::$container->get(JWTService::class);

        return $jwt->getJWT($tester, false, ['mode' => 'tester', 'authMode' => 'app']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function jsonRequest(
        string $method,
        string $uri,
        ?array $payload = null,
        ?string $token = null,
    ): array {
        $headers = [
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => 'application/ld+json',
        ];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        static::$client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            $payload !== null ? json_encode($payload) : null,
        );

        $response = static::$client->getResponse();
        $content = $response->getContent();

        return json_decode($content ?: '{}', true) ?? [];
    }

    protected function getStatusCode(): int
    {
        return static::$client->getResponse()->getStatusCode();
    }

    protected function assertStatusCode(int $expected): void
    {
        self::assertSame(
            $expected,
            $this->getStatusCode(),
            'Response body: '.static::$client->getResponse()->getContent(),
        );
    }

    protected function assertJsonKey(string $key, mixed $data): void
    {
        self::assertArrayHasKey($key, $data, "Response JSON is missing key '{$key}'.");
    }
}
