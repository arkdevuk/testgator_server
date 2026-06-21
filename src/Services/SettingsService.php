<?php

namespace App\Services;

use App\Entity\Settings;
use App\Repository\SettingsRepository;

/**
 * Centralised settings accessor.
 *
 * On instantiation all settings flagged with autoload=true are pulled from the
 * database and kept in memory. getSettingValue() checks the in-memory cache
 * first, falls back to a targeted DB query, and finally returns $default.
 */
class SettingsService
{
    /** @var array<string, Settings>  keyed by "{section}.{name}" */
    private array $cache = [];

    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    )
    {
        $this->preload();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    private function preload(): void
    {
        $settings = $this->settingsRepository->findBy(['autoload' => true]);

        foreach ($settings as $setting) {
            $this->cache[$setting->getId()] = $setting;
        }
    }

    /**
     * Retrieve a setting value.
     *
     * Resolution order:
     *  1. In-memory cache (autoloaded settings)
     *  2. Direct DB query
     *  3. $default
     */
    public function getSettingValue(string $name, string $section, mixed $default = null): mixed
    {
        $key = $section . '.' . $name;

        // 1. Cache hit
        if (isset($this->cache[$key])) {
            return $this->cache[$key]->getValue();
        }

        // 2. DB lookup
        $setting = $this->settingsRepository->find($key);
        if ($setting instanceof Settings) {
            return $setting->getValue();
        }

        // 3. Default
        return $default;
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    /**
     * Return a fully resolved Settings object (or null if not found).
     * Useful when the caller needs metadata beyond just the value.
     */
    public function getSetting(string $name, string $section): ?Settings
    {
        $key = $section . '.' . $name;

        return $this->cache[$key] ?? $this->settingsRepository->find($key);
    }
}
