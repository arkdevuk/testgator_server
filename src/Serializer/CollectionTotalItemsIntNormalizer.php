<?php

declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Decorates API Platform's Hydra collection normalizer to force the
 * collection count ("totalItems") back to an integer.
 *
 * Why this exists:
 * ApiPlatform\Doctrine\Orm\Paginator::getTotalItems() returns a float, and
 * the JSON-LD encoder runs with JSON_PRESERVE_ZERO_FRACTION (see ApiJsonEncode)
 * so real float properties keep their decimal point. The side effect is that a
 * count of 4 would serialize as 4.0. A collection count is conceptually an
 * integer, so we cast it here — without disturbing genuine float fields.
 */
final class CollectionTotalItemsIntNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(
        private readonly NormalizerInterface $decorated,
    )
    {
    }

    /**
     * The inner collection normalizer is NormalizerAware. Because this
     * decorator takes over the tagged service id, the serializer injects
     * itself here — forward it so the decorated normalizer gets it too.
     */
    public function setNormalizer(\Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;

        if ($this->decorated instanceof NormalizerAwareInterface) {
            $this->decorated->setNormalizer($normalizer);
        }
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $data = $this->decorated->normalize($object, $format, $context);

        if (\is_array($data)) {
            // Handles both the prefixed ("hydra:totalItems") and unprefixed
            // ("totalItems") key variants across API Platform versions.
            foreach (['totalItems', 'hydra:totalItems'] as $key) {
                if (isset($data[$key]) && \is_float($data[$key])) {
                    $data[$key] = (int)$data[$key];
                }
            }
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
