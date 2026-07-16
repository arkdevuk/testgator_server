<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Services\Security\UnsafeUrlException;
use App\Services\Security\UrlSafetyChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class SafeUrlValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UrlSafetyChecker $urlSafetyChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeUrl) {
            throw new UnexpectedTypeException($constraint, SafeUrl::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        try {
            $this->urlSafetyChecker->assertSafe($value);
        } catch (UnsafeUrlException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reason }}', $e->getMessage())
                ->addViolation();
        }
    }
}
