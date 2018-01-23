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

namespace ProophTest\HttpMiddleware\Container;

use Prooph\HttpMiddleware\MetadataGatherer;
use Psr\Http\Message\ServerRequestInterface;

final class StubMetadataGatherer implements MetadataGatherer
{
    public function getFromRequest(ServerRequestInterface $request): array
    {
        return [];
    }
}
