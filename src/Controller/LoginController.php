<?php

namespace App\Controller;

use App\Services\JWTService;
use App\Services\LdapService;
use App\Services\UserBuiltInDbService;
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

        // todo generate JWT
        $jwt = $jwtService->getJWT($user, false, [
            'authMode' => $authMode
        ]);

        return $this->json([
            'logged' => true,
            'jwt' => $jwt,
            'authMode' => $authMode,
        ]);
    }
}
