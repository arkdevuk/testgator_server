<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SelfUserController extends AbstractController
{
    #[Route('/apx/me', name: 'get_me_data')]
    public function get_me_data(): Response
    {
        $u = $this->getUser();
        if (!$u instanceof User) {
            return $this->json([
                'error' => 'User not found'
            ], 404);
        }

        return $this->json([
            'id' => $u->getId()?->toString(),
            'email' => $u->getEmail(),
            'roles' => $u->getRoles(),
        ]);
    }
}
