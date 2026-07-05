<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\User;
use App\Enum\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserProfileService
{
    public function __construct(
        private readonly UserRepository         $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly ProfilePictureService  $profilePictureService,
    )
    {
    }

    public function findTester(string $id): ?User
    {
        return $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::TESTER]);
    }

    public function findUser(string $id): ?User
    {
        return $this->userRepository->findOneBy(['id' => $id, 'type' => UserType::USER]);
    }

    /**
     * Set the tester's profile picture URL and persist.
     */
    public function setTesterProfilePictureUrl(User $tester, string $url): void
    {
        $tester->setProfilePictureUrl($url);
        $this->em->flush();
    }

    /**
     * Set the tester's nickname and persist.
     */
    public function setTesterNickname(User $tester, string $nickname): void
    {
        $tester->setNickname(trim($nickname));
        $this->em->flush();
    }

    /**
     * Validate, upload, and persist the user's profile picture.
     *
     * @return string The new profile picture URL
     *
     * @throws InvalidArgumentException on image validation failure
     * @throws RuntimeException         on upload failure
     */
    public function uploadUserProfilePicture(User $user, UploadedFile $file): string
    {
        $mime = $this->profilePictureService->validateImage($file->getPathname());

        $url = $this->profilePictureService->uploadImage(
            $file->getPathname(),
            $mime,
            (string)$user->getId(),
        );

        $user->setProfilePictureUrl($url);
        $this->em->flush();

        return $url;
    }

    /**
     * Reset the user's profile picture to the default avatar and persist.
     */
    public function deleteUserProfilePicture(User $user): void
    {
        $user->setProfilePictureUrl('/assets/gator_avatar.png');
        $this->em->flush();
    }
}
