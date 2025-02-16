<?php

namespace App\Controller;

use App\Services\Communication\MailingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublishController extends AbstractController
{
    #[Route('/public/publish', name: 'test_url')]
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
            $content,
        );


        return $this->json([
            'test' => true,
        ]);
    }
}
