<?php

namespace App\Controller;

use App\Services\Authentification\GuestAuthService;
use App\Services\Entities\TestPlanManager;
use App\Services\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthUploadController extends AbstractController
{
    #[Route('/apx/upload_request_auth', name: 'upload_request_auth')]
    public function upload_request_auth(
        FileService $fileService,
    ): Response
    {
        // auth already handled, generate a JWT token
        return $this->json([
            'jwt' => $fileService->getUploadRequest(),
        ]);
    }

    #[Route('/public/apx/upload_request_guest', name: 'upload_request_guest', methods: ['POST'])]
    public function upload_request_guest(
        FileService      $fileService,
        GuestAuthService $guestAuthService,
        Request          $request,
        TestPlanManager  $testPlanManager,
    ): Response
    {
        // parameters required in json body
        // challenge : a random string
        // hash : a hash of the challenge using the private key
        // tp : uuid of the TestPlan

        // check if the request is valid and contains the required parameters
        // if not, return 400
        $validParams = ['challenge', 'hash', 'tp'];
        try {
            $json = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->json([
                'error' => 'Invalid JSON',
            ], 400);
        }
        foreach ($validParams as $param) {
            if (!array_key_exists($param, $json)) {
                return $this->json([
                    'error' => 'Missing parameter: ' . $param,
                ], 400);
            }
        }

        // Fetch the TestPlan to get the key
        $tp = $testPlanManager->getTestPlanById((int)$json['tp']);
        if ($tp === null) {
            return $this->json([
                'error' => 'Invalid TestPlan',
            ], 404);
        }

        // validate the hash
        if (!$guestAuthService->validateHash(
            $json['challenge'],
            $json['hash'],
            $tp->getKey(),
        )) {
            return $this->json([
                'error' => 'Invalid hash',
            ], 401);
        }

        // if everything is valid, generate a JWT token
        return $this->json([
            'jwt' => $fileService->getUploadRequest(),
        ]);
    }
}
