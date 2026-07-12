<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\WorkingHoursStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['workingHours:read']],
    denormalizationContext: ['groups' => ['workingHours:write']],
    provider: WorkingHoursStateProvider::class
)]
#[GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')")]
class WorkingHours
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['workingHours:read'])]
        private ?string $id,
        #[Groups(['workingHours:read'])]
        private array $details
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
