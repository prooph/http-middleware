<?php

/**
 * This file is part of prooph/http-middleware.
 * (c) 2016-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2016-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\HttpMiddleware\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresMandatoryOptions;
use Prooph\HttpMiddleware\CommandMiddleware;
use Prooph\HttpMiddleware\NoopMetadataGatherer;
use Prooph\ServiceBus\CommandBus;
use Psr\Container\ContainerInterface;

final class CommandMiddlewareFactory extends AbstractMiddlewareFactory implements ProvidesDefaultOptions, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    public function __construct(string $configId = 'command')
    {
        parent::__construct($configId);
    }

    public function __invoke(ContainerInterface $container): CommandMiddleware
    {
        $options = $this->options($container->get('config'), $this->configId);

        if (isset($options['metadata_gatherer'])) {
            $gatherer = $container->get($options['metadata_gatherer']);
        } else {
            $gatherer = new NoopMetadataGatherer();
        }

        return new CommandMiddleware(
            $container->get($options['command_bus']),
            $container->get($options['message_factory']),
            $gatherer,
            $container->get($options['response_strategy'])
        );
    }

    public function defaultOptions(): iterable
    {
        return ['command_bus' => CommandBus::class];
    }

    public function mandatoryOptions(): iterable
    {
        return ['message_factory'];
    }
}
