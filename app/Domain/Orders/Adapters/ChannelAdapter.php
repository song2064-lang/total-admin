<?php

namespace App\Domain\Orders\Adapters;

use App\Domain\Orders\OrderData;
use InvalidArgumentException;

interface ChannelAdapter
{
    public function channel(): string;

    /** @throws InvalidArgumentException */
    public function normalize(array $payload): OrderData;
}
