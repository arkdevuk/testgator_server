<?php

namespace App\Services\Entities;

use App\Entity\Tester;
use App\Entity\User;
use App\Services\Communication\MailingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TesterManager
{
    protected EntityManagerInterface $em;
    protected MailingService $mailingService;
    protected TranslatorInterface $translator;

    public function __construct(
        EntityManagerInterface $em,
        MailingService         $mailingService,
        TranslatorInterface    $translator,
    )
    {
        $this->em = $em;
        $this->mailingService = $mailingService;
        $this->translator = $translator;
    }

    public function updateTesterCode(Tester $tester): string
    {
        // generate random code of 6 digits
        $code = random_int(100000, 999999);
        // store the code in the user
        $tester->setOtp((string)$code);
        $tester->setOtpTry(0);
        $tester->setOtpDate(new \DateTime());
        $this->em->persist($tester);
        $this->em->flush();
        // send mail


        try {
            $content = $this->mailingService->render('send-code.email.twig', [
                'auth_code' => $code
            ]);


            $subject = $this->translator->trans('email.auth_code.subject');

            $this->mailingService->sendMail(
                $tester->getEmail(),
                $subject,
                $content
            );
        } catch (\Throwable $e) {
            // ignore
        }


        return $code;
    }


    public function handlePostCreation(Tester $tester): void
    {
        $content = $this->mailingService->render('tester-welcome.email.twig', [
            'signed_url' => $_ENV['APP_URL'] . '/login?mode=tester&email=' . $tester->getEmail(),
        ]);

        $subject = $this->translator->trans('email.welcome_tester.subject');

        $this->mailingService->sendMail(
            $tester->getEmail(),
            $subject,
            $content
        );
    }

    /**
     * @throws \Exception
     */
    public function checkTesterLogin(string $email, string $code): ?Tester
    {
        $tester = $this->getTesterByEmail($email);
        if ($tester === null) {
            throw new \Exception('User not found');
        }

        // Reject immediately if the code has expired — don't burn an attempt
        if ($tester->getOtpDate() === null
            || $tester->getOtpDate()->getTimestamp() < (time() - 300)) {
            throw new \Exception('Code expired');
        }

        // Enforce attempt limit before checking the code so an attacker cannot
        // squeeze in an extra guess while the counter is being incremented
        if ($tester->getOtpTry() >= 3) {
            throw new \Exception('Too many attempts');
        }

        if ($tester->getOtp() === null || $tester->getOtp() === '' || $tester->getOtp() !== $code) {
            $tester->setOtpTry($tester->getOtpTry() + 1);
            $this->em->persist($tester);
            $this->em->flush();
            throw new \Exception('Invalid code');
        }

        $tester->setOtp(null);
        $tester->setOtpTry(0);

        $this->em->persist($tester);
        $this->em->flush();

        return $tester;
    }

    public function getTesterByEmail(string $email): ?Tester
    {
        return $this->em->getRepository(Tester::class)->findOneBy(['email' => $email]);
    }

    public function getTesterByGuid(string $guid): ?Tester
    {
        return $this->em->getRepository(Tester::class)->findOneBy(['id' => $guid]);
    }
}
