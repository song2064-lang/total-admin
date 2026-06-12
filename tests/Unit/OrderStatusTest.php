<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_상태는_정해진_순서로_전진한다(): void
    {
        $this->assertSame(OrderStatus::PaymentConfirmed, OrderStatus::Received->next());
        $this->assertSame(OrderStatus::Purchased, OrderStatus::PaymentConfirmed->next());
        $this->assertSame(OrderStatus::Inspected, OrderStatus::Purchased->next());
        $this->assertSame(OrderStatus::Shipped, OrderStatus::Inspected->next());
        $this->assertNull(OrderStatus::Shipped->next());
    }

    public function test_한_단계_전진만_허용된다(): void
    {
        $this->assertTrue(OrderStatus::Received->canTransitionTo(OrderStatus::PaymentConfirmed));
        $this->assertFalse(OrderStatus::Received->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::PaymentConfirmed->canTransitionTo(OrderStatus::Received));
    }
}
