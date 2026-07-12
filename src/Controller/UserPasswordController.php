<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Services\Authentification\PasswordService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserPasswordController extends AbstractController
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {
    }

    /**
     * POST /api/auth/me/change-password.
     *
     * Allows any authenticated team member (ROLE_USER, type != TESTER) to change
     * their own password. Testers are excluded because they only receive ROLE_TESTER.
     */
    #[Route('/api/auth/me/change-password', name: 'user_change_own_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changeOwnPassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->doChangePassword($user, $request);
    }

    /**
     * POST /api/users/{id}/change-password.
     *
     * Allows a ROLE_ADMIN to change the password of any team user account.
     */
    #[Route('/api/users/{id}/change-password', name: 'user_change_password_admin', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changePasswordByAdmin(string $id, Request $request): JsonResponse
    {
        $newPassword = $request->getPayload()->get('newPassword');

        if ($newPassword === null || $newPassword === '') {
            return $this->json(['error' => 'newPassword is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->passwordService->changePasswordById($id, $newPassword);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$user instanceof User) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true], Response::HTTP_OK);
    }

    // ── Shared logic ──────────────────────────────────────────────────────────

    private function doChangePassword(User $user, Request $request): JsonResponse
    {
        $newPassword = $request->getPayload()->get('newPassword');

        if ($newPassword === null || $newPassword === '') {
            return $this->json(['error' => 'newPassword is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->passwordService->changePassword($user, $newPassword);
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['success' => true], Response::HTTP_OK);
    }
}
