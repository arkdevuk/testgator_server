<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\WorkingHours;
use App\Services\AppConfigService;

class WorkingHoursStateProvider implements ProviderInterface
{
    public function __construct(protected AppConfigService $appConfigService)
    {
    }

    public function provide(Operation $operation,
        array $uriVariables = [],
        array $context = []): array
    {
        if ($operation instanceof GetCollection) {
            $dayOfTheWeeks = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            if (
                ((int) $this->appConfigService->getValue('app.settings.start_of_week', '0'))
                === 1
            ) {
                $dayOfTheWeeks = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            }

            $configuredDays = $this->appConfigService->getValue('app.working_days', []);

            $output = [];

            foreach ($dayOfTheWeeks as $day) {
                if (isset($configuredDays[$day])) {
                    $output[] = new WorkingHours($day, $configuredDays[$day]);
                } else {
                    $output[] = new WorkingHours($day, []);
                }
            }

            return $output;
        }

        return [];
    }
}
