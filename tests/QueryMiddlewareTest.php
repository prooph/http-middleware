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

namespace ProophTest\HttpMiddleware;

use Fig\Http\Message\RequestMethodInterface;
use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\HttpMiddleware\Exception\RuntimeException;
use Prooph\HttpMiddleware\MetadataGatherer;
use Prooph\HttpMiddleware\QueryMiddleware;
use Prooph\HttpMiddleware\Response\ResponseStrategy;
use Prooph\HttpMiddleware\RouteParamsExtractor;
use Prooph\ServiceBus\QueryBus;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;

/**
 * Test integrity of \Prooph\HttpMiddleware\QueryMiddleware
 */
class QueryMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_exception_if_queries_root_is_missing_body_request(): void
    {
        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn([['prooph_query_name' => 'test_query']])->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The root query value ("%s") must be provided.', QueryMiddleware::QUERIES_ATTRIBUTE));

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_query_name_attribute_is_not_set_body_request(): void
    {
        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn(['queries' => ['one' => ['key' => 'value']]])->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Each query must contain the query name attribute (%s)', QueryMiddleware::NAME_ATTRIBUTE));

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_query_has_no_route_params_extractor_get_request(): void
    {
        $queryBus = $this->prophesize(QueryBus::class);

        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn('foo')->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing route params query extractor for get request');

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_query_name_attribute_is_not_set_get_request(): void
    {
        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn(null)->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->shouldNotBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Query name attribute ("%s") was not found in request.', QueryMiddleware::NAME_ATTRIBUTE));

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_dispatch_failed_body_request(): void
    {
        $payload = ['queries' => ['one' => ['prooph_query_name' => 'stdClass', 'user_id' => 123]]];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->willThrow(
            new \Exception('Error')
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                'stdClass',
                ['prooph_query_name' => 'stdClass', 'user_id' => 123, 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->willReturn([])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred during dispatching of query "stdClass"');

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_throws_exception_if_dispatch_failed_get_request(): void
    {
        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->willThrow(
            new \Exception('Error')
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                'stdClass',
                ['payload' => ['query' => []], 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn('stdClass')->shouldBeCalled();
        $request->getQueryParams()->willReturn([])->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->willReturn([])->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn([])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred during dispatching of query "stdClass"');

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $middleware->process($request->reveal(), $handler->reveal());
    }

    /**
     * @test
     */
    public function it_dispatches_a_query_body_request(): void
    {
        $queryName = 'stdClass';
        $parsedBody = ['queries' => ['one' => ['prooph_query_name' => $queryName, 'filter' => []]]];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => $queryName, 'filter' => [], 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_a_query_get_request(): void
    {
        $queryName = 'stdClass';
        $messageData = ['payload' => ['query' => []], 'metadata' => []];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                $messageData
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();
        $request->getQueryParams()->willReturn([])->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(FulfilledPromise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn([])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_a_query_with_route_params_get_request(): void
    {
        $queryName = 'stdClass';
        $messageData = ['payload' => ['foo' => 'bar', 'bar' => 'foo', 'query' => []], 'metadata' => []];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                $messageData
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();
        $request->getQueryParams()->willReturn([])->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(FulfilledPromise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn(['foo' => 'bar', 'bar' => 'foo'])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_a_query_with_query_params_get_request(): void
    {
        $queryName = 'stdClass';
        $messageData = ['payload' => ['query' => ['foo' => 'bar', 'bar' => 'foo']], 'metadata' => []];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                $messageData
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();
        $request->getQueryParams()->willReturn(['foo' => 'bar', 'bar' => 'foo'])->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(FulfilledPromise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn([])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_a_query_with_route_params_and_query_params_get_request(): void
    {
        $queryName = 'stdClass';
        $messageData = ['payload' => ['foo' => 'bar', 'bar' => 'foo', 'query' => ['foo' => 'bar', 'bar' => 'foo']], 'metadata' => []];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                $messageData
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();
        $request->getQueryParams()->willReturn(['foo' => 'bar', 'bar' => 'foo'])->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(FulfilledPromise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn(['foo' => 'bar', 'bar' => 'foo'])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_multiple_queries_body_request(): void
    {
        $queryName = 'stdClass';
        $parsedBody = [
            'queries' => [
                'one' => ['prooph_query_name' => $queryName, 'filter' => []],
                'two' => ['prooph_query_name' => $queryName, 'filter' => ['test']],
            ],
        ];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(new FulfilledPromise([]));

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => $queryName, 'filter' => [], 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => $queryName, 'filter' => ['test'], 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();
        $request->getQueryParams()->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_handles_dispatch_exceptions_body_request(): void
    {
        $this->expectException(RuntimeException::class);

        $queryName = 'stdClass';
        $parsedBody = ['queries' => ['one' => ['prooph_query_name' => $queryName, 'filter' => []]]];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(new RuntimeException());

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => $queryName, 'filter' => [], 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_POST)->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }

    /**
     * @test
     */
    public function it_handles_dispatch_exceptions_get_request(): void
    {
        $this->expectException(RuntimeException::class);

        $queryName = 'stdClass';
        $messageData = ['payload' => ['query' => []], 'metadata' => []];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(new RuntimeException());

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                $messageData
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();
        $request->getQueryParams()->willReturn([])->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $routeParamsExctractor = $this->prophesize(RouteParamsExtractor::class);
        $routeParamsExctractor->extractRouteParams($request->reveal())->willReturn([])->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal(), $routeParamsExctractor->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }
}
