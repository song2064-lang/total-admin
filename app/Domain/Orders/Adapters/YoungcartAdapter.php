<?php

namespace App\Domain\Orders\Adapters;

use App\Domain\Orders\OrderData;
use InvalidArgumentException;

// 영카트 주문 payload 변환 (payload 예시는 README 참고)
class YoungcartAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return 'youngcart';
    }

    public function normalize(array $payload): OrderData
    {
        foreach (['od_id', 'buyer_name', 'buyer_phone', 'address1', 'items'] as $key) {
            if (empty($payload[$key])) {
                throw new InvalidArgumentException("필수 항목 누락: {$key}");
            }
        }

        if (! is_array($payload['items']) || $payload['items'] === []) {
            throw new InvalidArgumentException('items 는 비어있지 않은 배열이어야 합니다.');
        }

        $items = [];
        foreach ($payload['items'] as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('items 의 각 항목은 객체여야 합니다.');
            }
            $items[] = [
                'name' => (string) ($item['name'] ?? ''),
                'option' => $item['option'] ?? null,
                'qty' => (int) ($item['qty'] ?? 1),
                'price' => (int) ($item['price'] ?? 0),
            ];
        }

        return new OrderData(
            channel: $this->channel(),
            channelOrderNo: (string) $payload['od_id'],
            customerName: (string) $payload['buyer_name'],
            customerPhone: (string) $payload['buyer_phone'],
            shippingPostcode: $payload['zipcode'] ?? null,
            shippingAddress1: (string) $payload['address1'],
            shippingAddress2: $payload['address2'] ?? null,
            items: $items,
            payment: (array) ($payload['payment'] ?? []),
            pccc: $payload['pccc'] ?? null,
            orderedAt: $payload['ordered_at'] ?? null,
            raw: $payload,
        );
    }
}
