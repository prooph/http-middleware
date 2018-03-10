<?php
/**
 * This file is part of prooph/http-middleware.
 * (c) 2016-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\HttpMiddleware;

use Assert\Assertion;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\HttpMiddleware\Exception\RuntimeException;
use Prooph\HttpMiddleware\Response\ResponseStrategy;
use Prooph\ServiceBus\QueryBus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function React\Promise\all;

/**
 * Query messages describe available information that can be fetched from your (read) model.
 *
 * The QueryBus also dispatches a message to only one finder (special query message handler) but it returns a
 * `React\Promise\Promise`. The QueryBus hands over the query message to a finder but also a `React\Promise\Deferred`
 * which needs to be resolved by the finder. We use promises to allow finders to handle queries asynchronous for
 * example using curl_multi_exec.
 */
final class QueryMiddleware implements MiddlewareInterface
{
    /**
     * The query message identifier.
     *
     * @var string
     */
    public const NAME_ATTRIBUTE = 'prooph_query_name';

    /**
     * Dispatches query
     *
     * @var QueryBus
     */
    private $queryBus;

    /**
     * Creates message depending on query name
     *
     * @var MessageFactory
     */
    private $queryFactory;

    /**
     * Generate HTTP response with result from Promise
     *
     * @var ResponseStrategy
     */
    private $responseStrategy;

    /**
     * Gatherer of metadata from the request object
     *
     * @var MetadataGatherer
     */
    private $metadataGatherer;

    public function __construct(
        QueryBus $queryBus,
        MessageFactory $queryFactory,
        ResponseStrategy $responseStrategy,
        MetadataGatherer $metadataGatherer
    ) {
        $this->queryBus = $queryBus;
        $this->queryFactory = $queryFactory;
        $this->responseStrategy = $responseStrategy;
        $this->metadataGatherer = $metadataGatherer;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $messages = $this->parseRequestMessages($request);

        $responses = [];

        foreach ($messages as $message) {
            $message['metadata'] = $this->metadataGatherer->getFromRequest($request);

            $query = $this->queryFactory->createMessageFromArray(
                $message[self::NAME_ATTRIBUTE],
                $message
            );

            try {
                $responses[] = $this->queryBus->dispatch($query);
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    sprintf('An error occurred during dispatching of query "%s"', $message[self::NAME_ATTRIBUTE]),
                    StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                    $e
                );
            }
        }

        try {
            return $this->responseStrategy->fromPromise(all($responses));
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'An error occurred dispatching queries',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    private function parseRequestMessages(ServerRequestInterface $request): array
    {
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $messages = $request->getParsedBody();

            $this->validateMessages($messages);
        } elseif ($request->getMethod() === RequestMethodInterface::METHOD_GET) {
            $messages = $this->parseGetMessage($request);
        } else {
            throw new RuntimeException(
                sprintf('Method %s not allowed.', $request->getMethod()),
                StatusCodeInterface::STATUS_METHOD_NOT_ALLOWED
            );
        }

        return $messages;
    }

    private function validateMessages(array $messages): void
    {
        Assertion::allSatisfy(
            $messages,
            function ($request) {
                return is_array($request) && array_key_exists(self::NAME_ATTRIBUTE, $request);
            },
            sprintf('The request body must be an array of objects with at least the %s property', self::NAME_ATTRIBUTE)
        );
    }

    private function parseGetMessage(ServerRequestInterface $request): array
    {
        $queryName = $request->getAttribute(self::NAME_ATTRIBUTE);

        if (null === $queryName) {
            throw new RuntimeException(
                sprintf('Query name attribute ("%s") was not found in request.', QueryMiddleware::NAME_ATTRIBUTE)
            );
        }

        $message = $request->getQueryParams();

        $message[self::NAME_ATTRIBUTE] = $queryName;

        return [$message];
    }
}
