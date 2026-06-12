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

    public function test_구매대행_주문_상세에_수수료_내역이_보인다(): void
    {
        $user = User::factory()->create();

        $order = Order::create([
            'channel' => 'sajapan',
            'channel_order_no' => '178',
            'customer_name' => '김검증',
            'customer_phone' => '01098765432',
            'shipping_postcode' => '06236',
            'shipping_address1' => '서울 강남구 테헤란로 1 202동 202호',
            'items' => [
                [
                    'name' => '굿즈 세트 DEF',
                    'option' => null,
                    'qty' => 1,
                    'price' => 3500,
                    'source' => 'mercari',
                    'url' => 'https://jp.mercari.com/item/m12345678901',
                ],
            ],
            'payment' => [
                'method' => null,
                'currency' => 'JPY',
                'amount' => 4600,
                'subtotal_jpy' => 3500,
                'fees_jpy' => ['agency' => 400, 'remit' => 200, 'inspection' => 500],
                'inspection' => '사진검수',
                'fx_rate' => 9.5,
                'total_krw' => 43700,
                'points_used' => 1000,
            ],
            'pccc' => 'P987654321098',
            'status' => OrderStatus::Received,
            'raw' => ['memo' => '사진검수 부탁드립니다'],
            'ordered_at' => '2026-06-12 15:00:00',
        ]);

        $this->actingAs($user)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('대행수수료')
            ->assertSee('송금수수료')
            ->assertSee('사진검수')
            ->assertSee('적립금 사용')
            ->assertSee('요청 메모')
            ->assertSee('사진검수 부탁드립니다')
            ->assertSee('메루카리')
            ->assertSee('바로가기')
            ->assertSee('jp.mercari.com/item/m12345678901')
            ->assertSee('적용 환율')
            ->assertSee('100엔 = 950원')
            ->assertSee('최종 결제 예정액')
            ->assertSee('42,700원')
            ->assertSee('엔');
    }
}
