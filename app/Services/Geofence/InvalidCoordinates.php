<?php

namespace App\Services\Geofence;

use InvalidArgumentException;

/** Local colado que não pôde ser interpretado. reason: short_link | invalid. */
final class InvalidCoordinates extends InvalidArgumentException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
