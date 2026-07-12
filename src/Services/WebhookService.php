<?php

declare(strict_types=1);

namespace App\Services;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;

use DateTimeImmutable;
use DateTimeInterface;

use const FILTER_VALIDATE_URL;
use const JSON_THROW_ON_ERROR;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Sends outbound webhook calls when the feature is enabled via settings.
 *
 * Required settings (section: webhook):
 *   - enable_webhook : must be the string "true"
 *   - webhook_url    : must be a valid URL
 *
 * Uses PHP's native curl extension — no extra Symfony packages required.
 */
class WebhookService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'default:app.version.default:APP_VERSION')]
        private readonly string $appVersion = '1.0',
    ) {
    }

    /**
     * Dispatch a webhook if enabled and configured.
     *
     * @param string $eventName   e.g. "NewProjectAppEvent"
     * @param array  $entityData  serialised representation of the main entity
     * @param array  $projectData serialised representation of the related project
     */
    public function dispatch(string $eventName, array $entityData, array $projectData): void
    {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $url = $this->settingsService->getSettingValue('webhook_url', 'webhook');

            if ($url === null || !filter_var($url, FILTER_VALIDATE_URL)) {
                return;
            }

            $body = json_encode([
                'event' => $eventName,
                'date' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
                'entity' => $entityData,
                'project' => $projectData,
            ], JSON_THROW_ON_ERROR);

            $statusCode = $this->curlPost($url, $body);

            if ($statusCode >= 400) {
                $this->logger->warning('Webhook returned non-2xx response', [
                    'event' => $eventName,
                    'url' => $url,
                    'status' => $statusCode,
                ]);
            }
        } catch (Throwable $e) {
            // Swallow every failure — a webhook must never crash the main workflow
            $this->logger->warning('Webhook dispatch failed', [
                'event' => $eventName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return $this->settingsService->getSettingValue('enable_webhook', 'webhook', 'false') === 'true';
    }

    /**
     * Fire a POST request via curl and return the HTTP status code.
     * Timeout: 5 s connect, 10 s total.
     */
    private function curlPost(string $url, string $jsonBody): int
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: TestGator-Webhook-v'.$this->appVersion,
                'Content-Length: '.strlen($jsonBody),
            ],
        ]);

        curl_exec($ch);

        return curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
}
