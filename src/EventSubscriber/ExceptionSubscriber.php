<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use function Sentry\init;
use function Sentry\captureException;
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
        if (!$this->inited) {
            init([
                'dsn' => $_ENV['SENTRY_DSN'] ?? '',
            ]);
            $this->inited = true;
        }

        $throwable = $event->getThrowable();
        // Log to Sentry
        /*
        $this->logger->error('Uncaught Exception', [
            'exception' => $throwable,
        ]);//*/

        // Or directly if you're using Sentry SDK:
        captureException($throwable);

        // ⚠ Do NOT call $event->stopPropagation() unless you want to stop Symfony's default error handling
        // We simply let it continue after logging
    }
}
