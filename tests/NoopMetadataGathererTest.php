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

use PHPUnit\Framework\TestCase;
use Prooph\HttpMiddleware\MetadataGatherer;
use Prooph\HttpMiddleware\NoopMetadataGatherer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test integrity of \Prooph\HttpMiddleware\NoopMetadataGathererTest
 */
class NoopMetadataGathererTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_metadata_gatherer_interface(): void
    {
        $gatherer = new NoopMetadataGatherer();

        self::assertInstanceOf(MetadataGatherer::class, $gatherer);
    }

    /**
     * @test
     */
    public function it_return_array(): void
    {
        $gatherer = new NoopMetadataGatherer();
        $request = $this->prophesize(ServerRequestInterface::class);

        $this->assertInternalType('array', $gatherer->getFromRequest($request->reveal()));
        $this->assertEmpty($gatherer->getFromRequest($request->reveal()));
    }
}
