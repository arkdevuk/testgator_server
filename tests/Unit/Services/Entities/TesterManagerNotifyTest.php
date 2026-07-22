<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Entities;

use App\Entity\TestPlan;
use App\Entity\User;
use App\Services\Communication\MailingService;
use App\Services\Entities\TesterManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for the test-plan lifecycle notification emails
 * (published / closed / invited) added to TesterManager.
 */
class TesterManagerNotifyTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_URL'] = 'https://app.testgator.test';
    }

    public function testNotifyTestPlanPublishedEmailsEveryEnrolledTester(): void
    {
        $testerA = $this->makeTester('a@example.com');
        $testerB = $this->makeTester('b@example.com');
        $plan = $this->makePlan('Regression 1.2', [$testerA, $testerB]);

        $mailingService = $this->createMock(MailingService::class);
        $mailingService->expects(self::exactly(2))
            ->method('render')
            ->with('test-plan-published.email.twig', self::callback(
                static fn (array $ctx) => $ctx['test_plan_name'] === 'Regression 1.2' && str_contains($ctx['signed_url'], 'mode=tester')
            ))
            ->willReturn('<html></html>');
        $mailingService->expects(self::exactly(2))->method('sendMail');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('subject');

        $manager = new TesterManager($this->createStub(EntityManagerInterface::class), $mailingService, $translator);
        $manager->notifyTestPlanPublished($plan);
    }

    public function testNotifyTestPlanClosedEmailsEveryEnrolledTester(): void
    {
        $testerA = $this->makeTester('a@example.com');
        $plan = $this->makePlan('Regression 1.2', [$testerA]);

        $mailingService = $this->createMock(MailingService::class);
        $mailingService->expects(self::once())->method('render')->with('test-plan-closed.email.twig', self::anything())->willReturn('<html></html>');
        $mailingService->expects(self::once())->method('sendMail')->with('a@example.com', self::anything(), self::anything());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('subject');

        $manager = new TesterManager($this->createStub(EntityManagerInterface::class), $mailingService, $translator);
        $manager->notifyTestPlanClosed($plan);
    }

    public function testNotifyTesterAssignedEmailsOnlyThatTester(): void
    {
        $testerA = $this->makeTester('a@example.com');
        $testerB = $this->makeTester('b@example.com');
        $plan = $this->makePlan('Regression 1.2', [$testerA, $testerB]);

        $mailingService = $this->createMock(MailingService::class);
        $mailingService->expects(self::once())->method('render')->with('test-plan-invitation.email.twig', self::anything())->willReturn('<html></html>');
        $mailingService->expects(self::once())->method('sendMail')->with('b@example.com', self::anything(), self::anything());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('subject');

        $manager = new TesterManager($this->createStub(EntityManagerInterface::class), $mailingService, $translator);
        $manager->notifyTesterAssigned($plan, $testerB);
    }

    public function testMailFailureIsSwallowedAndDoesNotPropagate(): void
    {
        $tester = $this->makeTester('a@example.com');
        $plan = $this->makePlan('Regression 1.2', [$tester]);

        $mailingService = $this->createStub(MailingService::class);
        $mailingService->method('render')->willThrowException(new RuntimeException('twig broke'));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('subject');

        $manager = new TesterManager($this->createStub(EntityManagerInterface::class), $mailingService, $translator);

        // Should not throw.
        $manager->notifyTestPlanPublished($plan);
        self::assertTrue(true);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function makePlan(string $name, array $testers): TestPlan
    {
        $plan = new TestPlan();
        $plan->setName($name);
        foreach ($testers as $tester) {
            $plan->addTestersEnrolled($tester);
        }

        return $plan;
    }

    private function makeTester(string $email): User
    {
        $tester = $this->createStub(User::class);
        $tester->method('getEmail')->willReturn($email);

        return $tester;
    }
}
