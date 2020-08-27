<?php

namespace App\Command;

use App\Model\Ride;
use Carbon\Carbon;
use maxh\Nominatim\Nominatim;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParseCommand extends Command
{
    protected static $defaultName = 'kidicalmass:parse';

    protected function configure()
    {
        $this
            ->setDescription('Add a short description for your command')
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $content = file_get_contents('https://kinderaufsrad.org/aktionsbuendnis/#aktionsorte');

        $crawler = new Crawler($content);
        $crawler = $crawler->filter('#aktionsorte .drag_element > div');

        $rideList = [];

        $crawler->each(function (Crawler $elementCrawler) use (&$rideList) {
            $ride = new Ride();

            $elementHtml = $elementCrawler->attr('data-html');
            $elementCrawler = new Crawler($elementHtml);

            $h2List = $elementCrawler->filter('h2');

            foreach ($h2List as $h2Element) {
                if ($h2Element->textContent) {
                    $title = $h2Element->textContent;
                    $city = str_replace('Kidical Mass ', '', $title);

                    $ride
                        ->setTitle($title)
                        ->setCityName($city);
                }
            }

            if (!$ride->getCityName()) {
                return;
            }

            $dateTimeList = $elementCrawler->filter('p > b > span');

            foreach ($dateTimeList as $dateTimeElement) {
                $germanDateTimeSpec = $dateTimeElement->textContent;
                $germanDateTimeSpec = str_replace([',', 'Uhr', 'März', 'Septmber'], ['', '', '03.', '09.'], $germanDateTimeSpec);

                try {
                    $dateTime = Carbon::parseFromLocale($germanDateTimeSpec);

                    $ride->setDateTime($dateTime);
                } catch (\Exception $exception) {
                    try {
                        $germanDateTimeSpec = str_replace(['x', 'X'], '', $germanDateTimeSpec);
                        $dateTime = Carbon::parseFromLocale($germanDateTimeSpec);

                        $ride->setDateTime($dateTime);
                    } catch (\Exception $exception) {

                    }
                }
            }

            $locationList = $elementCrawler->filter('div span');

            foreach ($locationList as $locationElement) {
                $locationString = $locationElement->textContent;
                if (strpos($locationString, 'Start: ') === 0 && strpos($locationString, 'folgt') === false) {
                    $location = str_replace('Start: ', '', $locationString);

                    $ride->setLocation($location);
                }
            }

            if ($ride->getLocation() && $ride->getCityName()) {
                $nominatim = new Nominatim('http://nominatim.openstreetmap.org/');

                $search = $nominatim->newSearch()
                    ->country('Germany')
                    ->city($city)
                    ->query($location)
                    ->addressDetails()
                    ->limit(1);

                $resultList = $nominatim->find($search);

                if (1 === count($resultList)) {
                    $result = array_pop($resultList);

                    $ride
                        ->setLatitude($result['lat'])
                        ->setLongitude($result['lon']);
                }
            }

            $rideList[] = $ride;
        });

        $io->table(['City', 'Title', 'DateTime', 'Location', 'Latitude', 'Longitude'], array_map(function (Ride $ride): array {
            return [$ride->getCityName(), $ride->getTitle(), $ride->hasDateTime() ? $ride->getDateTime()->format('Y-m-d H:i') : '', $ride->getLocation(), $ride->getLatitude(), $ride->getLongitude()];
        }, $rideList));

        return Command::SUCCESS;
    }
}
