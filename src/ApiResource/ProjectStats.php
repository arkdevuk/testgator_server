<?php

namespace App\ApiResource;

class ProjectStats
{
    public function __construct(
        public readonly int $releases,
        public readonly int $testPlans,
        public readonly int $testers,
        public readonly int $answers,
    )
    {
    }
}
