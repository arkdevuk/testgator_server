<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\Release;
use App\State\ReleaseStatsStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['releaseStats:read']],
    denormalizationContext: ['groups' => ['releaseStats:write']],
    provider: ReleaseStatsStateProvider::class
)]
#[Get(security: "is_granted('ROLE_USER')")]
class ReleaseStats
{
    #[Groups(['releaseStats:read'])]
    #[ApiProperty()]
    private Release $release;

    #[Groups(['releaseStats:read'])]
    private int $totalQuestions = 0;
    #[Groups(['releaseStats:read'])]
    private int $totalWait = 0;
    #[Groups(['releaseStats:read'])]
    private int $totalResponded = 0;
    #[Groups(['releaseStats:read'])]
    private int $totalPlans = 0;

    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['releaseStats:read'])]
        private ?int $id
    )
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelease(): Release
    {
        return $this->release;
    }


    public function setRelease(Release $release): ReleaseStats
    {
        $this->release = $release;

        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): ReleaseStats
    {
        $this->totalQuestions = $totalQuestions;
        return $this;
    }

    public function getTotalWait(): int
    {
        return $this->totalWait;
    }

    public function setTotalWait(int $totalWait): ReleaseStats
    {
        $this->totalWait = $totalWait;
        return $this;
    }

    public function getTotalResponded(): int
    {
        return $this->totalResponded;
    }

    public function setTotalResponded(int $totalResponded): ReleaseStats
    {
        $this->totalResponded = $totalResponded;
        return $this;
    }

    public function getTotalPlans(): int
    {
        return $this->totalPlans;
    }

    public function setTotalPlans(int $totalPlans): ReleaseStats
    {
        $this->totalPlans = $totalPlans;
        return $this;
    }

    #[Groups(['releaseStats:read'])]
    public function getPercentage(): float
    {
        if ($this->totalWait !== 0 && $this->totalResponded !== 0) {
            return ($this->totalResponded / $this->totalWait) * 100;
        }
        return 0;
    }
}
