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

            $elementHtml = $elementCrawler->attr('data-html');
            $crawler = new Crawler($elementHtml);

            $ride = $this->rideBuilder->buildWithCrawler($crawler);

            $rideList[] = $ride;
        });

        $io->table(['City', 'Title', 'DateTime', 'Location', 'Latitude', 'Longitude'], array_map(function (Ride $ride): array {
            return [$ride->getCityName(), $ride->getTitle(), $ride->hasDateTime() ? $ride->getDateTime()->format('Y-m-d H:i') : '', $ride->getLocation(), $ride->getLatitude(), $ride->getLongitude()];
        }, $rideList));

        return Command::SUCCESS;
    }
}
