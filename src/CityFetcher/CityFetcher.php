<?php declare(strict_types=1);

namespace App\CityFetcher;

use App\Model\City;
use App\Model\Ride;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class CityFetcher implements CityFetcherInterface
{
    protected Client $client;
    protected SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer, string $criticalmassHostname)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => $criticalmassHostname,
        ]);
    }

    public function getCityForRide(Ride $ride): ?City
    {
        return $this->getCityForName($ride->getCityName());
    }

    public function getCityForName(string $name): ?City
    {
        $name = $this->fixCityName($name);

        $query = [
            'name' => $name,
        ];

        $response = $this->client->get(sprintf('/api/city?%s', http_build_query($query)));

        $cityList = $this->serializer->deserialize($response->getBody()->getContents(), 'array<App\Model\City>', 'json');

        return array_pop($cityList);
    }

    protected function fixCityName(string $name): string
    {
        $mapping = [
            'Ulm' => 'Ulm & Neu-Ulm',
            'Stuttgart-Botnang' => 'Stuttgart',
            'Ravensburg' => 'Ravensburg',
            'Offenbach am Main' => 'Offenbach',
            'München Giesing Ost' => 'München',
            'Konstanz(-Kreuzlingen)' => 'Konstanz',
            'Kempen – St. Hubert' => 'Kempen',
            'Giessen' => 'Gießen',
            'Frankfurt am Main' => 'Frankfurt',
            'Brandenburg an der Havel' => 'Brandenburg',
            'Bottrop Kirchhellen' => 'Bottrop',
            'Berlin ADFC Kreisfahrt' => 'Berlin',
            'Berlin Charlottenburg-Wilmersdorf' => 'Berlin',
            'Berlin Friedrichshain-Kreuzberg' => 'Berlin',
            'Berlin Lichtenberg' => 'Berlin',
            'Berlin Pankow' => 'Pankow',
            'Berlin Reinickendorf' => 'Berlin',
            'Berlin Steglitz-Zehlendorf' => 'Berlin',
            'Berlin Treptow-Köpenick' => 'Berlin',
            'Geneva' => 'Genf',
            'Halle (Saale)' => 'Halle',
            'Oldenburg (Oldb./Nds.)' => 'Oldenburg',
            'Immenstadt' => 'Immenstadt im Allgäu',
            'Bottrop-Kirchhellen' => 'Bottrop',
            'Braunau' => 'Braunau am Inn',
            'Earlswood/ Redhill' => 'Redhill',
            'Gemeinde Lüdersdorf, Nordwestmecklenburg' => 'Lüdersdorf',
            'Nienburg/Weser' => 'Nienburg',
            'Ravensburg / Weingarten' => 'Ravensburg',
            'Wentorf bei Hamburg' => 'Wentorf',
            'Wien Neustadt' => 'Wien',
        ];

        $name = str_replace(['(AU)', '(AU )', '(CH)', '(FR)'], '', $name);

        if (array_key_exists($name, $mapping)) {
            $name = $mapping[$name];
        }

        return trim($name);
    }
}
