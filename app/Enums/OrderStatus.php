<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

// 주문 상태: 접수 → 결제확인 → 매입 → 검수 → 발송
enum OrderStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case PaymentConfirmed = 'payment_confirmed';
    case Purchased = 'purchased';
    case Inspected = 'inspected';
    case Shipped = 'shipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => '접수',
            self::PaymentConfirmed => '결제확인',
            self::Purchased => '매입',
            self::Inspected => '검수',
            self::Shipped => '발송',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::PaymentConfirmed => 'info',
            self::Purchased => 'warning',
            self::Inspected => 'primary',
            self::Shipped => 'success',
        };
    }

    public function next(): ?self
    {
        $flow = self::cases();
        $index = array_search($this, $flow, true);

        return $flow[$index + 1] ?? null;
    }

    // 한 단계 전진만 허용
    public function canTransitionTo(self $target): bool
    {
        return $this->next() === $target;
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
