<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Entity\TestPlan;
use App\Entity\User;
use App\Enum\UserType;
use App\Services\Communication\MailingService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Handles tester-specific workflows (OTP login, welcome email) for
 * User entities of type TESTER.
 */
class TesterManager
{
    public function __construct(protected EntityManagerInterface $em, protected MailingService $mailingService, protected TranslatorInterface $translator)
    {
    }

    public function updateTesterCode(User $tester): string
    {
        // generate random code of 6 digits
        $code = random_int(100000, 999999);
        // store the code in the user
        $tester->setOtp((string) $code);
        $tester->setOtpTry(0);
        $tester->setOtpDate(new DateTime());
        $this->em->persist($tester);
        $this->em->flush();
        // send mail

        try {
            $content = $this->mailingService->render('send-code.email.twig', [
                'auth_code' => $code,
            ]);

            $subject = $this->translator->trans('email.auth_code.subject');

            $this->mailingService->sendMail(
                $tester->getEmail(),
                $subject,
                $content
            );
        } catch (Throwable) {
            // ignore
        }

        return (string) $code;
    }

    public function handlePostCreation(User $tester): void
    {
        try {
            $content = $this->mailingService->render('tester-welcome.email.twig', [
                'signed_url' => $_ENV['APP_URL'].'/login?mode=tester&email='.$tester->getEmail(),
            ]);

            $subject = $this->translator->trans('email.welcome_tester.subject');

            $this->mailingService->sendMail(
                $tester->getEmail(),
                $subject,
                $content
            );
        } catch (Throwable) {
            // a failing mail must never abort tester creation (or fixture loading)
        }
    }

    /**
     * @throws Exception
     */
    public function checkTesterLogin(string $email, string $code): ?User
    {
        $tester = $this->getTesterByEmail($email);
        if (!$tester instanceof User) {
            throw new Exception('User not found');
        }

        // Reject immediately if the code has expired — don't burn an attempt
        if (!$tester->getOtpDate() instanceof DateTimeInterface
            || $tester->getOtpDate()->getTimestamp() < (time() - 300)) {
            throw new Exception('Code expired');
        }

        // Enforce attempt limit before checking the code so an attacker cannot
        // squeeze in an extra guess while the counter is being incremented
        if ($tester->getOtpTry() >= 3) {
            throw new Exception('Too many attempts');
        }

        if ($tester->getOtp() === null || $tester->getOtp() === '' || $tester->getOtp() !== $code) {
            $tester->setOtpTry($tester->getOtpTry() + 1);
            $this->em->persist($tester);
            $this->em->flush();
            throw new Exception('Invalid code');
        }

        $tester->setOtp(null);
        $tester->setOtpTry(0);

        $this->em->persist($tester);
        $this->em->flush();

        return $tester;
    }

    /**
     * Notifies every tester enrolled in the plan that it just went live.
     * Fired when a test plan transitions from draft (or archived) to published.
     */
    public function notifyTestPlanPublished(TestPlan $testPlan): void
    {
        foreach ($testPlan->getTestersEnrolled() as $tester) {
            $this->sendTestPlanLifecycleMail(
                $tester,
                $testPlan,
                'test-plan-published.email.twig',
                'email.test_plan_published.subject',
            );
        }
    }

    /**
     * Notifies every tester enrolled in the plan that it was closed (moved
     * out of the published state, to draft or archived).
     */
    public function notifyTestPlanClosed(TestPlan $testPlan): void
    {
        foreach ($testPlan->getTestersEnrolled() as $tester) {
            $this->sendTestPlanLifecycleMail(
                $tester,
                $testPlan,
                'test-plan-closed.email.twig',
                'email.test_plan_closed.subject',
            );
        }
    }

    /**
     * Notifies a single tester that they've been enrolled into a plan that
     * is already published (as opposed to being enrolled before it publishes,
     * which is covered by notifyTestPlanPublished() instead).
     */
    public function notifyTesterAssigned(TestPlan $testPlan, User $tester): void
    {
        $this->sendTestPlanLifecycleMail(
            $tester,
            $testPlan,
            'test-plan-invitation.email.twig',
            'email.test_plan_invitation.subject',
        );
    }

    private function sendTestPlanLifecycleMail(User $tester, TestPlan $testPlan, string $template, string $subjectKey): void
    {
        try {
            $params = ['%test_plan_name%' => $testPlan->getName()];

            $content = $this->mailingService->render($template, [
                'test_plan_name' => $testPlan->getName(),
                'signed_url' => $_ENV['APP_URL'].'/login?mode=tester&email='.$tester->getEmail(),
            ]);

            $subject = $this->translator->trans($subjectKey, $params);

            $this->mailingService->sendMail(
                $tester->getEmail(),
                $subject,
                $content
            );
        } catch (Throwable) {
            // a failing mail must never abort a test plan state change
        }
    }

    public function getTesterByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email, 'type' => UserType::TESTER]);
    }

    public function getTesterByGuid(string $guid): ?User
    {
        return $this->em->getRepository(User::class)
            ->findOneBy(['id' => $guid, 'type' => UserType::TESTER]);
    }
}
