<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'channel' => 'youngcart',
            'channel_order_no' => '2606110002',
            'customer_name' => '홍길동',
            'customer_phone' => '010-1234-5678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [['name' => '루테인', 'option' => null, 'qty' => 1, 'price' => 30000]],
            'payment' => ['method' => 'bank', 'amount' => 30000],
            'status' => OrderStatus::Received,
            'raw' => [],
        ]);
    }

    public function test_최초_수신_시_이력이_남는다(): void
    {
        $order = $this->makeOrder();

        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'received',
            'changed_by' => null,
        ]);
    }

    public function test_상태_변경_시_관리자와_함께_이력이_남는다(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($user);
        $order->update(['status' => OrderStatus::PaymentConfirmed]);

        $this->assertDatabaseHas('order_status_logs', [
            'order_id' => $order->id,
            'from_status' => 'received',
            'to_status' => 'payment_confirmed',
            'changed_by' => $user->id,
        ]);

        $this->assertSame(2, $order->statusLogs()->count());
    }

    public function test_상태_외_필드_수정은_이력을_남기지_않는다(): void
    {
        $order = $this->makeOrder();

        $order->update(['shipping_address2' => '202동 202호']);

        $this->assertSame(1, $order->statusLogs()->count());
    }
}
