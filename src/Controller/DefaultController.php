<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): JsonResponse
    {
        return $this->json([
            'healthcheck' => 'ok',
            'version' => '1.0.0',
            'error' => false,
        ]);
    }
}
