<?php

namespace App\Controller;

use App\Services\Communication\MailingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->json([
            'healthcheck' => 'ok',
            'version' => '1.0.0',
            'error' => false,
        ]);
    }

    #[Route('/public/test', name: 'test_url')]
    public function publicTest(
        MailingService $mailingService
    ): Response
    {

        $content = $mailingService->render(
            'test.email.twig',
            ['name' => 'Alex']
        );

        $mailingService->sendMail(
            'ark@ark-dev.uk',
            'Test',
            'This is a test email'
        );


        return $this->json([
            'test' => true,
        ]);
    }
}
