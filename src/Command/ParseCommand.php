<?php

namespace App\Command;

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
            $elementHtml = $elementCrawler->attr('data-html');
            $elementCrawler = new Crawler($elementHtml);

            $h2List = $elementCrawler->filter('h2');

            $title = null;

            foreach ($h2List as $h2) {
                if ($h2->textContent) {
                    $title = $h2->textContent;
                }
            }

            $rideList[] = [
                'title' => $title,
            ];
        });
        
        dump($rideList);
        
        return Command::SUCCESS;
    }
}
