<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserType;
use App\Repository\UserRepository;
use App\Services\ProfilePictureService;
use Doctrine\ORM\EntityManagerInterface;

use const FILTER_VALIDATE_URL;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfilePictureController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ProfilePictureService $profilePictureService,
        private readonly Security       $security,
    )
    {
    }

    // ── POST /api/testers/{id}/profile-picture ────────────────────────────────

    /**
     * Sets profilePictureUrl from a URL payload.
     *
     * Accessible by the tester themselves or by ROLE_ADMIN.
     *
     * Body: { "url": "https://..." }
     */
    #[Route('/api/testers/{id}/profile-picture', name: 'tester_set_profile_picture_url', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function setTesterProfilePictureUrl(string $id, Request $request): JsonResponse
    {
        $tester = $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::TESTER]);

        if ($tester === null) {
            return $this->json(['error' => 'Tester not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $isSelf = $currentUser instanceof User
            && (string)$currentUser->getId() === (string)$tester->getId();
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        if (!$isSelf && !$isAdmin) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $url = $request->getPayload()->get('url');

        if ($url === null || $url === '') {
            return $this->json(['error' => '`url` is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['error' => '`url` must be a valid URL.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tester->setProfilePictureUrl($url);
        $this->em->flush();

        return $this->json(['profilePictureUrl' => $tester->getProfilePictureUrl()]);
    }

    // ── POST /api/testers/{id}/nickname ──────────────────────────────────────

    /**
     * Updates the nickname of a tester.
     * Only accessible by the tester themselves.
     *
     * Body: { "nickname": "string" }
     */
    #[Route('/api/testers/{id}/nickname', name: 'tester_set_nickname', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function setTesterNickname(string $id, Request $request): JsonResponse
    {
        $tester = $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::TESTER]);

        if ($tester === null) {
            return $this->json(['error' => 'Tester not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $isSelf = $currentUser instanceof User
            && (string)$currentUser->getId() === (string)$tester->getId();

        if (!$isSelf) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $nickname = $request->getPayload()->get('nickname');

        if ($nickname === null || trim($nickname) === '') {
            return $this->json(['error' => '`nickname` is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($nickname) > 128) {
            return $this->json(['error' => '`nickname` must not exceed 128 characters.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tester->setNickname(trim($nickname));
        $this->em->flush();

        return $this->json(['nickname' => $tester->getNickname()]);
    }

    // ── POST /api/users/{id}/profile-picture ──────────────────────────────────

    /**
     * Accepts multipart/form-data with a `file` field.
     *
     * Validation: < 500 KB, ≤ 800 × 800 px, square, PNG or JPEG only.
     * The image is stored in S3 with public-read ACL and the URL is persisted.
     *
     * Accessible by the user themselves (ROLE_USER) or ROLE_ADMIN.
     */
    #[Route('/api/users/{id}/profile-picture', name: 'user_upload_profile_picture', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadUserProfilePicture(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::USER]);

        if ($user === null) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $isSelf = $currentUser instanceof User
            && (string)$currentUser->getId() === (string)$user->getId();
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        if (!$isSelf && !$isAdmin) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');

        if ($file === null) {
            return $this->json(['error' => '`file` is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $mime = $this->profilePictureService->validateImage($file->getPathname());
        } catch (InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $url = $this->profilePictureService->uploadImage(
                $file->getPathname(),
                $mime,
                (string)$user->getId(),
            );
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user->setProfilePictureUrl($url);
        $this->em->flush();

        return $this->json(['profilePictureUrl' => $url]);
    }

    // ── DELETE /api/users/{id}/profile-picture ────────────────────────────────

    /**
     * Resets profilePictureUrl to the default avatar.
     *
     * Accessible by the user themselves (ROLE_USER) or ROLE_ADMIN.
     */
    #[Route('/api/users/{id}/profile-picture', name: 'user_delete_profile_picture', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteUserProfilePicture(string $id): JsonResponse
    {
        $user = $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::USER]);

        if ($user === null) {
            return $this->json(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $isSelf = $currentUser instanceof User
            && (string)$currentUser->getId() === (string)$user->getId();
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        if (!$isSelf && !$isAdmin) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $user->setProfilePictureUrl('/assets/gator_avatar.png');
        $this->em->flush();

        return $this->json(['profilePictureUrl' => $user->getProfilePictureUrl()]);
    }
}
