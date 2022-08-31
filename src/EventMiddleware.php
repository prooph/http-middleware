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
use Prooph\ServiceBus\EventBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Event messages describe things that happened while your model handled a command.
 *
 * The EventBus is able to dispatch a message to n listeners. Each listener can be a message handler or message
 * producer. Like commands the EventBus doesn't return anything.
 */
final class EventMiddleware implements MiddlewareInterface
{
    /**
     * Identifier to execute specific event
     *
     * @var string
     */
    public const NAME_ATTRIBUTE = 'prooph_event_name';

    /**
     * Dispatches event
     *
     * @var EventBus
     */
    private $eventBus;

    /**
     * Creates message depending on event name
     *
     * @var MessageFactory
     */
    private $eventFactory;

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
        EventBus $eventBus,
        MessageFactory $eventFactory,
        MetadataGatherer $metadataGatherer,
        ResponseStrategy $responseStrategy
    ) {
        $this->eventBus = $eventBus;
        $this->eventFactory = $eventFactory;
        $this->metadataGatherer = $metadataGatherer;
        $this->responseStrategy = $responseStrategy;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $eventName = $request->getAttribute(self::NAME_ATTRIBUTE);

        if (null === $eventName) {
            throw new RuntimeException(
                \sprintf('Event name attribute ("%s") was not found in request.', self::NAME_ATTRIBUTE),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        try {
            $event = $this->eventFactory->createMessageFromArray($eventName, [
                'payload' => $request->getParsedBody(),
                'metadata' => $this->metadataGatherer->getFromRequest($request),
            ]);

            $this->eventBus->dispatch($event);

            return $this->responseStrategy->withStatus(StatusCodeInterface::STATUS_ACCEPTED);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                \sprintf('An error occurred during dispatching of event "%s"', $eventName),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}
