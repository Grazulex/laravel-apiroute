<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

enum DetectionStrategy: string
{
    case Uri = 'uri';
    case Header = 'header';
    case Query = 'query';
    case Accept = 'accept';
}
