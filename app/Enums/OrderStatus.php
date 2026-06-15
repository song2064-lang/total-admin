<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

// 주문 상태 (접수·결제확인·매입·입고·검수·국제배송·국내배송·배송완료)
enum OrderStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case PaymentConfirmed = 'payment_confirmed';
    case Purchased = 'purchased';
    case Warehoused = 'warehoused';
    case Inspected = 'inspected';
    case InternationalShipping = 'international_shipping';
    case DomesticShipping = 'domestic_shipping';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => '접수',
            self::PaymentConfirmed => '결제확인',
            self::Purchased => '매입',
            self::Warehoused => '입고',
            self::Inspected => '검수',
            self::InternationalShipping => '국제배송',
            self::DomesticShipping => '국내배송',
            self::Delivered => '배송완료',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::PaymentConfirmed => 'info',
            self::Purchased => 'warning',
            self::Warehoused => 'warning',
            self::Inspected => 'primary',
            self::InternationalShipping => 'info',
            self::DomesticShipping => 'info',
            self::Delivered => 'success',
        };
    }

    public function next(): ?self
    {
        $flow = self::cases();
        $index = array_search($this, $flow, true);

        return $flow[$index + 1] ?? null;
    }

    public function prev(): ?self
    {
        $flow = self::cases();
        $index = array_search($this, $flow, true);

        return $index > 0 ? $flow[$index - 1] : null;
    }

    // 한 단계 전진만 허용
    public function canTransitionTo(self $target): bool
    {
        return $this->next() === $target;
    }

    // 국제·국내배송은 송장 입력 필요
    public function requiresTracking(): bool
    {
        return $this === self::InternationalShipping || $this === self::DomesticShipping;
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => [$case->value, $case->getLabel()], self::cases()),
            1,
            0,
        );
    }
}
