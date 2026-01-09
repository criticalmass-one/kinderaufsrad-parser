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

        $ride = new Ride();

        if (array_key_exists('title', $data) && $data['title'] !== null) {
            $ride->setTitle($data['title']);
        }

        if (array_key_exists('slug', $data) && $data['slug'] !== null) {
            $ride->setSlug($data['slug']);
        }

        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $ride->setDescription($data['description']);
        }

        if (array_key_exists('location', $data) && $data['location'] !== null) {
            $ride->setLocation($data['location']);
        }

        if (array_key_exists('latitude', $data)) {
            $ride->setLatitude($data['latitude']);
        }

        if (array_key_exists('longitude', $data)) {
            $ride->setLongitude($data['longitude']);
        }

        if (array_key_exists('ride_type', $data) && $data['ride_type'] !== null) {
            $ride->setRideType($data['ride_type']);
        }

        if (array_key_exists('date_time', $data)) {
            $value = $data['date_time'];
            if (!is_int($value) && !is_string($value) && $value !== null) {
                throw new NotNormalizableValueException('Invalid Carbon input');
            }
            $dateTime = $this->denormalizeCarbon($value);
            if ($dateTime !== null) {
                $ride->setDateTime($dateTime);
            }
        }

        if (array_key_exists('created_at', $data)) {
            $value = $data['created_at'];
            if (!is_int($value) && !is_string($value) && $value !== null) {
                throw new NotNormalizableValueException('Invalid Carbon input');
            }
            $createdAt = $this->denormalizeCarbon($value);
            if ($createdAt !== null) {
                $ride->setCreatedAt($createdAt);
            }
        }

        if (array_key_exists('updated_at', $data)) {
            $value = $data['updated_at'];
            if (!is_int($value) && !is_string($value) && $value !== null) {
                throw new NotNormalizableValueException('Invalid Carbon input');
            }
            $updatedAt = $this->denormalizeCarbon($value);
            if ($updatedAt !== null) {
                $ride->setUpdatedAt($updatedAt);
            }
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
