<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\TestPlan;
use App\Enum\AnswerState;
use App\Repository\TestPlanRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// demo endpoints are reserved for team members — testers are denied
#[IsGranted('ROLE_USER')]
final class DemoController extends AbstractController
{
    // ── Canned comments per state ─────────────────────────────────────────────

    private const array COMMENTS = [
        AnswerState::PASS->value => [
            'Everything worked as expected. No issues found during testing.',
            'Tested successfully on Chrome and Firefox. All steps completed without errors.',
            'Feature behaves exactly as described in the requirements. Great job!',
            'Smooth experience from start to finish. Nothing to report.',
        ],
        AnswerState::PASS_WITH_BUGS->value => [
            'The main flow works but the button label is misaligned on mobile screens.',
            'Passed overall, though I noticed a minor visual glitch on page load — the spinner stays 1s too long.',
            'Works correctly but the success message disappears too quickly (under 1 second).',
            'Functionally correct. Minor issue: the date format shows MM/DD instead of DD/MM for French locale.',
        ],
        AnswerState::FAILED->value => [
            'Clicking "Submit" returns a 500 error. Reproduced consistently on Chrome 124.',
            'The form does not validate the email field — any string is accepted without an @ symbol.',
            'After completing step 3, the page redirects to a blank screen instead of the confirmation page.',
            'Upload fails silently when the file is over 2 MB. No error message shown to the user.',
        ],
        AnswerState::BLOCKED->value => [
            'Cannot proceed — the login page returns a 403 even with valid credentials.',
            'Blocked at step 2: the dropdown menu never loads. Spinning indefinitely.',
            'Unable to test — the feature flag appears to be off in the current environment.',
            'Blocked: required test data (user account with "admin" role) is not available in staging.',
        ],
        AnswerState::PENDING->value => [
            'Will review once the environment is back online.',
            'Scheduled for testing this afternoon.',
            'Waiting for the backend deploy to finish before I can test this.',
            'On hold — need clarification on step 4 before proceeding.',
        ],
    ];

    public function __construct(private readonly TestPlanRepository $testPlanRepository, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/demo/add_demo_answer', name: 'app_demo_add_demo_answer', methods: ['POST'])]
    public function __invoke(
        Request $request,
    ): JsonResponse {
        $body = json_decode($request->getContent(), true) ?? [];

        $testingPlanIri = $body['testingPlanIri'] ?? null;
        $number = (int) ($body['number'] ?? 10);

        // ── Validate inputs ───────────────────────────────────────────────────

        if (!$testingPlanIri) {
            return $this->json(['error' => 'testingPlanIri is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($number < 0 || $number > 100) {
            return $this->json(['error' => 'number must be between 0 and 100'], Response::HTTP_BAD_REQUEST);
        }

        // ── Resolve TestPlan from IRI (/api/test_plans/12) ───────────────────

        if (!preg_match('#/(\d+)$#', $testingPlanIri, $m)) {
            return $this->json(['error' => 'Invalid testingPlanIri format. Expected e.g. /api/test_plans/12'], Response::HTTP_BAD_REQUEST);
        }

        $testPlan = $this->testPlanRepository->find((int) $m[1]);

        if (!$testPlan instanceof TestPlan) {
            return $this->json(['error' => 'TestPlan not found'], Response::HTTP_NOT_FOUND);
        }

        $questions = $testPlan->getQuestions()->toArray();

        if (empty($questions)) {
            return $this->json(['error' => 'This testing plan has no questions'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── Generate answers ──────────────────────────────────────────────────

        $testers = $testPlan->getTestersEnrolled()->toArray();

        if (empty($testers)) {
            return $this->json(['error' => 'This testing plan has no enrolled testers'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = new DateTime();
        $states = AnswerState::cases();
        $created = 0;

        for ($i = 0; $i < $number; ++$i) {
            $state = $states[array_rand($states)];
            $question = $questions[array_rand($questions)];
            $comments = self::COMMENTS[$state->value];

            // Random date: test plan creation → now (Question has no timestamps)
            $from = $testPlan->getCreated()
                ?? (clone $now)->modify('-30 days');
            $randomTs = random_int($from->getTimestamp(), $now->getTimestamp());
            $randomDate = new DateTime()->setTimestamp($randomTs);

            $answer = new Answer();
            $answer->setCreated($randomDate);
            $answer->setUpdated($randomDate);
            $answer->setQuestion($question);
            $answer->setState($state);
            $answer->setComment($comments[array_rand($comments)]);
            $answer->setTester($testers[array_rand($testers)]);
            $answer->setSystemInfos([
                'browser' => ['Chrome', 'Firefox', 'Safari', 'Edge'][array_rand(['Chrome', 'Firefox', 'Safari', 'Edge'])],
                'os' => ['macOS', 'Windows 11', 'Ubuntu 22', 'iOS'][array_rand(['macOS', 'Windows 11', 'Ubuntu 22', 'iOS'])],
                'demo' => true,
            ]);

            $this->em->persist($answer);
            ++$created;
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'created' => $created,
            'testPlan' => [
                'id' => $testPlan->getId(),
                'name' => $testPlan->getName(),
            ],
        ], Response::HTTP_CREATED);
    }
}
