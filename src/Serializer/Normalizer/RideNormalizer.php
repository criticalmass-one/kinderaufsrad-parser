<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Model\Ride;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RideNormalizer implements NormalizerInterface
{
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof Ride;
    }

    public function normalize($object, $format = null, array $context = []): array
    {
        /** @var Ride $ride */
        $ride = $object;

        $city = $ride->getCity();

        return [
            'slug' => $ride->getSlug(),
            'title' => $ride->getTitle(),
            'description' => $ride->getDescription(),
            'date_time' => $ride->getDateTime()?->timestamp,
            'location' => $ride->getLocation(),
            'latitude' => $ride->getLatitude(),
            'longitude' => $ride->getLongitude(),
            'created_at' => $ride->getCreatedAt()?->timestamp,
            'updated_at' => $ride->getUpdatedAt()?->timestamp,
            'ride_type' => $ride->getRideType(),
        ];
    }
}
