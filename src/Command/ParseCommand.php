<?php declare(strict_types=1);

namespace App\Command;

use App\Model\Ride;
use App\RideBuilder\RideBuilderInterface;
use App\RidePusher\RidePusherInterface;
use App\RideRetriever\RideRetrieverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParseCommand extends Command
{
    protected static $defaultName = 'kidicalmass:parse';

    protected RideBuilderInterface $rideBuilder;

    protected RideRetrieverInterface $rideRetriever;

    protected RidePusherInterface $ridePusher;

    public function __construct(RideBuilderInterface $rideBuilder, RideRetrieverInterface $rideRetriever, RidePusherInterface $ridePusher, string $name = null)
    {
        $this->rideBuilder = $rideBuilder;
        $this->rideRetriever = $rideRetriever;
        $this->ridePusher = $ridePusher;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Fetch kidical mass rides from kinderaufsrad.org')
            ->addOption('complete-only', null, InputOption::VALUE_NONE, 'Only list rides with complete data')
            ->addOption('unexisting-only', null, InputOption::VALUE_NONE, 'Do not list already existing rides')
            ->addOption('require-existing-city', null, InputOption::VALUE_NONE, 'Require ride to belong to an existing city')
            ->addOption('require-not-null-city', null, InputOption::VALUE_NONE, 'Require ride to have a not null city')
            ->addOption('require-location', null, InputOption::VALUE_NONE, 'Require ride to have a location')
            ->addOption('require-datetime', null, InputOption::VALUE_NONE, 'Require ride to have a datetime')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Update rides')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $content = file_get_contents('https://umap.openstreetmap.fr/de/datalayer/1884684/');

        $json = json_decode($content);

        $cityFeatureList = [];

        foreach ($json->features as $feature) {
            $name = isset($feature->properties->Name) ? $feature->properties->Name : $feature->properties->name;

            $name = trim($name);

            $cityFeatureList[md5($name)] = $feature;
        }

        foreach ($cityFeatureList as $cityFeature) {
            $ride = $this->rideBuilder->buildFromFeature($cityFeature);
        }
        dd($cityFeatureList);
        die;
        $crawler = new Crawler($content);
        $crawler = $crawler->filter('#aktionsorte .drag_element > div');

        $rideList = [];

        $crawler->each(function (Crawler $elementCrawler) use (&$rideList) {
            $elementHtml = $elementCrawler->attr('data-html');
            $crawler = new Crawler($elementHtml);

            $ride = $this->rideBuilder->buildWithCrawler($crawler);

            $rideList[] = $ride;
        });

        $rideList = array_filter($rideList, function(Ride $ride): bool
        {
            return $ride->getCityName() && $ride->getCity();
        });

        if ($input->getOption('unexisting-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return !$this->rideRetriever->doesRideExist($ride);
            });
        }

        if ($input->getOption('require-datetime') || $input->getOption('complete-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return ($ride->getDateTime() && $ride->getDateTime()->format('H:i') !== '00:00');
            });
        }

        if ($input->getOption('require-location') || $input->getOption('complete-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return ($ride->getLocation() && $ride->getLatitude() && $ride->getLongitude());
            });
        }

        if ($input->getOption('require-existing-city') || $input->getOption('complete-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return $ride->getCity() !== null;
            });
        }

        if ($input->getOption('require-not-null-city') || $input->getOption('complete-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return !$ride->getCity() && $ride->getCityName();
            });
        }

        $rideList = $this->sortRideList($rideList);

        $io->table(['City', 'Title', 'Slug', 'DateTime', 'Location', 'Latitude', 'Longitude'], array_map(function (Ride $ride): array {
            return [$ride->getCity() ? $ride->getCity()->getName() : $ride->getCityName() . '?', $ride->getTitle(), $ride->getSlug(), $ride->hasDateTime() ? $ride->getDateTime()->format('Y-m-d H:i') : '', $ride->getLocation(), $ride->getLatitude(), $ride->getLongitude()];
        }, $rideList));

        if ($io->ask(sprintf('Should I post those %d rides to critical mass api? [y/n]', count($rideList)), 'n') === 'y') {
            $progressBar = $io->createProgressBar(count($rideList));

            foreach ($rideList as $ride) {
                if ($input->getOption('update')) {
                    $this->ridePusher->postRide($ride);
                } else {
                    $this->ridePusher->putRide($ride);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
        }

        return Command::SUCCESS;
    }

    protected function sortRideList(array $rideList): array
    {
        usort($rideList, function(Ride $a, Ride $b): int
        {
            if (!$a->getCity()) {
                $cityAName = $a->getCityName();
            } else {
                $cityAName = $a->getCity()->getName();
            }

            if (!$b->getCity()) {
                $cityBName = $b->getCityName();
            } else {
                $cityBName = $b->getCity()->getName();
            }

            if ($cityAName === $cityBName) {
                return 0;
            }

            return ($cityAName < $cityBName) ? -1 : 1;
        });

        return $rideList;
    }
}
