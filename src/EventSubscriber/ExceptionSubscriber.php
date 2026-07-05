<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use function Sentry\captureException;
use function Sentry\init;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Or Sentry SDK directly if you prefer

class ExceptionSubscriber implements EventSubscriberInterface
{
    // Or \Sentry\State\HubInterface if using Sentry SDK
    private bool $inited = false;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $dsn = $_ENV['SENTRY_DSN'] ?? '';

        // No DSN configured (tests, local dev) → do nothing. Sentry's init()
        // registers global error/exception handlers that it never restores,
        // which PHPUnit reports as a risky test ("did not remove its own
        // error/exception handlers"). Skipping also avoids initializing the
        // SDK with an empty DSN in production.
        if ($dsn === '') {
            return;
        }

        if (!$this->inited) {
            init(['dsn' => $dsn]);
            $this->inited = true;
        }

        // ⚠ Do NOT call $event->stopPropagation() — Symfony's default error
        // handling must still run after we report the exception.
        captureException($event->getThrowable());
    }
}
