<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTabBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(OrderStatus $status): Order
    {
        return Order::create([
            'channel' => 'sajapan',
            'channel_order_no' => (string) fake()->unique()->numerify('######'),
            'customer_name' => '홍길동',
            'customer_phone' => '01012345678',
            'shipping_address1' => '서울 강남구 테헤란로 1',
            'items' => [['name' => '상품', 'qty' => 1, 'price' => 1000]],
            'payment' => ['currency' => 'JPY', 'amount' => 1000],
            'status' => $status,
            'raw' => [],
        ]);
    }

    public function test_일괄_변경_후_탭_배지가_갱신된다(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->makeOrder(OrderStatus::Received);
        $b = $this->makeOrder(OrderStatus::Received);

        $component = Livewire::test(ListOrders::class);

        // 초기: 접수 2, 결제확인 0
        $tabs = $component->instance()->getTabs();
        $this->assertSame('2', (string) $tabs['received']->getBadge());
        $this->assertSame('0', (string) $tabs['payment_confirmed']->getBadge());

        $component->callTableBulkAction('bulk_advance', [$a, $b]);

        // 변경 후: 접수 0, 결제확인 2
        $tabs = $component->instance()->getTabs();
        $this->assertSame('0', (string) $tabs['received']->getBadge());
        $this->assertSame('2', (string) $tabs['payment_confirmed']->getBadge());
    }
}
