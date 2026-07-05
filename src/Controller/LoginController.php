<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TestPlan;
use App\Entity\User;
use App\Services\Authentification\GuestAuthService;
use App\Services\Authentification\JWTService;
use App\Services\Authentification\LdapService;
use App\Services\Authentification\RefreshTokenService;
use App\Services\Entities\TesterManager;
use App\Services\Entities\TestPlanManager;
use App\Services\Entities\UserBuiltInDbService;
use App\Services\LoginRateLimiterService;
use Exception;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class LoginController extends AbstractController
{
    public function __construct(private readonly UserBuiltInDbService $userBuiltInDbService, private readonly TesterManager $testerManager, private readonly LdapService $ldapService, private readonly JWTService $jwtService, private readonly RefreshTokenService $refreshTokenService, private readonly LoginRateLimiterService $rateLimiter, private readonly GuestAuthService $guestAuthService, private readonly TestPlanManager $testPlanManager)
    {
    }
    // ── POST /api/auth/login ──────────────────────────────────────────────

    #[Route('/api/auth/login', name: 'app_login', methods: ['POST'])]
    public function index(
        Request $request,
        #[Autowire(env: 'APP_AUTH_MODE')]
        string $appAuthMode,
    ): JsonResponse
    {
        $username = $request->getPayload()->get('username');
        $password = $request->getPayload()->get('password');
        $authMode = $request->getPayload()->get('authMode', 'app');
        $mode = $request->getPayload()->get('mode', 'team');

        if ($username === null || $password === null) {
            return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
        }

        if (!in_array($mode, ['team', 'tester'], true)) {
            return $this->json(['logged' => false, 'error' => 'Invalid mode'], 400);
        }

        if (!in_array($authMode, ['app', 'db', 'ldap', 'code'], true)) {
            return $this->json(['logged' => false, 'error' => 'Invalid authMode'], 400);
        }

        // ── Rate limiting ─────────────────────────────────────────────────
        // Applied before any credential check so the window counts every
        // attempt, including structurally valid ones that fail authentication.
        // The 'code' OTP flow does not check a password, so skip it there.
        if ($authMode !== 'code') {
            $ip = $request->getClientIp() ?? 'unknown';
            $limit = $this->rateLimiter->attempt($ip, $username);

            if (!$limit['allowed']) {
                return $this->json(
                    ['logged' => false, 'error' => 'Too many login attempts. Try again later.'],
                    429,
                    ['Retry-After' => (string)$limit['retryAfter']],
                );
            }
        }

        // ── Team user ─────────────────────────────────────────────────────
        if ($mode === 'team') {
            $user = null;

            // APP_AUTH_MODE (env) decides the backend — not the client-supplied authMode.
            if ($appAuthMode === 'ldap') {
                try {
                    $userData = $this->ldapService->checkUserLogin($username, $password);
                    if ($userData['email'] === null) {
                        return $this->json(['logged' => false, 'error' => 'Invalid credentials'], 403);
                    }
                } catch (Exception $e) {
                    return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
                }
                $user = $this->userBuiltInDbService->getUserByEmail($userData['email'])
                    ?? $this->userBuiltInDbService->createUser($userData, 'ldap');
            } else {
                // APP_AUTH_MODE=db — authenticate against the local password hash.
                // Users with no password set (e.g. created via LDAP sync) are blocked
                // until an admin sets a password via POST /api/users/{id}/change-password.
                try {
                    $user = $this->userBuiltInDbService->checkUserLogin($username, $password);
                } catch (Throwable $e) {
                    return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
                }
            }

            if (!$user instanceof User) {
                return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
            }

            // Successful login — clear the attempt counter so a valid user
            // does not get locked out after a previous typo run.
            $this->rateLimiter->reset($request->getClientIp() ?? 'unknown', $username);

            return $this->json([
                'logged' => true,
                'jwt' => $this->jwtService->getJWT($user, false, ['mode' => 'team', 'authMode' => $appAuthMode]),
                'refreshToken' => $this->refreshTokenService->issue($user, 'user'),
                'authMode' => $appAuthMode,
            ]);
        }

        // ── Tester ────────────────────────────────────────────────────────
        $tester = $this->testerManager->getTesterByEmail($username);
        if (!$tester instanceof User || $tester->isActive() === false) {
            return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
        }

        if ($authMode === 'code') {
            $this->testerManager->updateTesterCode($tester);

            return $this->json(['logged' => false, 'otp' => true], 200);
        }

        if ($authMode === 'app') {
            try {
                $tester = $this->testerManager->checkTesterLogin($username, $password);
            } catch (Throwable $e) {
                return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
            }

            if (!$tester instanceof User) {
                return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
            }

            // Successful tester login — reset counter.
            $this->rateLimiter->reset($request->getClientIp() ?? 'unknown', $username);

            return $this->json([
                'logged' => true,
                'jwt' => $this->jwtService->getJWT($tester, false, ['mode' => 'tester', 'authMode' => $authMode]),
                'refreshToken' => $this->refreshTokenService->issue($tester, 'tester'),
                'authMode' => $authMode,
            ]);
        }

        return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
    }

    // ── GET /api/auth/mode ────────────────────────────────────────────────

    /**
     * Returns the server-side authentication mode so the frontend can adapt
     * its login form accordingly (e.g. label the username field "Email" in
     * db mode vs "Username / CN" in ldap mode).
     *
     * Public — no authentication required.
     */
    #[Route('/api/auth/mode', name: 'app_auth_mode', methods: ['GET'])]
    public function authMode(
        #[Autowire(env: 'APP_AUTH_MODE')]
        string $appAuthMode,
    ): JsonResponse
    {
        return $this->json([
            'mode' => $appAuthMode,
            // In db mode the login identifier is always the user's e-mail address.
            'usernameIsEmail' => $appAuthMode === 'db',
        ]);
    }

    // ── POST /api/auth/login_tester ───────────────────────────────────────

    #[Route('/api/auth/login_tester', name: 'app_login_tester', methods: ['POST'])]
    public function loginTester(
        Request $request,
    ): JsonResponse
    {
        $challenge = $request->getPayload()->get('challenge');
        $hash = $request->getPayload()->get('hash');
        $testPlanId = $request->getPayload()->get('tp');

        if ($challenge === null || $hash === null || $testPlanId === null) {
            return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
        }

        $tp = $this->testPlanManager->getTestPlanById((int)$testPlanId);
        if (!$tp instanceof TestPlan) {
            return $this->json(['error' => 'Invalid TestPlan'], 404);
        }

        if (!$this->guestAuthService->validateHash($challenge, $hash, $tp->getKey())) {
            return $this->json(['error' => 'Invalid hash'], 401);
        }

        return $this->json([
            'logged' => true,
            'jwt' => $this->jwtService->generateToken($this->buildGuestPayload($tp->getId())),
            'refreshToken' => $this->refreshTokenService->issueGuest($challenge, ['tp_id' => $tp->getId()]),
            'authMode' => 'Tester',
        ]);
    }

    // ── POST /api/auth/refresh ────────────────────────────────────────────

    #[Route('/api/auth/refresh', name: 'app_refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
    ): JsonResponse
    {
        $rawToken = $request->getPayload()->get('refreshToken');

        if ($rawToken === null || $rawToken === '') {
            return $this->json(['error' => 'Missing refreshToken'], 400);
        }

        try {
            $result = $this->refreshTokenService->consume($rawToken);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }

        // ── Guest (login_tester) ──────────────────────────────────────────
        if ($result['userType'] === 'guest') {
            $tpId = $result['extra']['tp_id'] ?? null;
            if ($tpId === null) {
                return $this->json(['error' => 'Invalid guest token context'], 401);
            }

            $tp = $this->testPlanManager->getTestPlanById((int)$tpId);
            if (!$tp instanceof TestPlan) {
                return $this->json(['error' => 'TestPlan no longer exists'], 401);
            }

            return $this->json([
                'jwt' => $this->jwtService->generateToken($this->buildGuestPayload($tp->getId())),
                'refreshToken' => $result['refreshToken'],
            ]);
        }

        // ── Tester (OTP login) ────────────────────────────────────────────
        if ($result['userType'] === 'tester') {
            $tester = $this->testerManager->getTesterByGuid($result['userGuid']);
            if (!$tester instanceof User || !$tester->isActive()) {
                return $this->json(['error' => 'Tester not found or inactive'], 401);
            }

            return $this->json([
                'jwt' => $this->jwtService->getJWT($tester, false, ['mode' => 'tester', 'authMode' => 'refresh']),
                'refreshToken' => $result['refreshToken'],
            ]);
        }

        // ── Team user ─────────────────────────────────────────────────────
        $user = $this->userBuiltInDbService->getUserByGuid($result['userGuid']);
        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found'], 401);
        }

        return $this->json([
            'jwt' => $this->jwtService->getJWT($user, false, ['mode' => 'team', 'authMode' => 'refresh']),
            'refreshToken' => $result['refreshToken'],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildGuestPayload(int $tpId): array
    {
        return [
            'authMode' => 'Tester',
            'tp' => ['id' => $tpId, '@id' => '/api/test_plans/' . $tpId],
            'exp' => time() + 3 * 60 * 60,
            'scope' => [
                'web/app/guest',
                'web/api/guest',
                'web/app/tp/' . $tpId,
                'web/api/tp/' . $tpId,
            ],
        ];
    }
}
