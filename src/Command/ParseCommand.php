<?php declare(strict_types=1);

namespace App\Command;

use App\Model\Ride;
use App\RideBuilder\RideBuilderInterface;
use App\RidePusher\RidePusherInterface;
use App\RideRetriever\RideRetrieverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseCommand extends Command
{
    protected static $defaultName = 'kidicalmass:parse';
    protected static $defaultDescription = 'Fetch kidical mass rides from kinderaufsrad.org';

    public function __construct(protected RideBuilderInterface $rideBuilder, protected RideRetrieverInterface $rideRetriever, protected RidePusherInterface $ridePusher, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->addArgument('map-identifier', InputArgument::REQUIRED, 'ID of geodata url')
            ->addOption('unexisting-only', null, InputOption::VALUE_NONE, 'Do not list already existing rides')
            ->addOption('existing-city-only', null, InputOption::VALUE_NONE, 'Only list rides in existing cities')
            ->addOption('non-existing-city-only', null, InputOption::VALUE_NONE, 'Only list rides in not existing cities')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Update rides')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $url = sprintf('https://umap.openstreetmap.fr/de/datalayer/%d/', $input->getArgument('map-identifier'));
        $content = file_get_contents($url);

        $json = json_decode($content, null, 512, JSON_THROW_ON_ERROR);

        $cityFeatureList = [];

        foreach ($json->features as $feature) {
            $name = $feature->properties->Name ?? $feature->properties->name;

            $name = trim($name);

            $cityFeatureList[md5($name)] = $feature;
        }

        $rideList = [];

        foreach ($cityFeatureList as $cityFeature) {
            $ride = $this->rideBuilder->buildFromFeature($cityFeature);

            if ($ride) {
                $rideList[] = $ride;
            }
        }

        $rideList = $this->sortRideList($rideList);

        if ($input->getOption('unexisting-only')) {
            $rideList = array_filter($rideList, fn(Ride $ride): bool => !$this->rideRetriever->doesRideExist($ride));
        }

        if ($input->getOption('non-existing-city-only')) {
            $rideList = array_filter($rideList, fn(Ride $ride): bool => $ride->getCity() === null);
        }

        if ($input->getOption('existing-city-only')) {
            $rideList = array_filter($rideList, fn(Ride $ride): bool => $ride->getCity() !== null);
        }

        $io->table(['City', 'Title', 'Slug', 'DateTime', 'Location', 'Latitude', 'Longitude'], array_map(fn(Ride $ride): array => [$ride->getCity() ? $ride->getCity()->getName() : $ride->getCityName() . '?', $ride->getTitle(), $ride->getSlug(), $ride->hasDateTime() ? $ride->getDateTime()->format('Y-m-d H:i') : '', $ride->getLocation(), $ride->getLatitude(), $ride->getLongitude()], $rideList));

        if ($io->ask(sprintf('Should I post those %d rides to critical mass api? [y/n]', count($rideList)), 'n') === 'y') {
            $progressBar = $io->createProgressBar(count($rideList));

            /** @var Ride $ride */
            foreach ($rideList as $ride) {
                if ($input->getOption('update')) {
                    try {
                        $this->ridePusher->postRide($ride);
                    } catch (\Exception) {
                        $io->error(sprintf('Ride %s (%s)not found', $ride->getTitle(), $ride->getSlug()));
                    }
                } else {
                    try {
                        $this->ridePusher->putRide($ride);
                    } catch (\Exception) {
                        $io->error(sprintf('Ride %s (%s) does already exist', $ride->getTitle(), $ride->getSlug()));
                    }
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
            return $cityAName <=> $cityBName;
        });

        return $rideList;
    }
}
