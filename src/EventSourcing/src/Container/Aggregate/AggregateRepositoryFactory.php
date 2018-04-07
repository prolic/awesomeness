<?php
/**
 * This file is part of the prooph/event-sourcing.
 * (c) 2014-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventSourcing\Container\Aggregate;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;
use InvalidArgumentException;
use Prooph\EventSourcing\Aggregate\AggregateRepository;
use Prooph\EventSourcing\Aggregate\AggregateRootTranslator;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventSourcing\Aggregate\Exception;
use Prooph\EventStoreClient\EventStoreConnection;
use Psr\Container\ContainerInterface;

final class AggregateRepositoryFactory implements ProvidesDefaultOptions, RequiresConfigId, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    /**
     * @var string
     */
    private $configId;

    /**
     * Creates a new instance from a specified config, specifically meant to be used as static factory.
     *
     * In case you want to use another config key than provided by the factories, you can add the following factory to
     * your config:
     *
     * <code>
     * <?php
     * return [
     *     'your_aggregate_class' => [AggregateRepositoryFactory::class, 'your_aggregate_class'],
     * ];
     * </code>
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): AggregateRepository
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static($name))->__invoke($arguments[0]);
    }

    public function __construct(string $configId)
    {
        $this->configId = $configId;
    }

    public function __invoke(ContainerInterface $container): AggregateRepository
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        $repositoryClass = $config['repository_class'];

        if (! class_exists($repositoryClass)) {
            throw new Exception\RuntimeException(sprintf('Repository class %s cannot be found', $repositoryClass));
        }

        if (! is_subclass_of($repositoryClass, AggregateRepository::class)) {
            throw new Exception\RuntimeException(sprintf('Repository class %s must be a sub class of %s', $repositoryClass, AggregateRepository::class));
        }

        $eventStore = $container->get(EventStoreConnection::class);

        $aggregateType = new AggregateType($config['aggregate_type']);

        $aggregateTranslator = $container->get($config['aggregate_translator']);

        return new $repositoryClass(
            $eventStore,
            $aggregateType,
            $aggregateTranslator,
            $config['category'],
            $config['optimistic_concurrecy']
        );
    }

    public function dimensions(): iterable
    {
        return ['prooph', 'event_sourcing', 'aggregate_repository'];
    }

    public function defaultOptions(): iterable
    {
        return [
            'aggregate_translator' => AggregateRootTranslator::class,
            'optimistic_concurrecy' => true,
        ];
    }

    public function mandatoryOptions(): iterable
    {
        return [
            'repository_class',
            'aggregate_type',
            'category',
        ];
    }
}
