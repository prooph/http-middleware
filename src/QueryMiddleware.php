<?php

/**
 * This file is part of prooph/http-middleware.
 * (c) 2016-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2016-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
     * The property identifier for the collection of queries.
     *
     * @var string
     */
    public const QUERIES_ATTRIBUTE = 'queries';

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
        $body = $request->getParsedBody();

        $this->validateRequestBody($body);

        $promises = [];

        foreach ($body[self::QUERIES_ATTRIBUTE] as $id => $message) {
            $message['metadata'] = $this->metadataGatherer->getFromRequest($request);

            $query = $this->queryFactory->createMessageFromArray(
                $message[self::NAME_ATTRIBUTE],
                $message
            );

            try {
                $promises[$id] = $this->queryBus->dispatch($query);
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    \sprintf('An error occurred during dispatching of query "%s"', $message[self::NAME_ATTRIBUTE]),
                    StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                    $e
                );
            }
        }

        try {
            $all = all($promises);

            return $this->responseStrategy->fromPromise($all);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'An error occurred dispatching queries',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    private function validateRequestBody(array $body): void
    {
        if (! isset($body[self::QUERIES_ATTRIBUTE])) {
            throw new RuntimeException(
                \sprintf('The root query value ("%s") must be provided.', QueryMiddleware::QUERIES_ATTRIBUTE)
            );
        }

        foreach ($body[self::QUERIES_ATTRIBUTE] as $message) {
            if (! \is_array($message) || ! \array_key_exists(self::NAME_ATTRIBUTE, $message)) {
                throw new RuntimeException(
                    \sprintf('Each query must contain the query name attribute (%s).', self::NAME_ATTRIBUTE)
                );
            }
        }
    }
}
