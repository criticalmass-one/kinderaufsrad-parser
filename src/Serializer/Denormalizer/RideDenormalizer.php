<?php declare(strict_types=1);

namespace App\Serializer\Denormalizer;

use App\Model\City;
use App\Model\Ride;
use Carbon\Carbon;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class RideDenormalizer implements DenormalizerInterface
{
    public function __construct(
        private CityDenormalizer $cityDenormalizer
    ) {}

    public function denormalize($data, $type, $format = null, array $context = []): Ride
    {
        if (!is_array($data)) {
            throw new NotNormalizableValueException('Expected array data for Ride');
        }

        $ride = (new Ride())
            ->setTitle($data['title'] ?? null)
            ->setSlug($data['slug'] ?? null)
            ->setDescription($data['description'] ?? null)
            ->setLocation($data['location'] ?? null)
            ->setLatitude($data['latitude'] ?? null)
            ->setLongitude($data['longitude'] ?? null)
            ->setRideType($data['ride_type'] ?? null);

        if (isset($data['date_time'])) {
            $ride->setDateTime($this->denormalizeCarbon($data['date_time']));
        }

        if (isset($data['created_at'])) {
            $ride->setCreatedAt($this->denormalizeCarbon($data['created_at']));
        }

        if (isset($data['updated_at'])) {
            $ride->setUpdatedAt($this->denormalizeCarbon($data['updated_at']));
        }

        if (isset($data['city'])) {
            /** @var City $city */
            $city = $this->cityDenormalizer->denormalize($data['city'], City::class, $format, $context);
            $ride
                ->setCity($city)
                ->setCityName($city->getName())
            ;
        }

        return $ride;
    }

    protected function denormalizeCarbon(int|string|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if (is_string($value)) {
            return new Carbon($value);
        }

        throw new NotNormalizableValueException('Invalid Carbon input');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Ride::class => true
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Ride::class;
    }
}
