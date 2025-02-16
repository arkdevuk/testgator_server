<?php

namespace App\Controller;

use App\Services\Authentification\GuestAuthService;
use App\Services\Authentification\JWTService;
use App\Services\Authentification\LdapService;
use App\Services\Entities\TestPlanManager;
use App\Services\Entities\UserBuiltInDbService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    #[Route('/apx/login', name: 'app_login')]
    public function index(
        Request              $request,
        UserBuiltInDbService $userBuiltInDbService,
        LdapService          $ldapService,
        JWTService           $jwtService,
    ): Response
    {
        // get json body
        $username = $request->getPayload()?->get('username');
        $password = $request->getPayload()?->get('password');
        $authMode = $request->getPayload()?->get('authMode', 'app');

        if ($username === null || $password === null || $request->getMethod() !== 'POST') {
            return $this->json([
                'logged' => false,
                'error' => 'Invalid request'
            ], 400);
        }

        // authMode : app|ldap
        $user = null;
        $userData = null;

        if ($authMode === 'ldap') {
            try {
                $userData = $ldapService->checkUserLogin($username, $password);
                if ($userData['email'] === null) {
                    return $this->json([
                        'logged' => false,
                        'error' => 'Invalid credentials'
                    ], 403);
                }
            } catch (\Exception $e) {
                return $this->json([
                    'logged' => false,
                    'error' => $e->getMessage()
                ], 403);
            }
            $user = $userBuiltInDbService->getUserByEmail($userData['email']);
            if ($user === null) {
                $user = $userBuiltInDbService->createUser($userData, 'ldap');
            }

        } else if ($authMode === 'app') {
            try {
                $user = $userBuiltInDbService->checkUserLogin($username, $password);
            } catch (\Throwable $e) {
                return $this->json([
                    'logged' => false,
                    'error' => $e->getMessage()
                ], 403);
            }
        }

        if ($user === null) {
            return $this->json([
                'logged' => false,
                'error' => 'Not logged in'
            ], 401);
        }

        // generate JWT
        $jwt = $jwtService->getJWT($user, false, [
            'authMode' => $authMode
        ]);

        return $this->json([
            'logged' => true,
            'jwt' => $jwt,
            'authMode' => $authMode,
        ]);
    }

    #[Route('/apx/login_tester', name: 'app_login_tester')]
    public function loginTester(
        Request          $request,
        GuestAuthService $guestAuthService,
        JWTService       $jwtService,
        TestPlanManager  $testPlanManager,
    ): Response
    {
        // get json body
        $challenge = $request->getPayload()?->get('challenge');
        $hash = $request->getPayload()?->get('hash');
        $testPlanId = $request->getPayload()?->get('tp');


        $authMode = 'Tester';

        if ($challenge === null
            || $hash === null
            || $testPlanId === null
            || $request->getMethod() !== 'POST') {
            return $this->json([
                'logged' => false,
                'error' => 'Invalid request'
            ], 400);
        }

        $tp = $testPlanManager->getTestPlanById((int)$testPlanId);
        if ($tp === null) {
            return $this->json([
                'error' => 'Invalid TestPlan',
            ], 404);
        }

        // validate the hash
        if (!$guestAuthService->validateHash(
            $challenge,
            $hash,
            $tp->getKey(),
        )) {
            return $this->json([
                'error' => 'Invalid hash',
            ], 401);
        }
        // expire in 3 hours
        $expire = time() + 3 * 60 * 60;

        // todo get Tester in DB, $challenge is the uuid of the tester

        $payload = [
            'authMode' => $authMode,
            'tp' => [
                'id' => $tp->getId(),
                '@id' => '/api/test_plans/' . $tp->getId(),
            ],
            'exp' => $expire,
            'scope' => [
                'web/app/guest',
                'web/api/guest',
                'web/app/tp/' . $tp->getId(),
                'web/api/tp/' . $tp->getId(),
            ]
        ];

        $jwt = $jwtService->generateToken($payload);


        return $this->json([
            'logged' => true,
            'jwt' => $jwt,
            'authMode' => $authMode,
        ]);
    }
}
