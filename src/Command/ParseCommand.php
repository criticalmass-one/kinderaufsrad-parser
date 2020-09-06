<?php declare(strict_types=1);

namespace App\Command;

use App\Model\Ride;
use App\RideBuilder\RideBuilderInterface;
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

    protected RideBuilderInterface $rideBuilder;

    public function __construct(RideBuilderInterface $rideBuilder, string $name = null)
    {
        $this->rideBuilder = $rideBuilder;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('Fetch kidical mass rides from kinderaufsrad.org')
            ->addOption('complete-only', null, InputOption::VALUE_NONE, 'Only list rides with complete data')
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

            $elementHtml = $elementCrawler->attr('data-html');
            $crawler = new Crawler($elementHtml);

            $ride = $this->rideBuilder->buildWithCrawler($crawler);

            $rideList[] = $ride;
        });

        usort($rideList, function(Ride $a, Ride $b): int
        {
            if ($a->getCity()->getName() === $b->getCity()->getName()) {
                return 0;
            }

            return ($a->getCity()->getName() < $b->getCity()->getName()) ? -1 : 1;
        });

        if ($input->getOption('complete-only')) {
            $rideList = array_filter($rideList, function(Ride $ride): bool
            {
                return ($ride->getCity() && $ride->getDateTime() && $ride->getDateTime()->format('H:i') !== '00:00' && $ride->getLocation() && $ride->getLatitude() && $ride->getLongitude());
            });
        }

        $io->table(['City', 'Title', 'Slug', 'DateTime', 'Location', 'Latitude', 'Longitude'], array_map(function (Ride $ride): array {
            return [$ride->getCityName(), $ride->getTitle(), $ride->getSlug(), $ride->hasDateTime() ? $ride->getDateTime()->format('Y-m-d H:i') : '', $ride->getLocation(), $ride->getLatitude(), $ride->getLongitude()];
        }, $rideList));

        return Command::SUCCESS;
    }
}
