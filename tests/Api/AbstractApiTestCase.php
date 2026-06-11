<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Enum\UserType;
use App\Services\Authentification\JWTService;
use App\Tests\DataFixtures\TestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected static KernelBrowser $client;
    protected static EntityManagerInterface $em;
    protected static ContainerInterface $container;

    protected static bool $fixturesLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        static::$client = static::createClient();
        static::$container = static::getContainer();
        static::$em = static::$container->get('doctrine.orm.entity_manager');

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
        string  $method,
        string  $uri,
        ?array  $payload = null,
        ?string $token = null,
    ): array
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/ld+json',
            'CONTENT_TYPE' => 'application/ld+json',
        ];

        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
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
            'Response body: ' . static::$client->getResponse()->getContent(),
        );
    }

    protected function assertJsonKey(string $key, mixed $data): void
    {
        self::assertArrayHasKey($key, $data, "Response JSON is missing key '{$key}'.");
    }
}
