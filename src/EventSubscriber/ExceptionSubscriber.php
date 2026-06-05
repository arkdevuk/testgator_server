<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

// Or Sentry SDK directly if you prefer

class ExceptionSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger; // Or \Sentry\State\HubInterface if using Sentry SDK
    private bool $inited = false;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->inited) {
            \Sentry\init([
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
        \Sentry\captureException($throwable);

        // ⚠ Do NOT call $event->stopPropagation() unless you want to stop Symfony's default error handling
        // We simply let it continue after logging
    }
}
