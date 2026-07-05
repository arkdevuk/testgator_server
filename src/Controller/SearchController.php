<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Services\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
    )
    {
    }

    #[Route('/api/search/query', name: 'search_query', methods: ['GET'])]
    public function query(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $query = trim($request->query->getString('query', ''));
        if ($query === '') {
            return $this->json(['error' => 'Missing required parameter: query'], Response::HTTP_BAD_REQUEST);
        }

        $scopes = $this->resolveScopes($request, $user);

        if ($scopes === null) {
            return $this->json(
                ['error' => 'Invalid scope value(s). Allowed: ' . implode(', ', SearchService::ALL_SCOPES)],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $results = $this->searchService->search($query, $scopes, $user);

        return $this->json($results);
    }
    /**
     * Resolves scope(s) from the request.
     * Supports:
     *   ?scope=projects,questions          (comma-separated)
     *   ?scope[]=projects&scope[]=questions (array style)
     *   ?scope=                             (omitted → all accessible)
     *
     * Returns null if any value is invalid.
     *
     * @return string[]|null
     */
    private function resolveScopes(Request $request, User $user): ?array
    {
        // Array style: scope[]=x&scope[]=y
        $array = $request->query->all('scope');

        if ($array === []) {
            // Comma-separated style: scope=x,y  or  scope=x
            $raw = trim($request->query->getString('scope', ''));
            if ($raw === '') {
                return $user->isTester() ? SearchService::TESTER_SCOPES : SearchService::ALL_SCOPES;
            }
            $array = array_map(trim(...), explode(',', $raw));
        }

        $array = array_values(array_unique(array_filter($array)));

        $invalid = array_diff($array, SearchService::ALL_SCOPES);
        if ($invalid !== []) {
            return null;
        }

        return $array;
    }
}
