<?php declare(strict_types=1);

namespace App\Tests\Kernel;

use App\CityFetcher\CityFetcher;
use App\CityFetcher\CityFetcherInterface;
use App\Command\ParseCommand;
use App\RideBuilder\RideBuilder;
use App\RideBuilder\RideBuilderInterface;
use App\RidePusher\RidePusher;
use App\RidePusher\RidePusherInterface;
use App\RideRetriever\RideRetriever;
use App\RideRetriever\RideRetrieverInterface;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * Boots the real container (test env) to make sure autowiring of the interfaces and the
 * command registration still work. No HTTP call is made: services are only constructed.
 *
 * PHP deprecations raised while constructing services are captured so that they are
 * asserted explicitly instead of failing the run under SYMFONY_DEPRECATIONS_HELPER=max.
 */
final class ServiceWiringTest extends KernelTestCase
{
    /**
     * @template T
     * @param callable(): T $callable
     * @return array{0: T, 1: list<string>}
     */
    private static function capturingDeprecations(callable $callable): array
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $message) use (&$deprecations): bool {
            if ($errno !== E_DEPRECATED) {
                return false;
            }
            $deprecations[] = $message;

            return true;
        });

        try {
            return [$callable(), $deprecations];
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function parseCommandIsRegisteredInTheConsoleApplication(): void
    {
        $application = new Application(self::bootKernel());

        self::assertTrue($application->has('kidicalmass:parse'));

        [$command] = self::capturingDeprecations(static function () use ($application): object {
            $command = $application->find('kidicalmass:parse');

            return $command instanceof LazyCommand ? $command->getCommand() : $command;
        });

        self::assertInstanceOf(ParseCommand::class, $command);
        self::assertSame('kidicalmass:parse', $command->getName());
    }

    #[Test]
    public function parseCommandDeclaresExpectedArgumentsAndOptions(): void
    {
        $application = new Application(self::bootKernel());

        [$definition] = self::capturingDeprecations(static fn() => $application->find('kidicalmass:parse')->getDefinition());

        self::assertTrue($definition->getArgument('map-identifier')->isRequired());
        foreach (['unexisting-only', 'existing-city-only', 'non-existing-city-only', 'update'] as $flag) {
            self::assertFalse($definition->getOption($flag)->acceptValue(), $flag);
        }
        self::assertTrue($definition->getOption('city-filter')->isValueRequired());
        self::assertSame('cf', $definition->getOption('city-filter')->getShortcut());
    }

    #[Test]
    public function interfacesAreAliasedToTheirImplementations(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        [$services] = self::capturingDeprecations(static fn(): array => [
            $container->get(CityFetcherInterface::class),
            $container->get(RideBuilderInterface::class),
            $container->get(RidePusherInterface::class),
            $container->get(RideRetrieverInterface::class),
        ]);

        self::assertInstanceOf(CityFetcher::class, $services[0]);
        self::assertInstanceOf(RideBuilder::class, $services[1]);
        self::assertInstanceOf(RidePusher::class, $services[2]);
        self::assertInstanceOf(RideRetriever::class, $services[3]);
    }

    #[Test]
    public function httpClientsAreBoundToTheConfiguredCriticalmassHostname(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        foreach ([RidePusherInterface::class, RideRetrieverInterface::class, CityFetcherInterface::class] as $serviceId) {
            [$service] = self::capturingDeprecations(static fn(): object => $container->get($serviceId));
            $client = (new \ReflectionProperty($service, 'client'))->getValue($service);
            $config = (new \ReflectionProperty(Client::class, 'config'))->getValue($client);

            self::assertSame('https://criticalmass.in/', (string) $config['base_uri'], $serviceId);
        }
    }

    #[Test]
    public function constructingTheRealServicesTriggersNoDeprecation(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        [, $deprecations] = self::capturingDeprecations(static fn(): array => [
            new CityFetcher('https://cm.test/'),
            $container->get(CityFetcherInterface::class),
            $container->get(RideBuilderInterface::class),
            $container->get(RidePusherInterface::class),
            $container->get(RideRetrieverInterface::class),
        ]);

        self::assertSame([], $deprecations);
    }
}
