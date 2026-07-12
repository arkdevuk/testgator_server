<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Answer;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\TestPlan;
use App\Entity\User;
use App\Event\AnswerUpdatedAppEvent;
use App\Event\DevCreatedAppEvent;
use App\Event\NewAnswerAppEvent;
use App\Event\NewProjectAppEvent;
use App\Event\NewQuestionAppEvent;
use App\Event\NewTestingPlanAppEvent;
use App\Event\ProjectUpdatedAppEvent;
use App\Event\QuestionUpdatedAppEvent;
use App\Event\TesterAssignedAppEvent;
use App\Event\TesterCreatedAppEvent;
use App\Event\TestingPlanUpdatedAppEvent;
use App\Event\TestPlanClosedAppEvent;
use App\Event\TestPlanPublishedAppEvent;
use App\Event\UserPasswordChangedAppEvent;
use App\Services\WebhookService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: NewProjectAppEvent::class)]
#[AsEventListener(event: ProjectUpdatedAppEvent::class)]
#[AsEventListener(event: NewTestingPlanAppEvent::class)]
#[AsEventListener(event: TestingPlanUpdatedAppEvent::class)]
#[AsEventListener(event: TestPlanPublishedAppEvent::class)]
#[AsEventListener(event: TestPlanClosedAppEvent::class)]
#[AsEventListener(event: TesterAssignedAppEvent::class)]
#[AsEventListener(event: NewQuestionAppEvent::class)]
#[AsEventListener(event: QuestionUpdatedAppEvent::class)]
#[AsEventListener(event: NewAnswerAppEvent::class)]
#[AsEventListener(event: AnswerUpdatedAppEvent::class)]
#[AsEventListener(event: UserPasswordChangedAppEvent::class)]
#[AsEventListener(event: DevCreatedAppEvent::class)]
#[AsEventListener(event: TesterCreatedAppEvent::class)]
final readonly class WebhookEventListener
{
    public function __construct(
        private WebhookService $webhookService,
    ) {
    }

    // ── Project ───────────────────────────────────────────────────────────────

    public function __invoke(
        NewProjectAppEvent|ProjectUpdatedAppEvent|NewTestingPlanAppEvent|TestingPlanUpdatedAppEvent|TestPlanPublishedAppEvent|TestPlanClosedAppEvent|TesterAssignedAppEvent|NewQuestionAppEvent|QuestionUpdatedAppEvent|NewAnswerAppEvent|AnswerUpdatedAppEvent|UserPasswordChangedAppEvent|DevCreatedAppEvent|TesterCreatedAppEvent $event,
    ): void {
        [$entityData, $projectData] = match (true) {
            $event instanceof NewProjectAppEvent,
            $event instanceof ProjectUpdatedAppEvent => $this->fromProject($event->project),

            $event instanceof NewTestingPlanAppEvent,
            $event instanceof TestingPlanUpdatedAppEvent,
            $event instanceof TestPlanPublishedAppEvent,
            $event instanceof TestPlanClosedAppEvent => $this->fromTestPlan($event->testPlan),

            $event instanceof TesterAssignedAppEvent => $this->fromTesterAssigned($event),

            $event instanceof NewQuestionAppEvent,
            $event instanceof QuestionUpdatedAppEvent => $this->fromQuestion($event->question),

            $event instanceof NewAnswerAppEvent,
            $event instanceof AnswerUpdatedAppEvent => $this->fromAnswer($event->answer),

            $event instanceof UserPasswordChangedAppEvent => $this->fromUserPasswordChanged($event->user),

            $event instanceof DevCreatedAppEvent => $this->fromUserPasswordChanged($event->user),
            $event instanceof TesterCreatedAppEvent => $this->fromTesterCreated($event->tester),
        };

        $this->webhookService->dispatch($event::class, $entityData, $projectData);
    }

    // ── Payload builders ──────────────────────────────────────────────────────

    private function fromProject(Project $project): array
    {
        $entityData = $this->serializeProject($project);
        $projectData = $entityData;

        return [$entityData, $projectData];
    }

    private function serializeProject(?Project $project): array
    {
        if (!$project instanceof Project) {
            return [];
        }

        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
        ];
    }

    private function fromTestPlan(TestPlan $testPlan): array
    {
        $entityData = $this->serializeTestPlan($testPlan);
        $projectData = $this->serializeProject(
            $testPlan->getRelease()?->getProject()
        );

        return [$entityData, $projectData];
    }

    private function serializeTestPlan(TestPlan $testPlan): array
    {
        return [
            'id' => $testPlan->getId(),
            'name' => $testPlan->getName(),
            'description' => $testPlan->getDescription(),
            'state' => $testPlan->getState(),
        ];
    }

    private function fromTesterAssigned(TesterAssignedAppEvent $event): array
    {
        $entityData = [
            'testPlan' => $this->serializeTestPlan($event->testPlan),
            'tester' => $this->serializeTester($event->tester),
        ];
        $projectData = $this->serializeProject(
            $event->testPlan->getRelease()?->getProject()
        );

        return [$entityData, $projectData];
    }

    // ── Serializers ───────────────────────────────────────────────────────────

    private function serializeTester(User $tester): array
    {
        return [
            'id' => (string) $tester->getId(),
            'email' => $tester->getEmail(),
        ];
    }

    private function fromQuestion(Question $question): array
    {
        $entityData = $this->serializeQuestion($question);
        $projectData = $this->serializeProject(
            $question->getPlan()?->getRelease()?->getProject()
        );

        return [$entityData, $projectData];
    }

    private function serializeQuestion(Question $question): array
    {
        return [
            'id' => $question->getId(),
            'name' => $question->getName(),
            'content' => $question->getContent(),
            'plan' => $question->getPlan() instanceof TestPlan
                ? ['id' => $question->getPlan()->getId(), 'name' => $question->getPlan()->getName()]
                : null,
        ];
    }

    private function fromAnswer(Answer $answer): array
    {
        $entityData = $this->serializeAnswer($answer);
        $projectData = $this->serializeProject(
            $answer->getQuestion()?->getPlan()?->getRelease()?->getProject()
        );

        return [$entityData, $projectData];
    }

    private function serializeAnswer(Answer $answer): array
    {
        return [
            'id' => $answer->getId(),
            'state' => $answer->getState()->value,
            'comment' => $answer->getComment(),
            'tester' => $answer->getTester() instanceof User
                ? $this->serializeTester($answer->getTester())
                : null,
            'question' => $answer->getQuestion() instanceof Question
                ? ['id' => $answer->getQuestion()->getId(), 'name' => $answer->getQuestion()->getName()]
                : null,
        ];
    }

    private function fromUserPasswordChanged(User $user): array
    {
        $entityData = [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'type' => $user->getType()->value,
        ];

        // Team users / dev accounts do not belong to a single project.
        return [$entityData, []];
    }

    private function fromTesterCreated(User $tester): array
    {
        $entityData = [
            'id' => (string) $tester->getId(),
            'email' => $tester->getEmail(),
            'type' => $tester->getType()->value,
        ];

        // A freshly created tester is not yet enrolled in any project.
        return [$entityData, []];
    }
}
