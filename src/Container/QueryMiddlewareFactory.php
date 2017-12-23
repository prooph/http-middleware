<?php
/**
 * This file is part of prooph/http-middleware.
 * (c) 2016-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\HttpMiddleware\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresMandatoryOptions;
use Prooph\HttpMiddleware\NoopMetadataGatherer;
use Prooph\HttpMiddleware\QueryMiddleware;
use Prooph\ServiceBus\QueryBus;
use Psr\Container\ContainerInterface;

final class QueryMiddlewareFactory extends AbstractMiddlewareFactory implements ProvidesDefaultOptions, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    public function __construct(string $configId = 'query')
    {
        parent::__construct($configId);
    }

    public function __invoke(ContainerInterface $container): QueryMiddleware
    {
        $options = $this->options($container->get('config'), $this->configId);

        if (isset($options['metadata_gatherer'])) {
            $gatherer = $container->get($options['metadata_gatherer']);
        } else {
            $gatherer = new NoopMetadataGatherer();
        }

        return new QueryMiddleware(
            $container->get($options['query_bus']),
            $container->get($options['message_factory']),
            $container->get($options['response_strategy']),
            $gatherer
        );
    }

    public function defaultOptions(): iterable
    {
        return ['query_bus' => QueryBus::class];
    }

    public function mandatoryOptions(): iterable
    {
        return ['message_factory', 'response_strategy'];
    }
}
