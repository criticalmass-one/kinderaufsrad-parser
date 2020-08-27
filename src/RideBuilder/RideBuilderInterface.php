<?php declare(strict_types=1);

namespace App\RideBuilder;

use App\Model\Ride;
use Symfony\Component\DomCrawler\Crawler;

interface RideBuilderInterface
{
    public function buildWithCrawler(Crawler $crawler): Ride;
}
