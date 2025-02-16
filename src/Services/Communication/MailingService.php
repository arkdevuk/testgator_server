<?php

namespace App\Services\Communication;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MailingService
{
    protected MailerInterface $mailer;
    protected Environment $twig;

    public function __construct(
        MailerInterface $mailer,
        Environment     $twig,
    )
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    public function render(string $template, array $context): string
    {
        try {
            return $this->twig->render($template, $context);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function sendMail(
        string $to,
        string $subject,
        string $body
    ): void
    {
        $email = (new Email())
            ->from($_ENV['MAILER_SENDER_ADDRESS'])
            ->to($to)
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }

}
