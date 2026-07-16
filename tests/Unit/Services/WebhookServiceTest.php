<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\Security\UrlSafetyChecker;
use App\Services\SettingsService;
use App\Services\WebhookService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for WebhookService's SSRF guard at dispatch time.
 *
 * We only exercise the "blocked" paths here — a successful dispatch would
 * reach real curl_exec() against the network, which these tests deliberately
 * avoid. The safety logic itself (which IPs/schemes are rejected) is covered
 * by UrlSafetyCheckerTest.
 */
class WebhookServiceTest extends TestCase
{
    public function testDisabledWebhookNeverConsultsUrlSafety(): void
    {
        $settings = $this->createStub(SettingsService::class);
        $settings->method('getSettingValue')
            ->willReturnCallback(static fn (string $name, string $section, mixed $default = null) => match ($name) {
                'enable_webhook' => 'false',
                default => $default,
            });

        $urlSafetyChecker = $this->createMock(UrlSafetyChecker::class);
        $urlSafetyChecker->expects(self::never())->method('assertSafe');

        $logger = $this->createStub(LoggerInterface::class);

        $service = new WebhookService($settings, $logger, $urlSafetyChecker);
        $service->dispatch('SomeEvent', [], []);
    }

    public function testEmptyWebhookUrlIsSkippedWithoutCallingUrlSafety(): void
    {
        $settings = $this->createStub(SettingsService::class);
        $settings->method('getSettingValue')
            ->willReturnCallback(static fn (string $name, string $section, mixed $default = null) => match ($name) {
                'enable_webhook' => 'true',
                'webhook_url' => '',
                default => $default,
            });

        $urlSafetyChecker = $this->createMock(UrlSafetyChecker::class);
        $urlSafetyChecker->expects(self::never())->method('assertSafe');

        $logger = $this->createStub(LoggerInterface::class);

        $service = new WebhookService($settings, $logger, $urlSafetyChecker);
        $service->dispatch('SomeEvent', [], []);
    }

    public function testUnsafeWebhookUrlIsLoggedAndDispatchIsSkipped(): void
    {
        $settings = $this->createStub(SettingsService::class);
        $settings->method('getSettingValue')
            ->willReturnCallback(static fn (string $name, string $section, mixed $default = null) => match ($name) {
                'enable_webhook' => 'true',
                'webhook_url' => 'http://169.254.169.254/latest/meta-data/',
                default => $default,
            });

        // Real checker (no mocked resolver needed — the URL is a literal IP).
        $urlSafetyChecker = new UrlSafetyChecker();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Webhook URL failed SSRF safety check, dispatch skipped',
                self::callback(static fn (array $context): bool => 'SomeEvent' === $context['event']
                    && 'http://169.254.169.254/latest/meta-data/' === $context['url']),
            );

        $service = new WebhookService($settings, $logger, $urlSafetyChecker);

        // Must not throw — a bad webhook URL must never break the caller's workflow.
        $service->dispatch('SomeEvent', [], []);
    }
}
