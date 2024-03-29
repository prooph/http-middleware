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

namespace Prooph\HttpMiddleware;

use Fig\Http\Message\StatusCodeInterface;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\HttpMiddleware\Exception\RuntimeException;
use Prooph\HttpMiddleware\Response\ResponseStrategy;
use Prooph\ServiceBus\CommandBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Command messages describe actions your model can handle.
 *
 * The CommandBus is designed to dispatch a message to only one handler or message producer. It does not return a
 * result. Sending a command means fire and forget and enforces the Tell-Don't-Ask principle.
 */
final class CommandMiddleware implements MiddlewareInterface
{
    /**
     * Identifier to execute specific command
     *
     * @var string
     */
    public const NAME_ATTRIBUTE = 'prooph_command_name';

    /**
     * Dispatches command
     *
     * @var CommandBus
     */
    private $commandBus;

    /**
     * Creates message depending on command name
     *
     * @var MessageFactory
     */
    private $commandFactory;

    /**
     * Gatherer of metadata from the request object
     *
     * @var MetadataGatherer
     */
    private $metadataGatherer;

    /**
     * Generate HTTP response with status code
     *
     * @var ResponseStrategy
     */
    private $responseStrategy;

    public function __construct(
        CommandBus $commandBus,
        MessageFactory $commandFactory,
        MetadataGatherer $metadataGatherer,
        ResponseStrategy $responseStrategy
    ) {
        $this->commandBus = $commandBus;
        $this->commandFactory = $commandFactory;
        $this->metadataGatherer = $metadataGatherer;
        $this->responseStrategy = $responseStrategy;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $commandName = $request->getAttribute(self::NAME_ATTRIBUTE);

        if (null === $commandName) {
            throw new RuntimeException(
                \sprintf('Command name attribute ("%s") was not found in request.', self::NAME_ATTRIBUTE),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        try {
            $command = $this->commandFactory->createMessageFromArray($commandName, [
                'payload' => $request->getParsedBody(),
                'metadata' => $this->metadataGatherer->getFromRequest($request),
            ]);

            $this->commandBus->dispatch($command);

            return $this->responseStrategy->withStatus(StatusCodeInterface::STATUS_ACCEPTED);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                \sprintf('An error occurred during dispatching of command "%s"', $commandName),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
