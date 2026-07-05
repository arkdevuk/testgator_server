<?php

namespace App\Services;

use Exception;
use Symfony\Component\HttpKernel\KernelInterface;

class AppConfigService
{
    protected string $configPath;
    protected bool $active;
    protected array $config;

    // get kernel.project_dir
    public function __construct(
        KernelInterface $kernel,
    )
    {
        $projectDir = $kernel->getProjectDir();
        $this->configPath = $projectDir . '/app_config/config.yaml';
        $this->active = file_exists($this->configPath);
        // config file is yaml
        try {
            $configContent = file_get_contents($this->configPath);
            $this->config = yaml_parse($configContent);
        } catch (Exception) {
            $this->config = [];
            $this->active = false;
        }
    }

    /**
     * Retrieves a value from the configuration array using dot notation.
     *
     * Example:
     * - 'app.working_days' returns the array of working days.
     * - 'app.working_days.monday' returns Monday's working hours.
     *
     * @param string $path Dot-notated string path to the desired config value.
     * @param mixed $defaultValue Value to return if the path is not found in the config.
     *
     * @return mixed The value found at the specified path, or the default value if not found.
     */
    public function getValue(string $path, mixed $defaultValue = null): mixed
    {
        $keys = explode('.', $path);
        $value = $this->config;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return $defaultValue;
            }
        }

        return $value;
    }
}
