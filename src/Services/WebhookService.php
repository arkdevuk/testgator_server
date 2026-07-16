<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Security\ResolvedSafeUrl;
use App\Services\Security\UnsafeUrlException;
use App\Services\Security\UrlSafetyChecker;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RESOLVE;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;

use DateTimeImmutable;
use DateTimeInterface;

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
        private readonly UrlSafetyChecker $urlSafetyChecker,
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

            if ($url === null || $url === '') {
                return;
            }

            // Settings validation (SafeUrl constraint on Settings::$value) already
            // rejects unsafe URLs at save time. We re-check here too — the setting
            // could have been written before that validation existed, and DNS can
            // change between save time and dispatch time (rebinding). assertSafe()
            // also returns the exact IP to pin the request to below.
            try {
                $resolved = $this->urlSafetyChecker->assertSafe($url);
            } catch (UnsafeUrlException $e) {
                $this->logger->warning('Webhook URL failed SSRF safety check, dispatch skipped', [
                    'event' => $eventName,
                    'url' => $url,
                    'reason' => $e->getMessage(),
                ]);

                return;
            }

            $body = json_encode([
                'event' => $eventName,
                'date' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
                'entity' => $entityData,
                'project' => $projectData,
            ], JSON_THROW_ON_ERROR);

            $statusCode = $this->curlPost($resolved, $body);

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
     *
     * $target carries the exact IP that UrlSafetyChecker already validated
     * as public/non-reserved. CURLOPT_RESOLVE pins the connection to that
     * IP — curl still sends the correct Host header / SNI for $target->url,
     * but it will not perform its own DNS lookup, which is what closes the
     * DNS-rebinding gap (domain resolves to a safe IP during the check,
     * then to 169.254.169.254 or similar a moment later).
     */
    private function curlPost(ResolvedSafeUrl $target, string $jsonBody): int
    {
        $ch = curl_init($target->url);

        $pinnedIp = str_contains($target->ip, ':') ? '['.$target->ip.']' : $target->ip;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            // Never follow redirects: a 3xx to an internal URL would bypass
            // the safety check entirely. (This was already curl's default
            // since CURLOPT_FOLLOWLOCATION was never set — now explicit.)
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $target->host, $target->port, $pinnedIp)],
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
