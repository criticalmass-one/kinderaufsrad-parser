<?php declare(strict_types=1);

namespace App\Serializer\Denormalizer;

use Carbon\Carbon;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;

class CarbonDenormalizer implements ContextAwareDenormalizerInterface
{
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return is_a($type, Carbon::class, true);
    }

    public function denormalize($data, $type, $format = null, array $context = []): ?Carbon
    {
        if ($data === null) {
            return null;
        }

        if (is_int($data)) {
            return Carbon::createFromTimestamp($data);
        }

        if (is_string($data)) {
            return new Carbon($data);
        }

        throw new NotNormalizableValueException(sprintf('Cannot denormalize value "%s" to Carbon', gettype($data)));
    }
}
