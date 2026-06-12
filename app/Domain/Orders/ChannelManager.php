<?php

namespace App\Domain\Orders;

use App\Domain\Orders\Adapters\ChannelAdapter;
use InvalidArgumentException;

class ChannelManager
{
    public function isKnown(string $channel): bool
    {
        return config("channels.{$channel}") !== null;
    }

    public function secret(string $channel): ?string
    {
        return config("channels.{$channel}.secret");
    }

    public function adapter(string $channel): ChannelAdapter
    {
        $class = config("channels.{$channel}.adapter");

        if ($class === null) {
            throw new InvalidArgumentException("등록되지 않은 채널: {$channel}");
        }

        return app($class);
    }

    public static function labels(): array
    {
        return array_map(fn (array $config) => $config['label'], config('channels', []));
    }
}
