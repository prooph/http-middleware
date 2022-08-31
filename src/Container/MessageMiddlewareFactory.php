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
use Prooph\HttpMiddleware\MessageMiddleware;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use Psr\Container\ContainerInterface;

final class MessageMiddlewareFactory extends AbstractMiddlewareFactory implements ProvidesDefaultOptions, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    public function __construct(string $configId = 'message')
    {
        parent::__construct($configId);
    }

    public function __invoke(ContainerInterface $container): MessageMiddleware
    {
        $options = $this->options($container->get('config'), $this->configId);

        return new MessageMiddleware(
            $container->get($options['command_bus']),
            $container->get($options['query_bus']),
            $container->get($options['event_bus']),
            $container->get($options['message_factory']),
            $container->get($options['response_strategy'])
        );
    }

    public function defaultOptions(): iterable
    {
        return [
            'command_bus' => CommandBus::class,
            'event_bus' => EventBus::class,
            'query_bus' => QueryBus::class,
        ];
    }

    public function mandatoryOptions(): iterable
    {
        return ['message_factory', 'response_strategy'];
    }
}
