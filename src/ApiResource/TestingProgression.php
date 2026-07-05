<?php

declare(strict_types=1);

namespace App\ApiResource;

class TestingProgression
{
    public function __construct(
        public readonly int   $questions,
        public readonly int   $questionsAnswered,
        public readonly int   $questionsPending,
        public readonly float $progression,
    )
    {
    }
}
