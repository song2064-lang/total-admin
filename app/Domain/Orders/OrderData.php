<?php

namespace App\Domain\Orders;

use App\Enums\OrderStatus;
use Illuminate\Support\Carbon;

// 표준 주문 DTO. 어댑터가 채널 payload를 이 형태로 변환한다
final readonly class OrderData
{
    public function __construct(
        public string $channel,
        public string $channelOrderNo,
        public string $customerName,
        public string $customerPhone,
        public ?string $shippingPostcode,
        public string $shippingAddress1,
        public ?string $shippingAddress2,
        public array $items,
        public array $payment,
        public ?string $pccc,
        public ?string $orderedAt,
        public array $raw,
    ) {}

    public function toModelAttributes(): array
    {
        return [
            'channel' => $this->channel,
            'channel_order_no' => $this->channelOrderNo,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'shipping_postcode' => $this->shippingPostcode,
            'shipping_address1' => $this->shippingAddress1,
            'shipping_address2' => $this->shippingAddress2,
            'items' => $this->items,
            'payment' => $this->payment,
            'pccc' => $this->pccc,
            'status' => OrderStatus::Received,
            'raw' => $this->raw,
            // 오프셋 포함 형식(ISO8601)도 정확히 저장되도록 앱 타임존으로 변환
            'ordered_at' => $this->orderedAt !== null
                ? Carbon::parse($this->orderedAt)->setTimezone(config('app.timezone'))
                : null,
        ];
    }
}
