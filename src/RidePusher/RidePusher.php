<?php declare(strict_types=1);

namespace App\RidePusher;

use App\Model\Ride;
use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class RidePusher implements RidePusherInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
            'verify' => false,
        ]);

        // @see https://github.com/symfony/symfony/issues/29161
        AnnotationReader::addGlobalIgnoredName('alias');
    }

    public function postRide(Ride $ride): self
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();

        $apiUrl = sprintf('/api/%s/%s', $citySlug, $ride->getSlug());

        $this->client->post($apiUrl, [
            'body' => $this->serializer->serialize($ride, 'json'),
        ]);

        return $this;
    }

    public function putRide(Ride $ride): self
    {
        $citySlug = $ride->getCity()->getMainSlug()->getSlug();

        $apiUrl = sprintf('/api/%s/%s', $citySlug, $ride->getSlug());

        $this->client->put($apiUrl, [
            'body' => $this->serializer->serialize($ride, 'json'),
        ]);

        return $this;
    }
}
