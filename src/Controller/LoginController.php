<?php

namespace App\Controller;

use App\Services\Authentification\GuestAuthService;
use App\Services\Authentification\JWTService;
use App\Services\Authentification\LdapService;
use App\Services\Authentification\RefreshTokenService;
use App\Services\Entities\TesterManager;
use App\Services\Entities\TestPlanManager;
use App\Services\Entities\UserBuiltInDbService;
use App\Services\LoginRateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    // ── POST /api/auth/login ──────────────────────────────────────────────

    #[Route('/api/auth/login', name: 'app_login', methods: ['POST'])]
    public function index(
        Request                 $request,
        UserBuiltInDbService    $userBuiltInDbService,
        TesterManager           $testerManager,
        LdapService             $ldapService,
        JWTService              $jwtService,
        RefreshTokenService     $refreshTokenService,
        LoginRateLimiterService $rateLimiter,
    ): Response
    {
        $username = $request->getPayload()?->get('username');
        $password = $request->getPayload()?->get('password');
        $authMode = $request->getPayload()?->get('authMode', 'app');
        $mode = $request->getPayload()?->get('mode', 'team');

        if ($username === null || $password === null) {
            return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
        }

        if (!in_array($mode, ['team', 'tester'], true)) {
            return $this->json(['logged' => false, 'error' => 'Invalid mode'], 400);
        }

        if (!in_array($authMode, ['app', 'ldap', 'code'], true)) {
            return $this->json(['logged' => false, 'error' => 'Invalid authMode'], 400);
        }

        // ── Rate limiting ─────────────────────────────────────────────────
        // Applied before any credential check so the window counts every
        // attempt, including structurally valid ones that fail authentication.
        // The 'code' OTP flow does not check a password, so skip it there.
        if ($authMode !== 'code') {
            $ip = $request->getClientIp() ?? 'unknown';
            $limit = $rateLimiter->attempt($ip, $username);

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

            if ($authMode === 'ldap') {
                try {
                    $userData = $ldapService->checkUserLogin($username, $password);
                    if ($userData['email'] === null) {
                        return $this->json(['logged' => false, 'error' => 'Invalid credentials'], 403);
                    }
                } catch (\Exception $e) {
                    return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
                }
                $user = $userBuiltInDbService->getUserByEmail($userData['email'])
                    ?? $userBuiltInDbService->createUser($userData, 'ldap');

            } elseif ($authMode === 'app') {
                try {
                    $user = $userBuiltInDbService->checkUserLogin($username, $password);
                } catch (\Throwable $e) {
                    return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
                }
            }

            if ($user === null) {
                return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
            }

            // Successful login — clear the attempt counter so a valid user
            // does not get locked out after a previous typo run.
            $rateLimiter->reset($request->getClientIp() ?? 'unknown', $username);

            return $this->json([
                'logged' => true,
                'jwt' => $jwtService->getJWT($user, false, ['mode' => 'team', 'authMode' => $authMode]),
                'refreshToken' => $refreshTokenService->issue($user, 'user'),
                'authMode' => $authMode,
            ]);
        }

        // ── Tester ────────────────────────────────────────────────────────
        $tester = $testerManager->getTesterByEmail($username);
        if ($tester === null || $tester->isActive() === false) {
            return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
        }

        if ($authMode === 'code') {
            $testerManager->updateTesterCode($tester);
            return $this->json(['logged' => false, 'otp' => true], 200);
        }

        if ($authMode === 'app') {
            try {
                $tester = $testerManager->checkTesterLogin($username, $password);
            } catch (\Throwable $e) {
                return $this->json(['logged' => false, 'error' => $e->getMessage()], 403);
            }

            if ($tester === null) {
                return $this->json(['logged' => false, 'error' => 'Not logged in'], 401);
            }

            // Successful tester login — reset counter.
            $rateLimiter->reset($request->getClientIp() ?? 'unknown', $username);

            return $this->json([
                'logged' => true,
                'jwt' => $jwtService->getJWT($tester, false, ['mode' => 'tester', 'authMode' => $authMode]),
                'refreshToken' => $refreshTokenService->issue($tester, 'tester'),
                'authMode' => $authMode,
            ]);
        }

        return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
    }

    // ── POST /api/auth/login_tester ───────────────────────────────────────

    #[Route('/api/auth/login_tester', name: 'app_login_tester', methods: ['POST'])]
    public function loginTester(
        Request             $request,
        GuestAuthService    $guestAuthService,
        JWTService          $jwtService,
        TestPlanManager     $testPlanManager,
        RefreshTokenService $refreshTokenService,
    ): Response
    {
        $challenge = $request->getPayload()?->get('challenge');
        $hash = $request->getPayload()?->get('hash');
        $testPlanId = $request->getPayload()?->get('tp');

        if ($challenge === null || $hash === null || $testPlanId === null) {
            return $this->json(['logged' => false, 'error' => 'Invalid request'], 400);
        }

        $tp = $testPlanManager->getTestPlanById((int)$testPlanId);
        if ($tp === null) {
            return $this->json(['error' => 'Invalid TestPlan'], 404);
        }

        if (!$guestAuthService->validateHash($challenge, $hash, $tp->getKey())) {
            return $this->json(['error' => 'Invalid hash'], 401);
        }

        return $this->json([
            'logged' => true,
            'jwt' => $jwtService->generateToken($this->buildGuestPayload($tp->getId())),
            'refreshToken' => $refreshTokenService->issueGuest($challenge, ['tp_id' => $tp->getId()]),
            'authMode' => 'Tester',
        ]);
    }

    // ── POST /api/auth/refresh ────────────────────────────────────────────

    #[Route('/api/auth/refresh', name: 'app_refresh', methods: ['POST'])]
    public function refresh(
        Request              $request,
        JWTService           $jwtService,
        RefreshTokenService  $refreshTokenService,
        UserBuiltInDbService $userBuiltInDbService,
        TesterManager        $testerManager,
        TestPlanManager      $testPlanManager,
    ): Response
    {
        $rawToken = $request->getPayload()?->get('refreshToken');

        if ($rawToken === null || $rawToken === '') {
            return $this->json(['error' => 'Missing refreshToken'], 400);
        }

        try {
            $result = $refreshTokenService->consume($rawToken);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }

        // ── Guest (login_tester) ──────────────────────────────────────────
        if ($result['userType'] === 'guest') {
            $tpId = $result['extra']['tp_id'] ?? null;
            if ($tpId === null) {
                return $this->json(['error' => 'Invalid guest token context'], 401);
            }

            $tp = $testPlanManager->getTestPlanById((int)$tpId);
            if ($tp === null) {
                return $this->json(['error' => 'TestPlan no longer exists'], 401);
            }

            return $this->json([
                'jwt' => $jwtService->generateToken($this->buildGuestPayload($tp->getId())),
                'refreshToken' => $result['refreshToken'],
            ]);
        }

        // ── Tester (OTP login) ────────────────────────────────────────────
        if ($result['userType'] === 'tester') {
            $tester = $testerManager->getTesterByGuid($result['userGuid']);
            if ($tester === null || !$tester->isActive()) {
                return $this->json(['error' => 'Tester not found or inactive'], 401);
            }

            return $this->json([
                'jwt' => $jwtService->getJWT($tester, false, ['mode' => 'tester', 'authMode' => 'refresh']),
                'refreshToken' => $result['refreshToken'],
            ]);
        }

        // ── Team user ─────────────────────────────────────────────────────
        $user = $userBuiltInDbService->getUserByGuid($result['userGuid']);
        if ($user === null) {
            return $this->json(['error' => 'User not found'], 401);
        }

        return $this->json([
            'jwt' => $jwtService->getJWT($user, false, ['mode' => 'team', 'authMode' => 'refresh']),
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
