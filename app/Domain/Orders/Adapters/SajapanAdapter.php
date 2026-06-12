<?php

namespace App\Domain\Orders\Adapters;

use App\Domain\Orders\OrderData;
use InvalidArgumentException;

// sa-japan(일본 구매대행 워드프레스) 주문 payload 변환
class SajapanAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return 'sajapan';
    }

    public function normalize(array $payload): OrderData
    {
        foreach (['order_id', 'name', 'phone', 'address', 'product'] as $key) {
            if (empty($payload[$key])) {
                throw new InvalidArgumentException("필수 항목 누락: {$key}");
            }
        }

        $product = (array) $payload['product'];

        if (empty($product['name'])) {
            throw new InvalidArgumentException('필수 항목 누락: product.name');
        }

        // 단일 상품 주문. 출처/상품 URL은 옵션 칸에 표시
        $option = trim(implode(' ', array_filter([
            $product['source'] ?? null,
            $product['url'] ?? null,
        ])));

        $items = [[
            'name' => (string) $product['name'],
            'option' => $option !== '' ? $option : null,
            'qty' => max(1, (int) ($product['qty'] ?? 1)),
            'price' => (int) ($product['price_jpy'] ?? 0),
        ]];

        return new OrderData(
            channel: $this->channel(),
            channelOrderNo: (string) $payload['order_id'],
            customerName: (string) $payload['name'],
            customerPhone: (string) $payload['phone'],
            shippingPostcode: $payload['zipcode'] ?? null,
            shippingAddress1: (string) $payload['address'],
            shippingAddress2: null,
            items: $items,
            payment: [
                'method' => null,
                'currency' => 'JPY',
                'amount' => (int) ($payload['total_jpy'] ?? 0),
                'subtotal_jpy' => (int) ($payload['subtotal_jpy'] ?? 0),
                'fees_jpy' => (array) ($payload['fees_jpy'] ?? []),
                'inspection' => $payload['inspection'] ?? null,
                'points_used' => (int) ($payload['points_used'] ?? 0),
            ],
            pccc: $payload['pccc'] ?? null,
            orderedAt: $payload['ordered_at'] ?? null,
            raw: $payload,
        );
    }
}
