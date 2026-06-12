<?php

namespace App\Domain\Orders\Adapters;

use App\Domain\Orders\OrderData;
use InvalidArgumentException;

// 우커머스 주문 payload 변환 (REST 주문 객체 축약형 기준)
class WoocommerceAdapter implements ChannelAdapter
{
    public function channel(): string
    {
        return 'woocommerce';
    }

    public function normalize(array $payload): OrderData
    {
        foreach (['id', 'billing', 'shipping', 'line_items'] as $key) {
            if (empty($payload[$key])) {
                throw new InvalidArgumentException("필수 항목 누락: {$key}");
            }
        }

        $billing = (array) $payload['billing'];
        $shipping = (array) $payload['shipping'];

        $customerName = trim(($billing['last_name'] ?? '').($billing['first_name'] ?? ''));

        if ($customerName === '') {
            throw new InvalidArgumentException('필수 항목 누락: billing.last_name/first_name');
        }

        if (empty($billing['phone'])) {
            throw new InvalidArgumentException('필수 항목 누락: billing.phone');
        }

        if (empty($shipping['address_1'])) {
            throw new InvalidArgumentException('필수 항목 누락: shipping.address_1');
        }

        if (! is_array($payload['line_items'])) {
            throw new InvalidArgumentException('line_items 는 배열이어야 합니다.');
        }

        $items = array_map(fn (array $item) => [
            'name' => (string) ($item['name'] ?? ''),
            'option' => $item['option'] ?? null,
            'qty' => (int) ($item['quantity'] ?? 1),
            'price' => (int) ($item['price'] ?? 0),
        ], $payload['line_items']);

        return new OrderData(
            channel: $this->channel(),
            channelOrderNo: (string) $payload['id'],
            customerName: $customerName,
            customerPhone: (string) $billing['phone'],
            shippingPostcode: $shipping['postcode'] ?? null,
            shippingAddress1: (string) $shipping['address_1'],
            shippingAddress2: $shipping['address_2'] ?? null,
            items: $items,
            payment: [
                'method' => $payload['payment_method'] ?? null,
                'amount' => (int) ($payload['total'] ?? 0),
                'paid_at' => $payload['date_paid'] ?? null,
            ],
            pccc: $payload['pccc'] ?? null,
            orderedAt: $payload['date_created'] ?? null,
            raw: $payload,
        );
    }
}
