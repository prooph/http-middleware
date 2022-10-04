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

use Psr\Http\Message\ServerRequestInterface;

final class NoopMetadataGatherer implements MetadataGatherer
{
    public function getFromRequest(ServerRequestInterface $request): array
    {
        return [];
    }
}
