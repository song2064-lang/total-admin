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
        $this->assertSame(OrderStatus::Warehoused, OrderStatus::Purchased->next());
        $this->assertSame(OrderStatus::Inspected, OrderStatus::Warehoused->next());
        $this->assertSame(OrderStatus::InternationalShipping, OrderStatus::Inspected->next());
        $this->assertSame(OrderStatus::DomesticShipping, OrderStatus::InternationalShipping->next());
        $this->assertSame(OrderStatus::Delivered, OrderStatus::DomesticShipping->next());
        $this->assertNull(OrderStatus::Delivered->next());
    }

    public function test_한_단계_전진만_허용된다(): void
    {
        $this->assertTrue(OrderStatus::Received->canTransitionTo(OrderStatus::PaymentConfirmed));
        $this->assertFalse(OrderStatus::Received->canTransitionTo(OrderStatus::Purchased));
        $this->assertFalse(OrderStatus::PaymentConfirmed->canTransitionTo(OrderStatus::Received));
    }

    public function test_배송_단계만_송장_입력이_필요하다(): void
    {
        $this->assertTrue(OrderStatus::InternationalShipping->requiresTracking());
        $this->assertTrue(OrderStatus::DomesticShipping->requiresTracking());
        $this->assertFalse(OrderStatus::Inspected->requiresTracking());
        $this->assertFalse(OrderStatus::Delivered->requiresTracking());
    }
}
