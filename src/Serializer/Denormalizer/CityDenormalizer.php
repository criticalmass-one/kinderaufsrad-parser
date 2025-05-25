<?php declare(strict_types=1);

namespace App\Serializer\Denormalizer;

use App\Model\City;
use App\Model\CitySlug;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CityDenormalizer implements DenormalizerInterface
{

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $type === City::class;
    }

    public function denormalize($data, $type, $format = null, array $context = []): City
    {
        if (!is_array($data)) {
            throw new NotNormalizableValueException('Expected data to be an array.');
        }

        $city = new City();

        $city
            ->setId($data['id'] ?? 0)
            ->setName($data['name'] ?? '')
            ->setTimezone($data['timezone'] ?? 'Europe/Berlin')
            ->setLatitude($data['latitude'] ?? 0.0)
            ->setLongitude($data['longitude'] ?? 0.0)
        ;

        if (isset($data['main_slug']['slug'])) {
            $citySlug = new CitySlug();
            $citySlug
                ->setId($data['main_slug']['id'])
                ->setSlug($data['main_slug']['slug'])
            ;

            $city->setMainSlug($citySlug);
        }

        return $city;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            City::class => true,
        ];
    }
}
