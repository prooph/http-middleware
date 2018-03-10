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
use Prooph\ServiceBus\QueryBus;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Promise\Promise;

/**
 * Test integrity of \Prooph\HttpMiddleware\QueryMiddleware
 */
class QueryMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_exception_if_query_name_attribute_is_not_set(): void
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
    public function it_throws_exception_if_dispatch_failed(): void
    {
        $queryName = 'stdClass';
        $payload = ['user_id' => 123];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->willThrow(
            new \Exception('Error')
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => 'stdClass', 'user_id' => 123, 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getQueryParams()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();

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
    public function it_dispatches_the_query(): void
    {
        $queryName = 'stdClass';
        $payload = ['user_id' => 123];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(
            $this->prophesize(Promise::class)->reveal()
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['prooph_query_name' => $queryName, 'user_id' => 123, 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethodInterface::METHOD_GET)->shouldBeCalled();
        $request->getQueryParams()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();

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
    public function it_dispatches_the_query_with_post_data(): void
    {
        $queryName = 'stdClass';
        $parsedBody = [['prooph_query_name' => $queryName, 'filter' => []]];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(
            $this->prophesize(Promise::class)->reveal()
        );

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
        $request->getMethod()->willReturn('POST')->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();
        $request->getQueryParams()->shouldNotBeCalled();
        $request->getAttribute()->shouldNotBeCalled();

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
    public function it_dispatches_multiple_queries(): void
    {
        $queryName = 'stdClass';
        $parsedBody = [
            ['prooph_query_name' => $queryName, 'filter' => []],
            ['prooph_query_name' => $queryName, 'filter' => ['test']],
        ];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(
            $this->prophesize(Promise::class)->reveal()
        );

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
        $request->getMethod()->willReturn('POST')->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();
        $request->getQueryParams()->shouldNotBeCalled();
        $request->getAttribute()->shouldNotBeCalled();

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
    public function it_handles_dispatch_exceptions(): void
    {
        $this->expectException(RuntimeException::class);

        $queryName = 'stdClass';
        $parsedBody = [['prooph_query_name' => $queryName, 'filter' => []]];

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
        $request->getMethod()->willReturn('POST')->shouldBeCalled();
        $request->getParsedBody()->willReturn($parsedBody)->shouldBeCalled();
        $request->getQueryParams()->shouldNotBeCalled();
        $request->getAttribute()->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal(), $gatherer->reveal());

        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }
}
