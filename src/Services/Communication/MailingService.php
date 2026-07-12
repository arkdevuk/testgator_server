<?php

declare(strict_types=1);

namespace App\Services\Communication;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

class MailingService
{
    public function __construct(protected MailerInterface $mailer, protected Environment $twig)
    {
    }

    public function render(string $template, array $context): string
    {
        try {
            return $this->twig->render($template, $context);
        } catch (Throwable) {
            return '';
        }
    }

    public function sendMail(
        string $to,
        string $subject,
        string $body
    ): void {
        $email = new Email()
            ->from($_ENV['MAILER_SENDER_ADDRESS'])
            ->to($to)
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }
}
