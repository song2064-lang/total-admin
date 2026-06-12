<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderScreenTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        return Order::create([
            'channel' => 'youngcart',
            'channel_order_no' => '2606110001',
            'customer_name' => '홍길동',
            'customer_phone' => '010-1234-5678',
            'shipping_postcode' => '06236',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'shipping_address2' => '101동 101호',
            'items' => [
                ['name' => '비타민C 1000mg', 'option' => '180정', 'qty' => 2, 'price' => 25000],
            ],
            'payment' => ['method' => 'bank', 'amount' => 50000, 'paid_at' => '2026-06-11 12:00:00'],
            'pccc' => 'P123412341234',
            'status' => OrderStatus::Received,
            'raw' => ['od_id' => '2606110001'],
            'ordered_at' => '2026-06-11 12:00:00',
        ]);
    }

    public function test_대시보드가_렌더링된다(): void
    {
        $user = User::factory()->create();
        $this->makeOrder();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_주문_목록_화면이_렌더링된다(): void
    {
        $user = User::factory()->create();
        $this->makeOrder();

        $this->actingAs($user)
            ->get('/admin/orders')
            ->assertOk();
    }

    public function test_주문_상세_화면이_렌더링된다(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($user)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('홍길동');
    }

    public function test_주문_수정_화면이_렌더링된다(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($user)
            ->get("/admin/orders/{$order->id}/edit")
            ->assertOk();
    }
}
